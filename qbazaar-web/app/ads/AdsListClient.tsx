'use client';

/** `/ads` — every listed ad, filtered by `?category=`, `?location=` and price (category.html, 69:467). */
import { AdsListing } from '@/components/catalog/AdsListing';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

export function AdsListClient() {
  return (
    <AdsListing
      heading={(selected) => {
        const allAds = t('ads.list.title', 'كل الإعلانات');
        const home = { label: t('home.breadcrumb', 'الرئيسية'), href: '/' };
        if (!selected) return { title: allAds, breadcrumb: [home, { label: allAds }] };
        const name = localized(selected.name);
        return { title: name, breadcrumb: [home, { label: allAds, href: '/ads' }, { label: name }] };
      }}
    />
  );
}
