'use client';

/**
 * Ad detail client island.
 *
 * - Renders the gallery + meta + description + custom fields on the left.
 * - Sticky seller card with CTAs on the right (Save/Report wire up in
 *   Sprint 7+10, Call uses a hidden-until-clicked phone reveal).
 * - Breadcrumb pulls category + location names from the cached stores.
 * - 404 surface when the API returns AD_NOT_FOUND.
 */
import Link from 'next/link';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  ChevronLeft,
  Flag,
  Phone,
  Share2,
  ShieldCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { AdDescription } from '@/components/ads/AdDescription';
import { AdGallery } from '@/components/ads/AdGallery';
import { AdSimilar } from '@/components/ads/AdSimilar';
import { AdStatusPill } from '@/components/ads/AdStatusPill';
import { CustomFieldsList } from '@/components/ads/CustomFieldsList';
import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { PriceTag } from '@/components/ads/PriceTag';
import { StartConversationButton } from '@/components/messaging/StartConversationButton';
import { useAdQuery } from '@/lib/queries/ads';
import { useTrackAdViewMutation } from '@/lib/queries/recently-viewed';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

interface Props {
  id: string;
}

export function AdDetailClient({ id }: Props) {
  const locale = getLocale();
  const { data, isLoading, error } = useAdQuery(id);
  const trackView = useTrackAdViewMutation();

  // Fire a single silent tracking call per ad id. The mutation hook swallows
  // its own errors so a 401/network blip doesn't bubble up to the page.
  useEffect(() => {
    if (!id) return;
    trackView.mutate(id);
    // We intentionally exclude `trackView` from deps — mutating the mutation
    // object identity each render would cause an infinite tracking loop.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (isLoading) {
    return (
      <main className="bg-cream-50 min-h-svh">
        <div className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6">
          <div className="bg-cream-200 mb-6 h-4 w-48 animate-pulse rounded" />
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div className="bg-cream-200 h-96 animate-pulse rounded-2xl" />
            <div className="bg-cream-200 h-72 animate-pulse rounded-2xl" />
          </div>
        </div>
      </main>
    );
  }

  if (error || !data) {
    const isNotFound =
      (error as { code?: string } | null)?.code === 'AD_NOT_FOUND';
    return (
      <main className="bg-cream-50 grid min-h-svh place-items-center px-4">
        <div className="text-center">
          <h1 className="font-display text-4xl text-ink-900">
            {isNotFound
              ? t('ads.errors.ad_not_found', 'لم نعثر على هذا الإعلان')
              : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
          </h1>
          <p className="text-ink-500 mt-2 text-sm">
            {isNotFound
              ? t(
                  'ads.errors.ad_not_found_body',
                  'الإعلان ربما تم حذفه أو الرابط غير صحيح.',
                )
              : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
          </p>
          <Button asChild className="mt-6">
            <Link href="/ads">{t('ads.empty.go_browse', 'تصفّح الإعلانات')}</Link>
          </Button>
        </div>
      </main>
    );
  }

  return <AdDetail ad={data} locale={locale} />;
}

function AdDetail({
  ad,
  locale,
}: {
  ad: import('@/lib/api/types').Ad;
  locale: 'ar' | 'en';
}) {
  const [phoneShown, setPhoneShown] = useState(false);
  const categoryName = ad.category ? localized(ad.category.name, locale) : '';
  const locationName = ad.location ? localized(ad.location.name, locale) : '';

  // The seller's full phone never lives on the public ad payload — Sprint 9
  // adds a dedicated reveal endpoint. For now the button is a placeholder.
  const onRevealPhone = () => setPhoneShown(true);

  return (
    <main className="bg-cream-50 min-h-svh">
      <div className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
        {/* Breadcrumb */}
        <nav className="text-ink-500 mb-6 flex flex-wrap items-center gap-2 text-xs">
          <Link href="/" className="hover:text-coral">
            {t('home.breadcrumb', 'الرئيسية')}
          </Link>
          {ad.category ? (
            <>
              <ChevronLeft className="size-3 rtl:rotate-180" />
              <Link
                href={`/c/${ad.category.slug}`}
                className="hover:text-coral"
              >
                {categoryName}
              </Link>
            </>
          ) : null}
          <ChevronLeft className="size-3 rtl:rotate-180" />
          <span className="text-ink-900 max-w-xs truncate">{ad.title}</span>
        </nav>

        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
          {/* MAIN */}
          <div className="space-y-6">
            <AdGallery images={ad.images ?? []} alt={ad.title} />

            <header>
              {ad.status !== 'active' ? (
                <div className="mb-3">
                  <AdStatusPill status={ad.status} />
                </div>
              ) : null}
              <h1 className="font-display text-3xl text-ink-900 md:text-4xl">
                {ad.title}
              </h1>
              <div className="mt-4">
                <PriceTag
                  price={ad.price}
                  priceType={ad.price_type}
                  size="lg"
                />
              </div>
              <div className="text-ink-700 mt-4 flex flex-wrap items-center gap-4 text-sm">
                {locationName ? <span>{locationName}</span> : null}
                <span aria-hidden="true">·</span>
                <span>
                  {t('ads.detail.ad_id', { id: ad.id }, `رقم الإعلان ${ad.id}`)}
                </span>
                <span aria-hidden="true">·</span>
                <span>
                  {t(
                    'ads.detail.views',
                    { count: String(ad.views_count) },
                    `${ad.views_count} مشاهدة`,
                  )}
                </span>
              </div>
            </header>

            <section>
              <h2 className="font-display text-2xl text-ink-900">
                {t('ads.detail.description', 'الوصف')}
              </h2>
              <AdDescription text={ad.description} className="mt-3" />
            </section>

            {ad.custom_fields && Object.keys(ad.custom_fields).length > 0 ? (
              <section>
                <h2 className="font-display mb-3 text-2xl text-ink-900">
                  {t('ads.detail.specs', 'التفاصيل')}
                </h2>
                <CustomFieldsList
                  values={ad.custom_fields}
                  category={ad.category}
                />
              </section>
            ) : null}

            {/* Safety strip — Bazzar mockup parity */}
            <div className="bg-cream-200 flex items-start gap-3 rounded-xl p-4">
              <span className="bg-white text-terracotta flex size-9 shrink-0 items-center justify-center rounded-full">
                <ShieldCheck className="size-4" />
              </span>
              <div>
                <p className="text-ink-900 text-sm font-bold">
                  {t('ads.detail.safety_title', 'ابقَ آمناً عند اللقاء')}
                </p>
                <p className="text-ink-700 mt-1 text-xs leading-relaxed">
                  {t(
                    'ads.detail.safety_body',
                    'التق في مكان عام، افحص البضاعة قبل الدفع، ولا ترسل أموالاً لأشخاص لم تقابلهم.',
                  )}
                </p>
              </div>
            </div>

            <AdSimilar adId={ad.id} />
          </div>

          {/* SIDEBAR */}
          <aside className="lg:sticky lg:top-6 lg:self-start">
            <Card className="p-5">
              {ad.user ? (
                <Link
                  href={`/u/${ad.user.id}`}
                  className="mb-4 flex items-center gap-3"
                >
                  <Avatar className="size-12">
                    {ad.user.avatar_url ? (
                      <AvatarImage src={ad.user.avatar_url} alt={ad.user.full_name} />
                    ) : null}
                    <AvatarFallback>
                      {ad.user.full_name.charAt(0)}
                    </AvatarFallback>
                  </Avatar>
                  <div>
                    <p className="text-ink-900 text-sm font-bold">
                      {ad.user.full_name}
                    </p>
                    <p className="text-ink-500 text-xs">
                      {t(
                        'users.profile.joined',
                        { date: new Date(ad.user.joined_at).getFullYear().toString() },
                        `عضو منذ ${new Date(ad.user.joined_at).getFullYear()}`,
                      )}
                    </p>
                  </div>
                </Link>
              ) : null}

              <div className="flex flex-col gap-2">
                <StartConversationButton ad={{ id: ad.id, user_id: ad.user_id }} />
                <Button
                  type="button"
                  size="lg"
                  variant="outline"
                  className="rounded-full"
                  onClick={onRevealPhone}
                >
                  <Phone className="size-4" />
                  {phoneShown
                    ? t('ads.actions.call_revealed', 'اضغط للاتصال')
                    : t('ads.actions.call', 'إظهار الرقم')}
                </Button>
                <div className="flex items-stretch gap-2">
                  <FavoriteButton
                    adId={ad.id}
                    size="md"
                    withLabel
                    className="h-9 flex-1 justify-center rounded-full bg-white text-ink-700 ring-ink-200 hover:text-coral"
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="lg"
                    className="flex-1 rounded-full"
                    onClick={() => {
                      const url =
                        typeof window !== 'undefined'
                          ? window.location.href
                          : '';
                      if (
                        typeof navigator !== 'undefined' &&
                        typeof navigator.share === 'function'
                      ) {
                        void navigator
                          .share({ title: ad.title, url })
                          .catch(() => undefined);
                        return;
                      }
                      // Clipboard fallback for desktop browsers without
                      // navigator.share — surface a toast so the user knows
                      // something happened.
                      if (
                        typeof navigator !== 'undefined' &&
                        navigator.clipboard?.writeText
                      ) {
                        void navigator.clipboard
                          .writeText(url)
                          .then(() =>
                            toast.success(
                              t('ads.actions.share_copied', 'تم نسخ الرابط'),
                            ),
                          )
                          .catch(() => undefined);
                      }
                    }}
                  >
                    <Share2 className="size-4" />
                    {t('ads.actions.share', 'مشاركة')}
                  </Button>
                </div>
              </div>

              <div className="border-ink-200 mt-4 flex items-center justify-between border-t pt-3 text-xs">
                <button
                  type="button"
                  className="text-ink-500 inline-flex items-center gap-1.5"
                  title={t('ads.actions.report_soon', 'الإبلاغ متاح قريباً')}
                >
                  <Flag className="size-3" />
                  {t('ads.actions.report', 'إبلاغ')}
                </button>
              </div>
            </Card>

            {locationName ? (
              <div className="border-ink-200 mt-4 rounded-xl border bg-card p-4">
                <p className="text-ink-500 text-[11px] font-bold uppercase tracking-wider">
                  {t('locations.pick', 'الموقع')}
                </p>
                <p className="text-ink-900 mt-1 text-sm font-medium">
                  {locationName}
                </p>
                {/* Map placeholder — real map lands in Sprint 11 */}
                <div className="bg-cream-200 mt-3 h-28 rounded-lg" aria-hidden="true" />
              </div>
            ) : null}
          </aside>
        </div>
      </div>
    </main>
  );
}
