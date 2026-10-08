'use client';

import { useState, type ReactNode } from 'react';
import Link from 'next/link';
import { CalendarDays, Eye, Flag, Heart, LoaderCircle, MapPin, MessageSquare, Share2, Star } from 'lucide-react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { Button } from '@/components/design-system/Button';
import { cardVariants } from '@/components/design-system/Card';
import { Icon } from '@/components/design-system/Icon';
import type { CategoryField, CategoryNode, Location } from '@/lib/api/types';
import { intlLocale } from '@/lib/i18n/format';
import { getLocale, localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { formatAmount, formatDate } from '@/lib/post-ad/format';
import { hasPrice, selectedOptionLabel, type AdFormValues } from '@/lib/post-ad/form';
import { photoDisplayUrl } from '@/lib/post-ad/photos';
import { findPath } from '@/lib/post-ad/tree';
import { cn, formatRelativeTime } from '@/lib/utils';
import { usePostAdStore } from '@/store/post-ad';

import { SellerIdentity, type SellerSummary } from './ProfileCard';
import { PreviewGallery } from './PreviewGallery';
import type { PostAdAction } from './usePostAdActions';
import { VIEW_HEADING_ID } from './view-heading';
import { YourAdPanel } from './YourAdPanel';

export interface AdPreviewViewProps {
  variant: 'preview' | 'publish';
  seller: SellerSummary;
  tree: readonly CategoryNode[];
  cities: readonly Location[];
  fields: readonly CategoryField[];
  canPublish: boolean;
  running: PostAdAction | null;
  onEdit: () => void;
  onPublish: () => void;
  onConfirm: () => void;
  onDelete: () => void;
}

const card = cardVariants({ padding: 'none' });

/** How a buyer will see the ad (preview.html), and the publish step (publish.html). */
export function AdPreviewView({ variant, seller, tree, cities, fields, canPublish, running, onEdit, onPublish, onConfirm, onDelete }: AdPreviewViewProps) {
  const locale = getLocale();
  const values = usePostAdStore((state) => state.values);
  const photos = usePostAdStore((state) => state.photos);
  const ad = usePostAdStore((state) => state.ad);
  const categories = findPath(tree, values.categoryId);
  const place = findPath(cities, values.locationId);
  const title = values.title.trim();

  return (
    <div className="flex flex-col gap-[22px]">
      {variant === 'preview' ? (
        <div className="flex flex-col gap-4 rounded-qb-lg border border-qb-brand-tint bg-qb-brand-soft px-5 py-3.5 qb-tablet:flex-row qb-tablet:items-center qb-tablet:justify-between">
          <p className="flex items-center gap-2.5 text-qb-body-sm font-medium text-qb-brand-on-soft">
            <Icon icon={Eye} size="md" />
            {t('post_ad.preview.banner')}
          </p>
          <div className="flex shrink-0 gap-2.5">
            <Button variant="secondary" size="sm" onClick={onEdit} className="h-11 px-[22px]">
              {t('post_ad.actions.edit')}
            </Button>
            {canPublish ? (
              <Button size="sm" onClick={onPublish} className="h-11 px-[22px]">
                {t('post_ad.actions.publish')}
              </Button>
            ) : null}
          </div>
        </div>
      ) : null}

      {variant === 'publish' && ad ? <YourAdPanel ad={ad} running={running} onEdit={onEdit} onDelete={onDelete} /> : null}

      <Breadcrumb
        items={[
          { label: t('post_ad.breadcrumb.home'), href: '/' },
          ...categories.map((node) => ({ label: localized(node.name) })),
          { label: shortTitle(title) },
        ]}
      />

      <div className="flex flex-wrap items-start gap-6">
        <div className="flex min-w-[min(300px,100%)] flex-[2_1_560px] flex-col gap-6">
          <PreviewGallery title={title} urls={photos.map(photoDisplayUrl)} />

          <section className={cn(card, 'p-6')}>
            <div className="flex flex-col gap-3 qb-tablet:flex-row qb-tablet:items-start qb-tablet:justify-between qb-tablet:gap-5">
              <h1
                id={VIEW_HEADING_ID}
                tabIndex={-1}
                className="text-qb-h4 leading-[1.3] font-semibold tracking-normal text-qb-ink outline-none qb-desktop:text-qb-h2"
              >
                {title}
              </h1>
              <p className="shrink-0 text-[26px] font-semibold text-qb-brand">{priceText(values, locale)}</p>
            </div>
            <ul className="mt-5 flex flex-col gap-3.5 text-qb-body-sm text-qb-ink-secondary">
              <MetaRow icon={<Icon icon={MapPin} size="sm" className="size-[18px] text-qb-brand" />}>
                {[...place.map((node) => localized(node.name)), values.showFullAddress ? values.street.trim() : '']
                  .filter(Boolean)
                  .join(' – ')}
              </MetaRow>
              {ad ? (
                <MetaRow icon={<Icon icon={CalendarDays} size="sm" className="size-[18px] text-qb-brand" />}>
                  {t('post_ad.preview.created', { time: formatRelativeTime(ad.created_at, intlLocale(locale)) })}
                </MetaRow>
              ) : null}
              <MetaRow icon={<Icon icon={Eye} size="sm" className="size-[18px] text-qb-brand" />}>
                {tPlural('post_ad.preview.views', ad?.views_count ?? 0, locale)}
              </MetaRow>
            </ul>
          </section>

          <section aria-labelledby="post-ad-preview-description" className={cn(card, 'p-6')}>
            <h2 id="post-ad-preview-description" className="mb-3.5 text-qb-h5 font-semibold tracking-normal">
              {t('post_ad.preview.description')}
            </h2>
            <p dir="auto" className="text-qb-body-sm leading-[1.7] whitespace-pre-line text-qb-ink-faint">
              {values.description.trim()}
            </p>
          </section>

          <FeaturesCard values={values} fields={fields} />
        </div>

        <aside aria-label={t('post_ad.preview.aside_label')} className="flex min-w-[min(280px,100%)] flex-[1_1_300px] flex-col gap-5">
          <BuyerView seller={seller} />
          <section className={cn(card, 'p-[22px]')}>
            <dl className="text-qb-body">
              <div className="flex items-center justify-between gap-4 pb-3.5">
                <dt className="shrink-0 text-qb-ink-faint">{t('post_ad.preview.ad_id')}</dt>
                <dd className="min-w-0 truncate font-semibold" dir="ltr">
                  {ad?.id}
                </dd>
              </div>
              <div className="flex items-center justify-between gap-4 py-3.5">
                <dt className="shrink-0 text-qb-ink-faint">{t('post_ad.preview.created_on')}</dt>
                <dd className="font-semibold">{ad ? formatDate(ad.created_at, locale) : ''}</dd>
              </div>
            </dl>
            {/* Inert in the preview, but drawn as buyers will see them. */}
            <div className="flex gap-2.5 border-t border-qb-line pt-4">
              <Button variant="outline" size="sm" disabled className="min-w-0 flex-1 font-normal text-qb-ink-body disabled:opacity-100">
                <Icon icon={Share2} size="sm" />
                {t('post_ad.preview.share')}
              </Button>
              <Button variant="outline" size="sm" disabled className="min-w-0 flex-1 font-normal text-qb-ink-body disabled:opacity-100">
                <Icon icon={Flag} size="sm" />
                {t('post_ad.preview.report')}
              </Button>
            </div>
          </section>
          {variant === 'publish' ? <PublishCard running={running} onConfirm={onConfirm} /> : null}
        </aside>
      </div>
    </div>
  );
}

const BREADCRUMB_TITLE_LENGTH = 24;

/** The ad title cut for the breadcrumb, by characters so an emoji is never split. */
function shortTitle(title: string): string {
  const characters = Array.from(title);
  return characters.length > BREADCRUMB_TITLE_LENGTH ? `${characters.slice(0, BREADCRUMB_TITLE_LENGTH).join('')}…` : title;
}

function MetaRow({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <li className="flex items-start gap-2.5">
      <span className="mt-0.5 flex">{icon}</span>
      <span>{children}</span>
    </li>
  );
}

function priceText(values: AdFormValues, locale: Locale): string {
  if (values.priceType === 'free') return t('ads.price.free');
  if (!hasPrice(values.priceType) || !values.price.trim()) return t('ads.price.contact');
  return t('post_ad.price.amount', { amount: formatAmount(values.price.trim(), locale) });
}

const BUYER_NOTE_ID = 'post-ad-buyer-note';

/** Seller card as buyers see it; its buttons work once the ad is live. */
function BuyerView({ seller }: { seller: SellerSummary }) {
  return (
    <section className={cn(card, 'p-[22px]')}>
      <SellerIdentity seller={seller} variant="preview" />
      <p id={BUYER_NOTE_ID} className="sr-only">
        {t('post_ad.preview.buyer_note')}
      </p>
      <div className="mt-5 flex flex-col gap-3">
        <Button
          fullWidth
          disabled
          aria-describedby={BUYER_NOTE_ID}
          className="h-[46px] bg-qb-fill-strong text-qb-caption font-medium text-qb-surface disabled:opacity-100"
        >
          <Icon icon={MessageSquare} size="sm" />
          {t('post_ad.preview.send_message')}
        </Button>
        <Button variant="secondary" fullWidth disabled aria-describedby={BUYER_NOTE_ID} className="h-12">
          <Icon icon={Star} size="sm" />
          {t('post_ad.preview.follow')}
        </Button>
        <Button variant="ghost" fullWidth disabled aria-describedby={BUYER_NOTE_ID} className="h-[30px] font-semibold text-qb-brand">
          <Icon icon={Heart} size="sm" />
          {t('post_ad.preview.favorite')}
        </Button>
      </div>
    </section>
  );
}

function FeaturesCard({ values, fields }: { values: AdFormValues; fields: readonly CategoryField[] }) {
  const items: Array<{ label: string; value: string }> = [
    { label: t('post_ad.basic.ad_type'), value: t(`post_ad.ad_type.${values.adType}`) },
  ];
  if (values.condition) items.push({ label: t('post_ad.details.condition'), value: t(`ads.condition.${values.condition}`) });
  items.push({ label: t('post_ad.price.shipping'), value: shippingText(values) });
  for (const field of fields) {
    const value = values.customFields[field.key];
    if (value === undefined || value === '') continue;
    const text =
      typeof value === 'boolean'
        ? t(value ? 'post_ad.preview.yes' : 'post_ad.preview.no')
        : field.type === 'select'
          ? selectedOptionLabel(field, value)
          : value;
    items.push({ label: localized(field.label), value: text });
  }

  return (
    <section aria-labelledby="post-ad-preview-features" className={cn(card, 'p-6')}>
      <h2 id="post-ad-preview-features" className="mb-5 text-qb-h5 font-semibold tracking-normal">
        {t('post_ad.preview.features')}
      </h2>
      <dl className="[display:grid] grid-cols-1 gap-5 qb-tablet:grid-cols-3 qb-desktop:grid-cols-4">
        {items.map((item) => (
          <div key={item.label}>
            <dt className="text-qb-caption text-qb-ink-subtle">{item.label}</dt>
            <dd className="mt-1 text-qb-body-sm font-medium break-words text-qb-ink">{item.value}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

function shippingText(values: AdFormValues): string {
  if (values.shipping === 'pickup_only') return t('post_ad.price.shipping_pickup_only');
  const fee = values.shippingFee.trim();
  return fee
    ? t('post_ad.preview.delivery_fee', { amount: t('post_ad.price.amount', { amount: formatAmount(fee, getLocale()) }) })
    : t('post_ad.preview.free_delivery');
}

/** The publish step's call to action, with the listing terms the API requires. */
function PublishCard({ running, onConfirm }: { running: PostAdAction | null; onConfirm: () => void }) {
  const [accepted, setAccepted] = useState(false);
  const [showError, setShowError] = useState(false);
  const busy = running === 'publish';

  function confirm() {
    if (!accepted) {
      setShowError(true);
      document.getElementById('post-ad-terms')?.focus();
      return;
    }
    onConfirm();
  }

  return (
    <section aria-labelledby="post-ad-publish-title" className={cn(card, 'p-[22px]')}>
      <h2 id="post-ad-publish-title" className="text-qb-h5 font-semibold tracking-normal">
        {t('post_ad.publish.title')}
      </h2>
      <p className="mt-1.5 text-qb-caption text-qb-ink-secondary">{t('post_ad.publish.body')}</p>
      <label className="mt-4 flex cursor-pointer items-start gap-3 text-qb-caption text-qb-ink-body">
        <input
          id="post-ad-terms"
          type="checkbox"
          checked={accepted}
          aria-invalid={showError && !accepted ? true : undefined}
          aria-describedby={showError && !accepted ? 'post-ad-terms-error' : undefined}
          onChange={(event) => {
            setAccepted(event.target.checked);
            setShowError(false);
          }}
          className="mt-0.5 size-5 shrink-0 accent-qb-brand"
        />
        <span>
          {t('post_ad.publish.terms_before')}{' '}
          <Link href="/p/terms" target="_blank" rel="noopener noreferrer" className="font-medium text-qb-brand underline underline-offset-2">
            {t('post_ad.publish.terms_link')}
            <span className="sr-only"> {t('post_ad.publish.new_tab')}</span>
          </Link>
        </span>
      </label>
      {showError && !accepted ? (
        <p id="post-ad-terms-error" role="alert" className="mt-2 text-qb-label text-qb-danger">
          {t('post_ad.publish.terms_required')}
        </p>
      ) : null}
      <Button fullWidth disabled={running !== null} aria-busy={busy || undefined} onClick={confirm} className="mt-5 h-12">
        {busy ? <Icon icon={LoaderCircle} size="sm" className="animate-spin motion-reduce:animate-none" /> : null}
        {t('post_ad.actions.confirm_publish')}
      </Button>
    </section>
  );
}
