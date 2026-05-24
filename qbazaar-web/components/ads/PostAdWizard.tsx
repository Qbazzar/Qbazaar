'use client';

/**
 * Multi-step "Post a new ad" wizard.
 *
 * Steps:
 *
 *   1. Category    — pick a leaf via the CategoryTree sidebar
 *   2. Details     — title, description, price + price_type, condition,
 *                    plus dynamic custom_fields driven by the category
 *   3. Location    — Qatar location picker
 *   4. Images      — ImageDropzone tied to a draft ad (created on step→4)
 *
 * State lives in `usePostAdStore` so the user can flip between steps without
 * losing data. The draft ad is persisted on the server as soon as we enter
 * step 4 — that way images have a parent to attach to.
 *
 * The "Publish" CTA on step 4 calls `publishAd` then redirects to the public
 * ad detail. If image upload fails, the ad stays in `draft` and the user can
 * retry.
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card } from '@/components/ui/card';
import { CategoryTree } from '@/components/categories/CategoryTree';
import { LocationPicker } from '@/components/locations/LocationPicker';
import { ImageDropzone } from '@/components/upload/ImageDropzone';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import {
  useCreateAdMutation,
  usePublishAdMutation,
  useUpdateAdMutation,
} from '@/lib/queries/ads';
import { usePostAdStore, type PostAdStep } from '@/store/post-ad';
import { findCategoryBySlug } from '@/store/categories';
import { findLocationBySlug } from '@/store/locations';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type {
  AdCondition,
  CategoryNode,
  CreateAdRequest,
  PriceType,
} from '@/lib/api/types';

const STEPS: PostAdStep[] = [1, 2, 3, 4];
const STEP_LABELS: Record<PostAdStep, string> = {
  1: 'ads.post.steps.category',
  2: 'ads.post.steps.details',
  3: 'ads.post.steps.location',
  4: 'ads.post.steps.images',
};

const PRICE_TYPES: PriceType[] = ['fixed', 'negotiable', 'free', 'contact'];
const CONDITIONS: AdCondition[] = ['new', 'like_new', 'used'];

export function PostAdWizard() {
  const router = useRouter();
  const locale = getLocale();
  const state = usePostAdStore();
  const { data: tree } = useCategoryTreeQuery();
  const { data: cities } = useQatarLocationsQuery();
  const createMutation = useCreateAdMutation();
  const updateMutation = useUpdateAdMutation();
  const publishMutation = usePublishAdMutation();
  const [busy, setBusy] = useState(false);

  // Reset the wizard whenever the user lands on the page fresh.
  // We deliberately *do not* reset on unmount — the user might briefly
  // navigate to a category page from inside step 1.
  useEffect(() => {
    return () => {
      // no-op for now
    };
  }, []);

  // ── Step gating ─────────────────────────────────────────────────────────

  const canAdvance = useMemo(() => {
    switch (state.step) {
      case 1:
        return Boolean(state.categoryId);
      case 2:
        return (
          state.details.title.trim().length >= 5 &&
          state.details.description.trim().length >= 20 &&
          (state.details.price_type === 'free' ||
            state.details.price_type === 'contact' ||
            state.details.price.trim().length > 0)
        );
      case 3:
        return Boolean(state.locationId);
      case 4:
        return state.images.length > 0;
    }
  }, [state.step, state.categoryId, state.locationId, state.details, state.images]);

  // ── Mutations ───────────────────────────────────────────────────────────

  /**
   * Either creates the draft ad (first time we leave step 3) or patches the
   * existing draft with the latest text/location/category values. Returns
   * the resulting ad id so step 4 can mount the dropzone against it.
   */
  const persistDraft = useCallback(async (): Promise<string | null> => {
    if (!state.categoryId || !state.locationId) return null;
    const payload: CreateAdRequest = {
      category_id: state.categoryId,
      location_id: state.locationId,
      title: state.details.title.trim(),
      description: state.details.description.trim(),
      price:
        state.details.price_type === 'free' ||
        state.details.price_type === 'contact'
          ? null
          : Number(state.details.price) || 0,
      price_type: state.details.price_type,
      condition: state.details.condition,
      custom_fields: state.details.custom_fields,
    };
    if (state.draftAdId) {
      const ad = await updateMutation.mutateAsync({
        id: state.draftAdId,
        payload,
      });
      state.setImages(ad.images ?? state.images);
      return ad.id;
    }
    const ad = await createMutation.mutateAsync(payload);
    state.setDraftAdId(ad.id);
    state.setImages(ad.images ?? []);
    return ad.id;
  }, [createMutation, state, updateMutation]);

  const onNext = useCallback(async () => {
    if (!canAdvance || busy) return;
    if (state.step === 3) {
      setBusy(true);
      try {
        const id = await persistDraft();
        if (!id) throw new Error('Draft creation failed');
        state.next();
      } catch (err) {
        toast.error(
          (err as { message?: string })?.message ??
            t('ads.errors.create_failed', 'تعذّر حفظ المسودة'),
        );
      } finally {
        setBusy(false);
      }
      return;
    }
    state.next();
  }, [canAdvance, busy, persistDraft, state]);

  const onPublish = useCallback(async () => {
    if (!state.draftAdId || busy) return;
    setBusy(true);
    try {
      const ad = await publishMutation.mutateAsync(state.draftAdId);
      toast.success(t('ads.post.published', 'تم نشر إعلانك'));
      state.reset();
      router.push(`/ads/${ad.id}`);
    } catch (err) {
      toast.error(
        (err as { message?: string })?.message ??
          t('ads.errors.ad_not_publishable', 'تعذّر النشر'),
      );
    } finally {
      setBusy(false);
    }
  }, [busy, publishMutation, router, state]);

  // ── Helpers for sub-views ──────────────────────────────────────────────

  const selectedCategory = useMemo(() => {
    if (!tree || !state.categoryId) return null;
    return findCategoryNode(tree, state.categoryId);
  }, [tree, state.categoryId]);

  // ── Render ─────────────────────────────────────────────────────────────

  return (
    <div className="space-y-8">
      {/* Stepper */}
      <ol className="grid grid-cols-4 gap-1">
        {STEPS.map((s, i) => (
          <li key={s} className="flex flex-col gap-1.5">
            <div
              className={cn(
                'h-1 rounded-full transition-colors',
                s <= state.step ? 'bg-coral' : 'bg-ink-200',
              )}
            />
            <span
              className={cn(
                'text-[11px] font-medium tracking-wider',
                s <= state.step ? 'text-ink-900' : 'text-ink-500',
              )}
            >
              0{i + 1} · {t(STEP_LABELS[s], '')}
            </span>
          </li>
        ))}
      </ol>

      <Card className="space-y-6 p-6 md:p-8">
        {state.step === 1 ? (
          <StepCategory
            tree={tree ?? []}
            activeId={state.categoryId}
            onPick={(id) => state.setCategoryId(id)}
          />
        ) : null}

        {state.step === 2 ? (
          <StepDetails category={selectedCategory} />
        ) : null}

        {state.step === 3 ? (
          <StepLocation
            cities={cities ?? []}
            value={state.locationId}
            onChange={state.setLocationId}
          />
        ) : null}

        {state.step === 4 && state.draftAdId ? (
          <StepImages
            adId={state.draftAdId}
            existing={state.images}
            onChange={state.setImages}
          />
        ) : null}
      </Card>

      {/* Nav */}
      <div className="flex items-center justify-between gap-3">
        <Button
          type="button"
          variant="outline"
          size="lg"
          onClick={() => state.prev()}
          disabled={state.step === 1 || busy}
        >
          <ChevronRight className="size-4" />
          {t('ads.post.back', 'السابق')}
        </Button>
        {state.step < 4 ? (
          <Button
            type="button"
            size="lg"
            onClick={() => void onNext()}
            disabled={!canAdvance || busy}
            className="bg-coral text-white hover:bg-coral/90"
          >
            {busy ? <Loader2 className="size-4 animate-spin" /> : null}
            {t('ads.post.next', 'التالي')}
            <ChevronLeft className="size-4" />
          </Button>
        ) : (
          <Button
            type="button"
            size="lg"
            onClick={() => void onPublish()}
            disabled={!canAdvance || busy}
            className="bg-coral text-white hover:bg-coral/90"
          >
            {busy ? <Loader2 className="size-4 animate-spin" /> : null}
            {t('ads.actions.publish', 'نشر الإعلان')}
          </Button>
        )}
      </div>
    </div>
  );
}

