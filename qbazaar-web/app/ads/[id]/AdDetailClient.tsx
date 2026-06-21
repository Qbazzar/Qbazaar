'use client';

/**
 * Ad detail client island — QBFront port (source: QBFront/detail.html).
 *
 * Layout: breadcrumb · `.detail-grid` (main gallery + meta + description +
 * spec grid + safety band + similar)  ·  `.seller-card` sidebar (CTA stack
 * + favorite/share + report).
 */
import Link from 'next/link';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ChevronLeft, Phone, Share2, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { AdDescription } from '@/components/ads/AdDescription';
import { AdGallery } from '@/components/ads/AdGallery';
import { AdSimilar } from '@/components/ads/AdSimilar';
import { AdStatusPill } from '@/components/ads/AdStatusPill';
import { CustomFieldsList } from '@/components/ads/CustomFieldsList';
import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { StartConversationButton } from '@/components/messaging/StartConversationButton';
import { ReportButton } from '@/components/reports/ReportButton';
import { useAdQuery } from '@/lib/queries/ads';
import { useTrackAdViewMutation } from '@/lib/queries/recently-viewed';
import { useAuth } from '@/hooks/useAuth';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

interface Props {
  id: string;
}

export function AdDetailClient({ id }: Props) {
  const locale = getLocale();
  const { data, isLoading, error } = useAdQuery(id);
  const trackView = useTrackAdViewMutation();

  useEffect(() => {
    if (!id) return;
    trackView.mutate(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  if (isLoading) {
    return (
      <main>
        <div className="container" style={{ paddingTop: 24, paddingBottom: 48 }}>
          <div
            className="animate-pulse"
            style={{ height: 18, width: 220, background: 'var(--cream-200)', borderRadius: 8, marginBottom: 24 }}
          />
          <div className="detail-grid">
            <div className="gallery animate-pulse" />
            <div
              className="card animate-pulse"
              style={{ minHeight: 300 }}
            />
          </div>
        </div>
      </main>
    );
  }

  if (error || !data) {
    const isNotFound =
      (error as { code?: string } | null)?.code === 'AD_NOT_FOUND';
    return (
      <main>
        <div className="container" style={{ padding: '80px 24px', textAlign: 'center' }}>
          <h1 className="empty-state__title">
            {isNotFound
              ? t('ads.errors.ad_not_found', 'لم نعثر على هذا الإعلان')
              : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
          </h1>
          <p className="empty-state__sub">
            {isNotFound
              ? t(
                  'ads.errors.ad_not_found_body',
                  'الإعلان ربما تم حذفه أو الرابط غير صحيح.',
                )
              : t('common.error', 'حدث خطأ، حاول مرة أخرى')}
          </p>
          <div style={{ marginTop: 24 }}>
            <Link href="/ads" className="btn btn--primary btn--pill">
              {t('ads.empty.go_browse', 'تصفّح الإعلانات')}
            </Link>
          </div>
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
  const { user, isAuthenticated, isHydrated } = useAuth();
  const categoryName = ad.category ? localized(ad.category.name, locale) : '';
  const locationName = ad.location ? localized(ad.location.name, locale) : '';

  // The owner can't contact themselves, and a sold ad takes no contact action —
  // hide the phone CTA in both cases (the message button handles its own state).
  const isOwner =
    isHydrated && isAuthenticated && user?.id === ad.user_id;
  const contactDisabled = isOwner || ad.status === 'sold';

  const onRevealPhone = () => setPhoneShown(true);

  const priceLabel =
    ad.price_type === 'free'
      ? t('ads.price.free', 'مجاناً')
      : ad.price_type === 'contact' || ad.price == null
        ? t('ads.price.contact', 'بالتواصل')
        : new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-US').format(ad.price);

  return (
    <main>
      <div className="container" style={{ paddingTop: 24, paddingBottom: 48 }}>
        {/* Breadcrumb */}
        <nav className="breadcrumbs">
          <Link href="/">{t('home.breadcrumb', 'الرئيسية')}</Link>
          {ad.category ? (
            <>
              <ChevronLeft className="size-3 rtl:rotate-180" aria-hidden />
              <Link href={`/c/${ad.category.slug}`}>{categoryName}</Link>
            </>
          ) : null}
          <ChevronLeft className="size-3 rtl:rotate-180" aria-hidden />
          <span className="breadcrumbs__current">{ad.title}</span>
        </nav>

        <div className="detail-grid">
          {/* MAIN */}
          <div>
            <AdGallery images={ad.images ?? []} alt={ad.title} />

            <header>
              {ad.status !== 'active' ? (
                <div style={{ marginTop: 16 }}>
                  <AdStatusPill status={ad.status} />
                </div>
              ) : null}
              <h1 className="detail-title">{ad.title}</h1>
              <div className="detail-price">
                {priceLabel}
                <span className="detail-price__currency">
                  {ad.price_type !== 'free' && ad.price != null
                    ? t('common.currency', 'ر.ق')
                    : null}
                </span>
              </div>
              <div className="detail-meta">
                {locationName ? (
                  <span className="detail-meta__item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
                      <path d="M12 22s7-7 7-13a7 7 0 1 0-14 0c0 6 7 13 7 13z" />
                      <circle cx="12" cy="9" r="2.5" />
                    </svg>
                    {locationName}
                  </span>
                ) : null}
                <span className="detail-meta__item">
                  {t('ads.detail.ad_id', { id: ad.id }, `رقم الإعلان ${ad.id}`)}
                </span>
                <span className="detail-meta__item">
                  {t(
                    'ads.detail.views',
                    { count: String(ad.views_count) },
                    `${ad.views_count} مشاهدة`,
                  )}
                </span>
              </div>
            </header>

            <section style={{ marginTop: 32 }}>
              <h2 className="section-header__title">
                {t('ads.detail.description', 'الوصف')}
              </h2>
              <AdDescription text={ad.description} className="mt-3" />
            </section>

            {ad.custom_fields && Object.keys(ad.custom_fields).length > 0 ? (
              <section>
                <h2 className="section-header__title" style={{ marginTop: 28, marginBottom: 12 }}>
                  {t('ads.detail.specs', 'التفاصيل')}
                </h2>
                <CustomFieldsList
                  values={ad.custom_fields}
                  category={ad.category}
                />
              </section>
            ) : null}

            {/* Safety strip */}
            <div className="safety-band">
              <span className="safety-band__icon">
                <ShieldCheck className="size-4" />
              </span>
              <div>
                <div className="safety-band__title">
                  {t('ads.detail.safety_title', 'ابقَ آمناً عند اللقاء')}
                </div>
                <div className="safety-band__body">
                  {t(
                    'ads.detail.safety_body',
                    'التق في مكان عام، افحص البضاعة قبل الدفع، ولا ترسل أموالاً لأشخاص لم تقابلهم.',
                  )}
                </div>
              </div>
            </div>

            <div style={{ marginTop: 40 }}>
              <AdSimilar adId={ad.id} />
            </div>
          </div>

          {/* SIDEBAR */}
          <aside>
            <div className="card seller-card">
              {ad.user ? (
                <Link href={`/u/${ad.user.id}`} className="seller-card__head">
                  <span className="seller-card__avatar">
                    {ad.user.full_name?.charAt(0) ?? 'Q'}
                  </span>
                  <div>
                    <div className="seller-card__name">{ad.user.full_name}</div>
                    <div className="seller-card__since">
                      {t(
                        'users.profile.joined',
                        { date: new Date(ad.user.joined_at).getFullYear().toString() },
                        `عضو منذ ${new Date(ad.user.joined_at).getFullYear()}`,
                      )}
                    </div>
                  </div>
                </Link>
              ) : null}

              <div className="seller-card__actions">
                <StartConversationButton
                  ad={{ id: ad.id, user_id: ad.user_id, status: ad.status }}
                />
                {!contactDisabled ? (
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
                ) : null}
                <div style={{ display: 'flex', gap: 8 }}>
                  <FavoriteButton
                    adId={ad.id}
                    size="md"
                    withLabel
                    className="h-10 flex-1 justify-center rounded-full bg-white text-ink-700 ring-ink-200 hover:text-coral"
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

              <div className="seller-card__foot">
                <ReportButton
                  target_type="ad"
                  target_id={ad.id}
                  variant="ghost"
                  className="text-ink-500 hover:text-coral inline-flex items-center gap-1.5 rounded-full"
                />
              </div>
            </div>

            {locationName ? (
              <div className="trust-card">
                <div className="trust-card__title">
                  {t('locations.pick', 'الموقع')}
                </div>
                <div className="trust-card__list">
                  <div>{locationName}</div>
                </div>
                <div className="map-box" style={{ marginTop: 12, height: 160 }} aria-hidden="true" />
              </div>
            ) : null}
          </aside>
        </div>
      </div>
    </main>
  );
}
