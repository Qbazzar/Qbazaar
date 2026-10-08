/**
 * Resizes and uploads ad photos one at a time, outside React, so the work
 * carries on while the seller switches between the form and the preview.
 *
 * One photo per request gives each tile its own progress and its own retry.
 * Requests never overlap: the API refuses a second upload to the same ad
 * while one is running (409) and allows 20 upload requests a minute (429),
 * so both are waited out and retried a few times.
 *
 * A new photo sends a live ad back to review, so for a live ad the queue
 * holds every change (uploads, removals and the order) until it is saved.
 */
import { ApiClientError } from '@/lib/api/auth';
import type { Media } from '@/lib/api/types';
import { checkPhotoInput, type PhotoInputProblem, type PreparedPhoto } from '@/lib/images/prepare-photo';

import { makeCover, movePhoto, photoFromMedia, sameOrder, uploadedMediaIds, type PhotoItem } from './photos';

export const RATE_LIMIT_WAIT_MS = 15_000;
export const BUSY_WAIT_MS = 2_000;
export const MAX_UPLOAD_ATTEMPTS = 4;

export interface UploadOptions {
  onProgress: (percent: number) => void;
  signal: AbortSignal;
}

export interface PhotoQueueDeps {
  getPhotos: () => PhotoItem[];
  setPhotos: (update: (photos: PhotoItem[]) => PhotoItem[]) => void;
  prepare: (file: Blob) => Promise<PreparedPhoto>;
  upload: (adId: string, file: Blob, options: UploadOptions) => Promise<Media>;
  reorder: (adId: string, mediaIds: Media['id'][]) => Promise<void>;
  removeMedia: (mediaId: Media['id']) => Promise<void>;
  createObjectUrl: (blob: Blob) => string;
  revokeObjectUrl: (url: string) => void;
  createKey: () => string;
  wait?: (ms: number, signal: AbortSignal) => Promise<void>;
}

export interface RejectedFile {
  name: string;
  problem: PhotoInputProblem | 'limit';
}

export interface AddResult {
  added: number;
  rejected: RejectedFile[];
}

export interface ResetOptions {
  /** Keep uploads, removals and reorders until `commit()`: the ad is live. */
  holdUntilSave?: boolean;
}

/** How long to wait before retrying a failed upload, or null to give up. */
export function uploadRetryDelay(error: unknown, attempt: number): number | null {
  if (attempt >= MAX_UPLOAD_ATTEMPTS || !(error instanceof ApiClientError)) return null;
  if (error.status === 429) return RATE_LIMIT_WAIT_MS * attempt;
  if (error.status === 409 && error.code === 'REQUEST_IN_PROGRESS') return BUSY_WAIT_MS;
  return null;
}

function waitFor(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve) => {
    const timer = setTimeout(resolve, ms);
    signal.addEventListener(
      'abort',
      () => {
        clearTimeout(timer);
        resolve();
      },
      { once: true },
    );
  });
}

function errorCodeOf(error: unknown): string {
  return error instanceof ApiClientError ? error.code : 'UNKNOWN_ERROR';
}

export type PhotoQueue = ReturnType<typeof createPhotoQueue>;