// ── Step 1: Category ──────────────────────────────────────────────────────

function StepCategory({
  tree,
  activeId,
  onPick,
}: {
  tree: CategoryNode[];
  activeId: string | null;
  onPick: (id: string) => void;
}) {
  const locale = getLocale();
  return (
    <div className="space-y-4">
      <StepTitle
        title={t('ads.post.steps.category', 'القسم المناسب')}
        subtitle={t(
          'ads.post.steps.category_subtitle',
          'اختر القسم الأقرب لإعلانك — يمكنك التعديل لاحقاً.',
        )}
      />
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {flatten(tree)
          .filter((n) => n.children.length === 0)
          .map((leaf) => (
            <button
              key={leaf.id}
              type="button"
              onClick={() => onPick(leaf.id)}
              className={cn(
                'rounded-xl border bg-white px-4 py-3 text-start text-sm transition-all',
                leaf.id === activeId
                  ? 'border-coral bg-coral/5 text-ink-900'
                  : 'border-ink-200 hover:border-coral/50',
              )}
            >
              <span className="text-ink-500 text-[10px] uppercase tracking-wider">
                {leaf.parent_id ? '' : ''}
              </span>
              <span className="text-ink-900 block font-medium">
                {localized(leaf.name, locale)}
              </span>
            </button>
          ))}
      </div>
    </div>
  );
}

