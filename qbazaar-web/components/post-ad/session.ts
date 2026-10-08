'use client';

import { deleteMedia, reorderAdImages, uploadAdImages } from '@/lib/api/ad-images';
import type { Ad } from '@/lib/api/types';
import { preparePhoto } from '@/lib/images/prepare-photo';
import { isPublishable } from '@/lib/post-ad/form';
import { createPhotoQueue } from '@/lib/post-ad/photo-queue';
import { usePostAdStore, type PostAdSession } from '@/store/post-ad';

let photoKeySeed = 0;

/** The one photo queue of the post-ad form, writing into its store. */
export const photoQueue = createPhotoQueue({
  getPhotos: () => usePostAdStore.getState().photos,
  setPhotos: (update) => usePostAdStore.setState((state) => ({ photos: update(state.photos) })),
  prepare: (file) => preparePhoto(file),
  upload: async (adId, file, { onProgress, signal }) => {
    const [media] = await uploadAdImages(adId, [file], { onProgress, signal });
    if (!media) throw new Error('The upload returned no image');
    return media;
  },
  reorder: reorderAdImages,
  removeMedia: deleteMedia,
  createObjectUrl: (blob) => URL.createObjectURL(blob),
  revokeObjectUrl: (url) => URL.revokeObjectURL(url),
  createKey: () => `photo-${(photoKeySeed += 1)}`,
});

/** The session key of a new ad, per seller, so one seller never sees another's unsaved form. */
export function createSession(userId: string): PostAdSession {
  return `create:${userId}`;
}

/**
 * Opens the form for a new ad or for the ad being edited. A new-ad form the
 * seller left before saving is kept, so a stray click doesn't lose their
 * typing; anything already saved lives on in My Ads, so that form restarts.
 * A live ad's photo changes wait for "Save Changes", like its other fields.
 */
export function beginPostAdSession(session: PostAdSession, ad: Ad | null): void {
  const state = usePostAdStore.getState();
  const keepUnsaved = session.startsWith('create:') && state.session === session && state.ad === null && state.view !== 'done';
  if (keepUnsaved) return;
  photoQueue.reset(ad?.images ?? [], ad?.id ?? null, { holdUntilSave: !isPublishable(ad) });
  state.begin(session, ad);
}
