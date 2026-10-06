import type { Media } from '@/lib/api/types';

/**
 * processing: being resized in the browser
 * ready:      resized, waiting for the ad to exist or for its turn to upload
 * uploading:  on its way, `progress` 0-100
 * uploaded:   stored on the ad (`media` is set)
 * failed:     see `failure`
 */
export type PhotoStatus = 'processing' | 'ready' | 'uploading' | 'uploaded' | 'failed';

/** `process`: the browser could not read the file; `upload`: the API refused or the network failed. */
export type PhotoFailure = 'process' | 'upload';

export interface PhotoItem {
  key: string;
  status: PhotoStatus;
  /** What the tile shows: a local object URL or the server's thumbnail. */
  previewUrl: string;
  /** Set when `previewUrl` is an object URL this page created and must revoke. */
  ownsPreviewUrl: boolean;
  /** The picked original, until it has been resized. */
  source?: Blob;
  /** The resized JPEG, until it has been uploaded. */
  file?: Blob;
  media?: Media;
  progress: number;
  failure?: PhotoFailure;
  /** API error code of a failed upload, e.g. UPLOAD_003 or NETWORK_ERROR. */
  errorCode?: string;
}

export function photoFromMedia(media: Media): PhotoItem {
  return {
    key: `media-${media.id}`,
    status: 'uploaded',
    previewUrl: media.sizes?.thumbnail || media.sizes?.medium || media.url,
    ownsPreviewUrl: false,
    media,
    progress: 100,
  };
}

/** Large image for the preview gallery: the local JPEG beats a not-yet-generated server size. */
export function photoDisplayUrl(photo: PhotoItem): string {
  if (photo.ownsPreviewUrl || !photo.media) return photo.previewUrl;
  return photo.media.sizes?.large || photo.media.url || photo.previewUrl;
}

export function movePhoto(photos: PhotoItem[], from: number, to: number): PhotoItem[] {
  if (from === to || from < 0 || to < 0 || from >= photos.length || to >= photos.length) return photos;
  const next = [...photos];
  const [moved] = next.splice(from, 1);
  next.splice(to, 0, moved);
  return next;
}

/** The first photo is the cover. */
export function makeCover(photos: PhotoItem[], key: string): PhotoItem[] {
  return movePhoto(photos, photos.findIndex((photo) => photo.key === key), 0);
}

export function uploadedMediaIds(photos: PhotoItem[]): Media['id'][] {
  return photos.flatMap((photo) => (photo.status === 'uploaded' && photo.media ? [photo.media.id] : []));
}

/** Photos that count towards the ad: everything except failed ones. */
export function countedPhotos(photos: PhotoItem[]): number {
  return photos.filter((photo) => photo.status !== 'failed').length;
}

export function hasPendingPhotos(photos: PhotoItem[]): boolean {
  return photos.some((photo) => photo.status === 'processing' || photo.status === 'ready' || photo.status === 'uploading');
}

export function sameOrder<T>(a: readonly T[], b: readonly T[]): boolean {
  return a.length === b.length && a.every((value, index) => value === b[index]);
}