function flatten(nodes: CategoryNode[]): CategoryNode[] {
  return nodes.flatMap((n) => [n, ...flatten(n.children)]);
}

function findCategoryNode(
  nodes: CategoryNode[],
  id: string,
): CategoryNode | null {
  for (const n of nodes) {
    if (n.id === id) return n;
    const hit = findCategoryNode(n.children, id);
    if (hit) return hit;
  }
  return null;
}

// ── Step 2: Details ───────────────────────────────────────────────────────

function StepDetails({ category }: { category: CategoryNode | null }) {
  const state = usePostAdStore();
  const locale = getLocale();
  const def = category?.custom_fields ?? [];

  return (
    <div className="space-y-5">
      <StepTitle
        title={t('ads.post.steps.details', 'تفاصيل الإعلان')}
        subtitle={t(
          'ads.post.steps.details_subtitle',
          'عنوان واضح ووصف صادق يجذبان مشترين أكثر بكثير.',
        )}
      />

      <Field label={t('ads.form.title', 'عنوان الإعلان')} required>
        <Input
          value={state.details.title}
          onChange={(e) => state.setDetails({ title: e.target.value })}
          maxLength={120}
          placeholder={t('ads.form.title_placeholder', 'مثال: آيفون 15 برو ماكس 256GB')}
        />
        <Hint>{state.details.title.length}/120</Hint>
      </Field>

      <Field label={t('ads.form.description', 'الوصف')} required>
        <Textarea
          rows={6}
          value={state.details.description}
          onChange={(e) => state.setDetails({ description: e.target.value })}
          maxLength={2000}
          placeholder={t(
            'ads.form.description_placeholder',
            'صف الحالة، الإكسسوارات، سبب البيع، طريقة التسليم...',
          )}
        />
        <Hint>{state.details.description.length}/2000</Hint>
      </Field>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t('ads.form.price_type', 'نوع السعر')}>
          <div className="flex flex-wrap gap-2">
            {PRICE_TYPES.map((pt) => (
              <button
                key={pt}
                type="button"
                onClick={() => state.setDetails({ price_type: pt })}
                className={cn(
                  'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                  state.details.price_type === pt
                    ? 'border-coral bg-coral/10 text-coral'
                    : 'border-ink-200 text-ink-700 hover:border-coral/50',
                )}
              >
                {t(`ads.price.${pt}`, pt)}
              </button>
            ))}
          </div>
        </Field>

        {state.details.price_type !== 'free' &&
        state.details.price_type !== 'contact' ? (
          <Field label={t('ads.form.price', 'السعر (ر.ق)')} required>
            <Input
              type="number"
              inputMode="numeric"
              min={0}
              value={state.details.price}
              onChange={(e) => state.setDetails({ price: e.target.value })}
              placeholder="0"
            />
          </Field>
        ) : null}
      </div>

      <Field label={t('ads.form.condition', 'الحالة')}>
        <div className="flex flex-wrap gap-2">
          {CONDITIONS.map((c) => (
            <button
              key={c}
              type="button"
              onClick={() =>
                state.setDetails({
                  condition: state.details.condition === c ? null : c,
                })
              }
              className={cn(
                'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                state.details.condition === c
                  ? 'border-coral bg-coral/10 text-coral'
                  : 'border-ink-200 text-ink-700 hover:border-coral/50',
              )}
            >
              {t(`ads.condition.${c}`, c)}
            </button>
          ))}
        </div>
      </Field>

      {def.length > 0 ? (
        <div className="space-y-4 rounded-xl bg-cream-50 p-4">
          <h4 className="text-ink-900 text-sm font-bold">
            {t('ads.form.custom_fields', 'تفاصيل إضافية')}
          </h4>
          {def.map((field) => {
            const value = state.details.custom_fields[field.key];
            const label = localized(field.label, locale);
            const onChange = (v: unknown) =>
              state.setDetails({
                custom_fields: {
                  ...state.details.custom_fields,
                  [field.key]: v,
                },
              });

            if (field.type === 'select' && field.options) {
              return (
                <Field key={field.key} label={label} required={field.required}>
                  <select
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    className="border-input bg-card text-ink-900 focus-visible:ring-ring/50 focus-visible:border-ring h-9 w-full rounded-lg border px-3 text-sm outline-none focus-visible:ring-3"
                  >
                    <option value="">—</option>
                    {field.options.map((opt) => (
                      <option key={opt} value={opt}>
                        {opt}
                      </option>
                    ))}
                  </select>
                </Field>
              );
            }

            if (field.type === 'boolean') {
              return (
                <Field key={field.key} label={label} required={field.required}>
                  <label className="text-ink-700 flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={Boolean(value)}
                      onChange={(e) => onChange(e.target.checked)}
                    />
                    {label}
                  </label>
                </Field>
              );
            }

            return (
              <Field key={field.key} label={label} required={field.required}>
                <Input
                  type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                  value={(value as string | number) ?? ''}
                  onChange={(e) =>
                    onChange(
                      field.type === 'number' && e.target.value
                        ? Number(e.target.value)
                        : e.target.value,
                    )
                  }
                />
              </Field>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}

// ── Step 3: Location ──────────────────────────────────────────────────────

function StepLocation({
  cities,
  value,
  onChange,
}: {
  cities: ReturnType<typeof useQatarLocationsQuery>['data'] extends infer T
    ? NonNullable<T>
    : never;
  value: string | null;
  onChange: (id: string | null) => void;
}) {
  // The picker is slug-driven, but the ad payload wants an id — translate
  // between the two using the cached tree.
  const slugToId = useMemo(() => {
    const map = new Map<string, string>();
    const walk = (nodes: typeof cities) => {
      for (const n of nodes) {
        map.set(n.slug, n.id);
        walk(n.children);
      }
    };
    walk(cities);
    return map;
  }, [cities]);

  const idToSlug = useMemo(() => {
    const map = new Map<string, string>();
    slugToId.forEach((id, slug) => map.set(id, slug));
    return map;
  }, [slugToId]);

  const slugValue = value ? idToSlug.get(value) ?? null : null;

  return (
    <div className="space-y-4">
      <StepTitle
        title={t('ads.post.steps.location', 'الموقع')}
        subtitle={t(
          'ads.post.steps.location_subtitle',
          'اختر المدينة والمنطقة لتظهر لجيرانك الأقرب.',
        )}
      />
      <LocationPicker
        value={slugValue}
        onChange={(slug) => onChange(slug ? slugToId.get(slug) ?? null : null)}
        className="max-w-md"
      />
    </div>
  );
}

// ── Step 4: Images ────────────────────────────────────────────────────────

function StepImages({
  adId,
  existing,
  onChange,
}: {
  adId: string;
  existing: import('@/lib/api/types').Media[];
  onChange: (m: import('@/lib/api/types').Media[]) => void;
}) {
  return (
    <div className="space-y-4">
      <StepTitle
        title={t('ads.post.steps.images', 'صور الإعلان')}
        subtitle={t(
          'ads.post.steps.images_subtitle',
          'حتى 20 صورة. الأولى ستكون الغلاف. اسحب لإعادة الترتيب.',
        )}
      />
      <ImageDropzone adId={adId} existing={existing} onChange={onChange} />
    </div>
  );
}

// ── Tiny shared bits ──────────────────────────────────────────────────────

function StepTitle({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div>
      <h3 className="font-display text-2xl italic md:text-3xl">{title}</h3>
      <p className="text-ink-500 mt-1 text-sm">{subtitle}</p>
    </div>
  );
}

function Field({
  label,
  required,
  children,
}: {
  label: string;
  required?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-ink-700 block text-xs font-medium">
        {label}
        {required ? <span className="text-coral ms-1">*</span> : null}
      </label>
      {children}
    </div>
  );
}

function Hint({ children }: { children: React.ReactNode }) {
  return <p className="text-ink-500 text-xs">{children}</p>;
}
