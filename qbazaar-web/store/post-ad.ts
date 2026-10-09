/**
 * Post-ad form state — Zustand.
 *
 * Lives outside the page so the form survives switching between the form,
 * the preview and the publish step, and so photo uploads keep updating it
 * while the seller looks at the preview. Not persisted: a reload starts a
 * clean form, and saved drafts are resumed from My Ads.
 */
import { create } from 'zustand';

import type { PromotionType } from '@/lib/api/commerce-types';
import type { Ad } from '@/lib/api/types';
import {
  EMPTY_AD_FORM,
  adFormFromAd,
  type AdFormErrors,
  type AdFormField,
  type AdFormValues,
} from '@/lib/post-ad/form';
import type { PhotoItem } from '@/lib/post-ad/photos';

export type PostAdView = 'form' | 'preview' | 'publish' | 'done';

/** `create:<user id>` for /post-ad, `edit:<ad id>` for the edit page. */
export type PostAdSession = `create:${string}` | `edit:${string}`;

export interface PostAdState {
  session: PostAdSession | null;
  view: PostAdView;
  /** The ad on the server: the one being edited, or the draft once saved. */
  ad: Ad | null;
  values: AdFormValues;
  errors: AdFormErrors;
  /** Managed by the photo queue (lib/post-ad/photo-queue). */
  photos: PhotoItem[];
  /** Promotions ticked while posting; they can be bought once the ad is live. */
  promotions: PromotionType[];

  begin: (session: PostAdSession, ad: Ad | null) => void;
  setValues: (patch: Partial<AdFormValues>) => void;
  setErrors: (errors: AdFormErrors) => void;
  clearError: (field: AdFormField) => void;
  setView: (view: PostAdView) => void;
  setAd: (ad: Ad) => void;
  togglePromotion: (type: PromotionType) => void;
}

export const usePostAdStore = create<PostAdState>((set) => ({
  session: null,
  view: 'form',
  ad: null,
  values: EMPTY_AD_FORM,
  errors: {},
  photos: [],
  promotions: [],

  begin: (session, ad) =>
    set({
      session,
      view: 'form',
      ad,
      values: ad ? adFormFromAd(ad) : EMPTY_AD_FORM,
      errors: {},
      promotions: [],
    }),
  setValues: (patch) => set((state) => ({ values: { ...state.values, ...patch } })),
  setErrors: (errors) => set({ errors }),
  clearError: (field) =>
    set((state) => {
      if (!state.errors[field]) return state;
      const errors = { ...state.errors };
      delete errors[field];
      return { errors };
    }),
  setView: (view) => set({ view }),
  setAd: (ad) => set({ ad }),
  togglePromotion: (type) =>
    set((state) => ({
      promotions: state.promotions.includes(type)
        ? state.promotions.filter((chosen) => chosen !== type)
        : [...state.promotions, type],
    })),
}));