export function createPhotoQueue(deps: PhotoQueueDeps) {
  const wait = deps.wait ?? waitFor;
  const controllers = new Map<string, AbortController>();
  const listeners = new Set<(adId: string) => void>();
  const imagesChanged = (id: string) => listeners.forEach((listener) => listener(id));
  /** The ad changes go to; null until the ad exists, and outside a save while holding. */
  let adId: string | null = null;
  let holdUntilSave = false;
  /** Server photos the seller removed from a live ad, deleted on the next save. */
  let heldRemovals: PhotoItem[] = [];
  let refusedRemovals = 0;
  let serverOrder: Media['id'][] = [];
  let processing: Promise<void> | null = null;
  let uploading: Promise<void> | null = null;
  let ordering: Promise<void> = Promise.resolve();
  // Bumped by reset(), so loops of a previous form stop touching the new one.
  let generation = 0;

  const find = (key: string) => deps.getPhotos().find((photo) => photo.key === key);
  const patch = (key: string, changes: Partial<PhotoItem>) =>
    deps.setPhotos((photos) => photos.map((photo) => (photo.key === key ? { ...photo, ...changes } : photo)));

  function releaseUrl(photo: PhotoItem) {
    if (photo.ownsPreviewUrl) deps.revokeObjectUrl(photo.previewUrl);
  }

  const waiting = (status: PhotoItem['status']) => deps.getPhotos().some((photo) => photo.status === status);

  function process() {
    if (processing) return;
    const current = generation;
    const run = (async () => {
      for (;;) {
        const next = deps.getPhotos().find((photo) => photo.status === 'processing');
        if (!next?.source) return;
        try {
          const prepared = await deps.prepare(next.source);
          if (current !== generation) return;
          if (!find(next.key)) continue;
          releaseUrl(next);
          patch(next.key, {
            status: 'ready',
            file: prepared.blob,
            source: undefined,
            previewUrl: deps.createObjectUrl(prepared.blob),
            ownsPreviewUrl: true,
          });
        } catch {
          if (current !== generation) return;
          patch(next.key, { status: 'failed', failure: 'process', source: undefined });
        }
      }
    })();
    processing = run;
    void run.finally(() => {
      if (processing === run) processing = null;
      if (current !== generation) return;
      // Photos added while this loop was finishing would otherwise wait forever.
      if (waiting('processing')) process();
      upload();
    });
  }

  async function uploadWithRetry(id: string, photo: PhotoItem, signal: AbortSignal): Promise<Media> {
    for (let attempt = 1; ; attempt += 1) {
      try {
        return await deps.upload(id, photo.file as Blob, {
          signal,
          onProgress: (percent) => patch(photo.key, { progress: percent }),
        });
      } catch (error) {
        const delay = uploadRetryDelay(error, attempt);
        // A photo removed meanwhile is not worth another request from the rate limit.
        if (delay === null || signal.aborted || !find(photo.key)) throw error;
        patch(photo.key, { progress: 0 });
        await wait(delay, signal);
        if (signal.aborted) throw error;
      }
    }
  }

  /** Deletes the held removals; a photo the server keeps comes back into the list. */
  async function sendHeldRemovals(current: number): Promise<boolean> {
    let removed = false;
    for (const photo of heldRemovals.splice(0)) {
      const mediaId = (photo.media as Media).id;
      try {
        await deps.removeMedia(mediaId);
        if (current !== generation) return removed;
        serverOrder = serverOrder.filter((item) => item !== mediaId);
        releaseUrl(photo);
        removed = true;
      } catch {
        if (current !== generation) return removed;
        deps.setPhotos((photos) => [...photos, photo]);
        refusedRemovals += 1;
      }
    }
    return removed;
  }

  function upload() {
    if (uploading || !adId) return;
    const id = adId;
    const current = generation;
    let changed = false;
    const run = (async () => {
      if (await sendHeldRemovals(current)) changed = true;
      for (;;) {
        if (current !== generation) return;
        const next = deps.getPhotos().find((photo) => photo.status === 'ready');
        if (!next?.file) break;
        const controller = new AbortController();
        controllers.set(next.key, controller);
        patch(next.key, { status: 'uploading', progress: 0, failure: undefined, errorCode: undefined });
        try {
          const media = await uploadWithRetry(id, next, controller.signal);
          if (current !== generation) return;
          changed = true;
          serverOrder = [...serverOrder, media.id];
          if (!find(next.key)) {
            // Removed while it was on its way: take it off the ad again.
            await deps.removeMedia(media.id).catch(() => undefined);
            serverOrder = serverOrder.filter((mediaId) => mediaId !== media.id);
            continue;
          }
          patch(next.key, { status: 'uploaded', media, file: undefined, progress: 100 });
        } catch (error) {
          if (controller.signal.aborted || current !== generation) continue;
          patch(next.key, { status: 'failed', failure: 'upload', progress: 0, errorCode: errorCodeOf(error) });
        } finally {
          controllers.delete(next.key);
        }
      }
      if (await saveOrder(id, current)) changed = true;
    })();
    uploading = run;
    void run.finally(() => {
      if (uploading === run) uploading = null;
      if (current !== generation) return;
      if (changed) imagesChanged(id);
      if (waiting('ready')) {
        upload();
        return;
      }
      // A move made while the batch sent its own reorder is still unsaved.
      saveOrderWhenIdle();
    });
  }

  /** Sends the on-screen order when it differs from the server's. */
  async function saveOrder(id: string, current: number): Promise<boolean> {
    if (current !== generation) return false;
    const wanted = uploadedMediaIds(deps.getPhotos());
    if (wanted.length === 0 || sameOrder(wanted, serverOrder)) return false;
    try {
      await deps.reorder(id, wanted);
      if (current === generation) serverOrder = wanted;
      return true;
    } catch {
      // The server keeps its order; the next change tries again.
      return false;
    }
  }

  function saveOrderWhenIdle() {
    const id = adId;
    // A running upload batch saves the order when it finishes.
    if (!id || uploading) return;
    const current = generation;
    ordering = ordering.then(async () => {
      if (await saveOrder(id, current)) imagesChanged(id);
    });
  }

  /** Resolves once nothing is being resized, uploaded or reordered. */
  async function settle(): Promise<void> {
    while (processing || uploading) {
      await Promise.allSettled([processing, uploading]);
    }
    await ordering;
  }

  return {
    /** Calls `listener` whenever the ad's images changed on the server; returns the unsubscribe. */
    onImagesChanged(listener: (adId: string) => void): () => void {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },

    /** Starts over with the given server photos (a fresh form, or the ad being edited). */
    reset(media: Media[] = [], id: string | null = null, { holdUntilSave: hold = false }: ResetOptions = {}) {
      generation += 1;
      controllers.forEach((controller) => controller.abort());
      controllers.clear();
      deps.getPhotos().forEach(releaseUrl);
      heldRemovals.forEach(releaseUrl);
      heldRemovals = [];
      holdUntilSave = hold;
      deps.setPhotos(() => media.map(photoFromMedia));
      adId = hold ? null : id;
      serverOrder = media.map((item) => item.id);
      processing = null;
      uploading = null;
    },

    /**
     * The ad is saved: sends what waits for it (held removals, uploads, then
     * the order) and resolves when that is done, with the number of removals
     * the server refused (those photos are back in the list). A live ad holds
     * the changes made after this until its next save.
     */
    async commit(id: string): Promise<number> {
      const current = generation;
      adId = id;
      refusedRemovals = 0;
      upload();
      await settle();
      if (current !== generation) return 0;
      if (holdUntilSave) adId = null;
      return refusedRemovals;
    },

    /** Queues picked files, keeping the ad at `max` photos at most. */
    add(files: File[], max: number): AddResult {
      const room = Math.max(0, max - deps.getPhotos().length);
      const accepted: File[] = [];
      const rejected: RejectedFile[] = [];
      for (const file of files) {
        const problem = checkPhotoInput(file);
        if (problem) rejected.push({ name: file.name, problem });
        else if (accepted.length >= room) rejected.push({ name: file.name, problem: 'limit' });
        else accepted.push(file);
      }
      if (accepted.length > 0) {
        const added: PhotoItem[] = accepted.map((file) => ({
          key: deps.createKey(),
          status: 'processing',
          previewUrl: deps.createObjectUrl(file),
          ownsPreviewUrl: true,
          source: file,
          progress: 0,
        }));
        deps.setPhotos((photos) => [...photos, ...added]);
        process();
      }
      return { added: accepted.length, rejected };
    },

    retry(key: string) {
      const photo = find(key);
      if (!photo || photo.failure !== 'upload') return;
      patch(key, { status: 'ready', failure: undefined, errorCode: undefined, progress: 0 });
      upload();
    },

    /** Removes a photo; resolves false (and puts it back) when the server refuses. */
    async remove(key: string): Promise<boolean> {
      const photos = deps.getPhotos();
      const index = photos.findIndex((photo) => photo.key === key);
      if (index === -1) return true;
      const photo = photos[index];
      // Once every byte is sent the server keeps the photo even if the request
      // is cancelled, so let it finish; the upload loop then deletes it again.
      const fullySent = photo.status === 'uploading' && photo.progress >= 100;
      if (!fullySent) controllers.get(key)?.abort();
      deps.setPhotos((current) => current.filter((item) => item.key !== key));
      if (photo.status !== 'uploaded' || !photo.media) {
        releaseUrl(photo);
        return true;
      }
      if (holdUntilSave && !adId) {
        heldRemovals.push(photo);
        return true;
      }
      const mediaId = photo.media.id;
      try {
        await deps.removeMedia(mediaId);
        serverOrder = serverOrder.filter((item) => item !== mediaId);
        releaseUrl(photo);
        if (adId) imagesChanged(adId);
        return true;
      } catch {
        deps.setPhotos((current) => [...current.slice(0, index), photo, ...current.slice(index)]);
        return false;
      }
    },

    move(from: number, to: number) {
      deps.setPhotos((photos) => movePhoto(photos, from, to));
      saveOrderWhenIdle();
    },

    makeCover(key: string) {
      deps.setPhotos((photos) => makeCover(photos, key));
      saveOrderWhenIdle();
    },

    settle,
  };
}
