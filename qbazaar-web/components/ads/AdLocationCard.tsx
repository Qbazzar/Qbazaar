'use client';

import dynamic from 'next/dynamic';
import { MapPin } from 'lucide-react';
import { useId } from 'react';

import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { detailCard, detailCardMain, detailCardTitle } from './detail-card';

// Leaflet touches `window` at import time, so the read-only map loads client-only.
const MapView = dynamic(() => import('@/components/locations/MapView').then((module) => module.MapView), {
  ssr: false,
  loading: () => <div className="mt-4 h-[220px] rounded-qb-lg bg-qb-fill" />,
});

interface AdLocationCardProps {
  locationName: string;
  latitude: number | null;
  longitude: number | null;
}

/**
 * Where the item is: the area name and, when the seller pinned it, a map.
 * The reference has no such panel; it closes the content column so the
 * sidebar keeps product.html's two panels.
 */
export function AdLocationCard({ locationName, latitude, longitude }: AdLocationCardProps) {
  const titleId = useId();
  const hasPin = latitude != null && longitude != null;

  return (
    <section aria-labelledby={titleId} className={cn(detailCard, detailCardMain)}>
      <h2 id={titleId} className={detailCardTitle}>
        {t('locations.pick')}
      </h2>
      <p className="mt-3.5 flex items-center gap-2.5 text-qb-body-sm text-qb-ink-secondary">
        <Icon icon={MapPin} className="size-[18px] text-qb-brand" strokeWidth={1.6} />
        {locationName}
      </p>
      {hasPin ? (
        <MapView
          lat={latitude}
          lng={longitude}
          label={t('ads.detail.map_label', { place: locationName })}
          // `isolate` keeps Leaflet's z-indexed panes (400-1000) under the page's dialogs and sheets.
          className="isolate mt-4 h-[220px] w-full overflow-hidden rounded-qb-lg"
        />
      ) : null}
    </section>
  );
}
