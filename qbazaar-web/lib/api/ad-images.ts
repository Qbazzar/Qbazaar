/**
 * Typed client for ad-images upload/reorder/delete (BE-4.5 + BE-4.6).
 *
 * Uploads use `multipart/form-data` with per-file progress streamed through
 * axios so the dropzone can render a real progress bar. Reorder + delete are
 * plain JSON / 204 endpoints.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type { ErrorEnvelope, Media, SuccessEnvelope } from './types';

function toApiClientError(err: unknown): ApiClientError {
  if (isAxiosError<ErrorEnvelope>(err) && err.response?.data?.error) {
    const e = err.response.data.error;
    return new ApiClientError({
      status: err.response.status,
      code: e.code,
      messageKey: e.message_key,
      message: e.message,
      details: e.details,
      requestId: e.request_id,
    });
  }
  if (err instanceof Error) {
    return new ApiClientError({
      status: 0,
      code: 'NETWORK_ERROR',
      messageKey: 'errors.network',
      message: err.message,
    });
  }
  return new ApiClientError({
    status: 0,
    code: 'UNKNOWN_ERROR',
    messageKey: 'errors.unknown',
    message: 'Unknown error',
  });
}

export interface UploadAdImagesOptions {
  /** 0-100 progress for the combined multipart upload. */
  onProgress?: (percent: number) => void;
  signal?: AbortSignal;
}

/**
 * Uploads one or more image files (already compressed client-side) to an ad.
 *
 * The backend returns the newly persisted `Media[]` rows ordered by `order`.
 * Callers should append them to the existing thumbnails — the server does NOT
 * re-render the whole gallery in the response.
 */
export async function uploadAdImages(
  adId: string,
  files: Array<File | Blob>,
  options: UploadAdImagesOptions = {},
): Promise<Media[]> {
  if (files.length === 0) return [];
  try {
    const form = new FormData();
    files.forEach((file, idx) => {
      const filename =
        file instanceof File && file.name ? file.name : `image-${idx + 1}.jpg`;
      // Laravel expects `images[]` to materialise as an array of UploadedFile.
      form.append('images[]', file, filename);
    });

    // The endpoint wraps the new rows under `data.images` (see the API's
    // AdImageController + the OpenAPI contract) — NOT a bare array. Reading
    // `data.data` directly handed callers an object, which blew up the
    // dropzone's `[...images, ...uploaded]` spread with "not iterable".
    const { data } = await api.post<SuccessEnvelope<{ images: Media[] }>>(
      `/api/v1/ads/${encodeURIComponent(adId)}/images`,
      form,
      {
        headers: { 'Content-Type': 'multipart/form-data' },
        signal: options.signal,
        onUploadProgress: (event) => {
          if (!options.onProgress || !event.total) return;
          const percent = Math.round((event.loaded * 100) / event.total);
          options.onProgress(Math.min(100, Math.max(0, percent)));
        },
      },
    );
    return data.data.images ?? [];
  } catch (err) {
    throw toApiClientError(err);
  }
}

/**
 * Persist a new order for an ad's images. Server validates ownership +
 * verifies every media id belongs to the ad.
 */
export async function reorderAdImages(
  adId: string,
  mediaIds: string[],
): Promise<void> {
  try {
    // The endpoint validates an `order` array of media ids (see the API's
    // ReorderImagesRequest + the OpenAPI contract). Sending `media_ids` made
    // the request fail validation ("The given data was invalid") on every
    // cover/reorder change.
    await api.post(
      `/api/v1/ads/${encodeURIComponent(adId)}/images/reorder`,
      { order: mediaIds },
    );
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function deleteMedia(mediaId: string): Promise<void> {
  try {
    await api.delete(`/api/v1/media/${encodeURIComponent(mediaId)}`);
  } catch (err) {
    throw toApiClientError(err);
  }
}
