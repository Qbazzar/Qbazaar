/**
 * Post-ad wizard state — Zustand.
 *
 * Holds the multi-step form state for the `/post-ad` flow:
 *
 *   - which step the user is on (1..4)
 *   - the chosen category + location ids
 *   - the in-progress text fields
 *   - the draft ad id once it's been created server-side (step 4 needs it
 *     so image uploads have a parent)
 *
 * NOT persisted — the wizard is transient by design. If the user reloads we
 * want them to start clean rather than recover a stale half-filled form.
 */
import { create } from 'zustand';
import type { AdCondition, Media, PriceType } from '@/lib/api/types';

export type PostAdStep = 1 | 2 | 3 | 4;

export interface PostAdDetails {
  title: string;
  description: string;
  price: string;          // input is a string so RHF can show empty state
  price_type: PriceType;
  condition: AdCondition | null;
  custom_fields: Record<string, unknown>;
}

export interface PostAdState {
  step: PostAdStep;
  draftAdId: string | null;
  categoryId: string | null;
  locationId: string | null;
  details: PostAdDetails;
  images: Media[];

  setStep: (step: PostAdStep) => void;
  next: () => void;
  prev: () => void;
  setCategoryId: (id: string | null) => void;
  setLocationId: (id: string | null) => void;
  setDetails: (patch: Partial<PostAdDetails>) => void;
  setDraftAdId: (id: string | null) => void;
  setImages: (images: Media[]) => void;
  reset: () => void;
}

const INITIAL_DETAILS: PostAdDetails = {
  title: '',
  description: '',
  price: '',
  price_type: 'fixed',
  condition: null,
  custom_fields: {},
};

export const usePostAdStore = create<PostAdState>((set, get) => ({
  step: 1,
  draftAdId: null,
  categoryId: null,
  locationId: null,
  details: INITIAL_DETAILS,
  images: [],

  setStep: (step) => set({ step }),
  next: () => {
    const cur = get().step;
    set({ step: (Math.min(4, cur + 1) as PostAdStep) });
  },
  prev: () => {
    const cur = get().step;
    set({ step: (Math.max(1, cur - 1) as PostAdStep) });
  },
  setCategoryId: (categoryId) => set({ categoryId }),
  setLocationId: (locationId) => set({ locationId }),
  setDetails: (patch) =>
    set((state) => ({ details: { ...state.details, ...patch } })),
  setDraftAdId: (draftAdId) => set({ draftAdId }),
  setImages: (images) => set({ images }),
  reset: () =>
    set({
      step: 1,
      draftAdId: null,
      categoryId: null,
      locationId: null,
      details: INITIAL_DETAILS,
      images: [],
    }),
}));
