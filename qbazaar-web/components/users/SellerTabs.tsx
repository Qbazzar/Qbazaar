'use client';

/**
 * A private seller's "Ads / Reviews" tabs: the flat underline bar of
 * seller.html over seller-individual.html's listings column. The reviews
 * load only when their tab is opened.
 */
import type { ReactNode } from 'react';

import { Tab, TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { t } from '@/lib/i18n/messages';

interface SellerTabsProps {
  /** The "Active Listings" column. */
  ads: ReactNode;
  reviews: ReactNode;
}

export function SellerTabs({ ads, reviews }: SellerTabsProps) {
  return (
    <Tabs defaultValue="ads" variant="underline">
      {/* Each label sits in its own span, as the reference's interpolated text does. */}
      <TabList>
        <Tab value="ads">
          <span>{t('users.profile.tabs.ads')}</span>
        </Tab>
        <Tab value="reviews">
          <span>{t('reviews.title')}</span>
        </Tab>
      </TabList>
      <TabPanel value="ads">{ads}</TabPanel>
      <TabPanel value="reviews">{reviews}</TabPanel>
    </Tabs>
  );
}
