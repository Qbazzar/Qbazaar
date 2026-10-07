'use client';

import { useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { ApiClientError } from '@/lib/api/auth';
import type { Ad, CategoryField } from '@/lib/api/types';
import { isPhoneNotVerifiedError } from '@/lib/auth/phone-gate';
import { t } from '@/lib/i18n/messages';
import {
  adFormFromAd,
  changedFields,
  firstErrorField,
  isPublishable,
  serverErrorsToForm,
  toAdPayload,
  validateAdForm,
  type AdFormErrors,
  type AdFormField,
} from '@/lib/post-ad/form';
import { useCreateAdMutation, useDeleteAdMutation, usePublishAdMutation, useUpdateAdMutation } from '@/lib/queries/ads';
import { usePostAdStore, type PostAdView } from '@/store/post-ad';

import { fieldId } from './FormParts';
import { photoQueue } from './session';
import { showSaved } from './toast';

export type PostAdAction = 'draft' | 'preview' | 'submit' | 'publish' | 'delete';

interface SaveResult {
  ad: Ad;
  uploadsFailed: boolean;
  /** The server kept a photo the seller removed; it is back in the list. */
  removalsRefused: boolean;
  /** At least one new photo reached the ad. */
  photosAdded: boolean;
}

/**
 * Focuses a field once React has painted its error state, and after the
 * step change (which focuses the step heading) when the error sent the
 * seller back to the form.
 */
function focusField(name: AdFormField): void {
  requestAnimationFrame(() => requestAnimationFrame(() => document.getElementById(fieldId(name))?.focus()));
}

function newIdempotencyKey(): string {
  return typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
    ? crypto.randomUUID()
    : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

/** Save draft, preview, submit and publish for the post-ad and edit-ad forms. */
export function usePostAdActions(fields: readonly CategoryField[]) {
  const router = useRouter();
  const createAd = useCreateAdMutation();
  const updateAd = useUpdateAdMutation();
  const publishAd = usePublishAdMutation();
  const deleteAd = useDeleteAdMutation();
  const [running, setRunning] = useState<PostAdAction | null>(null);
  // One key per ad, so a retried publish is answered from the API's cache.
  const publishKeys = useRef(new Map<string, string>());

  const store = () => usePostAdStore.getState();

  function show(view: PostAdView) {
    store().setView(view);
  }

  function showErrors(errors: AdFormErrors) {
    store().setErrors(errors);
    const first = firstErrorField(errors, fields);
    if (first) focusField(first);
  }

  function report(error: unknown, fallbackKey: string) {
    // The API client already sent the seller to phone verification.
    if (isPhoneNotVerifiedError(error)) return;
    if (error instanceof ApiClientError && error.code === 'VALIDATION_FAILED') {
      const fieldErrors = serverErrorsToForm(error.details);
      if (Object.keys(fieldErrors).length > 0) {
        show('form');
        showErrors(fieldErrors);
        return;
      }
    }
    toast.error(error instanceof ApiClientError && error.message ? error.message : t(fallbackKey));
  }

  /** Tells the seller which photo changes didn't reach the server; true when there were any. */
  function reportPhotoProblems(result: SaveResult): boolean {
    if (result.uploadsFailed) {
      toast.error(t('post_ad.photos.some_failed'));
      focusField('photos');
    } else if (result.removalsRefused) {
      toast.error(t('post_ad.photos.remove_failed'));
    }
    return result.uploadsFailed || result.removalsRefused;
  }

  /** Checks the form, showing and focusing what is wrong; true when it is valid. */
  function validate(requirePhoto: boolean): boolean {
    const { values, photos } = store();
    const errors = validateAdForm(values, { fields, photoCount: photos.length, requirePhoto });
    if (Object.keys(errors).length > 0) {
      showErrors(errors);
      return false;
    }
    store().setErrors({});
    return true;
  }

  /** Creates the ad, or sends what changed on the one being edited (nothing at all leaves it untouched). */
  async function saveFields(): Promise<Ad> {
    const { values, ad } = store();
    const payload = toAdPayload(values, fields);
    if (!ad) return createAd.mutateAsync(payload);
    const changes = changedFields(toAdPayload(adFormFromAd(ad), fields), payload);
    return Object.keys(changes).length > 0 ? updateAd.mutateAsync({ id: ad.id, payload: changes }) : ad;
  }

  /**
   * Validates, saves the ad and then its photos. Resolves to the saved ad, or
   * null when the form is invalid; failed photos don't undo the save.
   */
  async function save(requirePhoto: boolean): Promise<SaveResult | null> {
    if (!validate(requirePhoto)) return null;

    const saved = await saveFields();
    store().setAd(saved);
    const waiting = new Set(store().photos.filter((photo) => photo.status !== 'uploaded').map((photo) => photo.key));
    const refusedRemovals = await photoQueue.commit(saved.id);
    const photos = store().photos;
    return {
      ad: saved,
      uploadsFailed: photos.some((photo) => photo.status === 'failed'),
      removalsRefused: refusedRemovals > 0,
      photosAdded: photos.some((photo) => waiting.has(photo.key) && photo.status === 'uploaded'),
    };
  }

  async function run(action: PostAdAction, fallbackKey: string, task: () => Promise<void>) {
    if (running) return;
    setRunning(action);
    try {
      await task();
    } catch (error) {
      report(error, fallbackKey);
    } finally {
      setRunning(null);
    }
  }

  const saveDraft = () =>
    run('draft', 'post_ad.toast.save_failed', async () => {
      const result = await save(false);
      if (!result) return;
      showSaved(t('post_ad.toast.draft_saved'));
      reportPhotoProblems(result);
    });

  const preview = () =>
    run('preview', 'post_ad.toast.save_failed', async () => {
      // Saving a live ad can send it back to review, so only "Save Changes" saves it.
      if (!isPublishable(store().ad)) {
        if (validate(true)) show('preview');
        return;
      }
      const result = await save(false);
      if (result && !reportPhotoProblems(result)) show('preview');
    });

  /** "Add Ads" on a new or draft ad (on to the publish step), "Save Changes" on a live one. */
  const submit = () =>
    run('submit', 'post_ad.toast.save_failed', async () => {
      const publishable = isPublishable(store().ad);
      const result = await save(true);
      if (!result || reportPhotoProblems(result)) return;
      if (publishable) {
        show('publish');
        return;
      }
      // The API resubmits a live ad when new photos reach it.
      const { status } = result.ad;
      const inReview = status === 'pending' || (status === 'active' && result.photosAdded);
      showSaved(t(inReview ? 'post_ad.toast.changes_in_review' : 'post_ad.toast.changes_saved'));
      router.push('/account/ads');
    });

  const publish = () =>
    run('publish', 'post_ad.toast.publish_failed', async () => {
      const ad = store().ad;
      if (!ad) return;
      const key = publishKeys.current.get(ad.id) ?? newIdempotencyKey();
      publishKeys.current.set(ad.id, key);
      try {
        const published = await publishAd.mutateAsync({ id: ad.id, acceptedTerms: true, idempotencyKey: key });
        store().setAd(published);
        show('done');
      } catch (error) {
        if (error instanceof ApiClientError && error.code === 'AD_009') {
          show('form');
          showErrors({ photos: 'post_ad.errors.photos_required' });
          return;
        }
        if (error instanceof ApiClientError && error.code === 'AD_006') {
          toast.error(t('post_ad.toast.daily_limit'));
          return;
        }
        throw error;
      }
    });

  /** "Delete" on the publish step: removes the ad and takes the seller to My Ads. */
  const remove = () =>
    run('delete', 'ads.errors.delete_failed', async () => {
      const ad = store().ad;
      if (!ad) return;
      await deleteAd.mutateAsync(ad.id);
      showSaved(t('ads.actions.delete_success'));
      router.push('/account/ads');
    });

  return { running, saveDraft, preview, submit, publish, remove, edit: () => show('form') };
}
