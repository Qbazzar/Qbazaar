/**
 * TanStack Query hooks for ad-image mutations.
 *
 * Images are nested resources under an ad, so every mutation invalidates the
 * parent `ad.detail` key. We don't expose a query hook here — images live
 * inline on the ad detail payload (Sprint 4 contract).
 */
import {
  useMutation,
  useQueryClient,
  type UseMutationResult,
} from '@tanstack/react-query';
import {
  deleteMedia,
  reorderAdImages,
  uploadAdImages,
  type UploadAdImagesOptions,
} from '@/lib/api/ad-images';
import type { ApiClientError } from '@/lib/api/auth';
import type { Media } from '@/lib/api/types';
import { adKeys } from './ads';

export interface UploadAdImagesPayload {
  adId: string;
  files: Array<File | Blob>;
  options?: UploadAdImagesOptions;
}

export function useUploadAdImagesMutation(): UseMutationResult<
  Media[],
  ApiClientError,
  UploadAdImagesPayload
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ adId, files, options }) =>
      uploadAdImages(adId, files, options),
    onSuccess: (_media, { adId }) => {
      qc.invalidateQueries({ queryKey: adKeys.detail(adId) });
    },
  });
}

export interface ReorderAdImagesPayload {
  adId: string;
  mediaIds: string[];
}

export function useReorderAdImagesMutation(): UseMutationResult<
  void,
  ApiClientError,
  ReorderAdImagesPayload
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ adId, mediaIds }) => reorderAdImages(adId, mediaIds),
    onSuccess: (_void, { adId }) => {
      qc.invalidateQueries({ queryKey: adKeys.detail(adId) });
    },
  });
}

export interface DeleteMediaPayload {
  /** Required so we can invalidate the parent ad's detail key. */
  adId: string;
  mediaId: string;
}

export function useDeleteMediaMutation(): UseMutationResult<
  void,
  ApiClientError,
  DeleteMediaPayload
> {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ mediaId }) => deleteMedia(mediaId),
    onSuccess: (_void, { adId }) => {
      qc.invalidateQueries({ queryKey: adKeys.detail(adId) });
    },
  });
}
