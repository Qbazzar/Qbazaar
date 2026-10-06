'use client';

import { useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { ApiClientError } from '@/lib/api/auth';
import type { Ad, AdStatus, CategoryField } from '@/lib/api/types';
import { isPhoneNotVerifiedError } from '@/lib/auth/phone-gate';
import { t } from '@/lib/i18n/messages';
import {
  firstErrorField,
  serverErrorsToForm,
  toAdPayload,
  validateAdForm,
  type AdFormErrors,
  type AdFormField,
} from '@/lib/post-ad/form';
import { useCreateAdMutation, usePublishAdMutation, useUpdateAdMutation } from '@/lib/queries/ads';
import { usePostAdStore, type PostAdView } from '@/store/post-ad';

import { fieldId } from './FormParts';
import { photoQueue } from './session';

export type PostAdAction = 'draft' | 'preview' | 'submit' | 'publish';

/** Ads that go through the publish step; any other ad is saved in place. */
const PUBLISHABLE_STATUSES: readonly AdStatus[] = ['draft', 'rejected'];

export function isPublishable(ad: Ad | null): boolean {
  return ad === null || PUBLISHABLE_STATUSES.includes(ad.status);
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

  function warnFailedPhotos() {
    toast.error(t('post_ad.photos.some_failed'));
    focusField('photos');
  }

  /**
   * Validates, saves the ad and waits for its photos. Resolves to the saved
   * ad, or null when the form is invalid; failed photos don't undo the save.
   */
  async function save(requirePhoto: boolean): Promise<{ ad: Ad; photosFailed: boolean } | null> {
    const { values, photos, ad } = store();
    const errors = validateAdForm(values, { fields, photoCount: photos.length, requirePhoto });
    if (Object.keys(errors).length > 0) {
      showErrors(errors);
      return null;
    }
    store().setErrors({});

    const payload = toAdPayload(values, fields);
    const saved = ad ? await updateAd.mutateAsync({ id: ad.id, payload }) : await createAd.mutateAsync(payload);
    store().setAd(saved);
    photoQueue.attach(saved.id);
    await photoQueue.settle();
    return { ad: saved, photosFailed: store().photos.some((photo) => photo.status === 'failed') };
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
      toast.success(t('post_ad.toast.draft_saved'));
      if (result.photosFailed) warnFailedPhotos();
    });

  const preview = () =>
    run('preview', 'post_ad.toast.save_failed', async () => {
      const result = await save(false);
      if (!result) return;
      if (result.photosFailed) warnFailedPhotos();
      else show('preview');
    });

  /** "Add Ads" on a new or draft ad (on to the publish step), "Save Changes" on a live one. */
  const submit = () =>
    run('submit', 'post_ad.toast.save_failed', async () => {
      const before = store().ad;
      const result = await save(true);
      if (!result) return;
      if (result.photosFailed) {
        warnFailedPhotos();
        return;
      }
      if (isPublishable(before)) {
        show('publish');
        return;
      }
      toast.success(t(before?.status === 'active' ? 'post_ad.toast.changes_in_review' : 'post_ad.toast.changes_saved'));
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

  return { running, saveDraft, preview, submit, publish, edit: () => show('form') };
}
