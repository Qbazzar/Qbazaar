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
      <TabList>
        <Tab value="ads">{t('users.profile.tabs.ads')}</Tab>
        <Tab value="reviews">{t('reviews.title')}</Tab>
      </TabList>
      <TabPanel value="ads">{ads}</TabPanel>
      <TabPanel value="reviews">{reviews}</TabPanel>
    </Tabs>
  );
}
