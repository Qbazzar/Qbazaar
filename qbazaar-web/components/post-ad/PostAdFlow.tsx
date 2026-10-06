'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';

import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import type { Ad, CategoryNode, Location, User } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { fieldsOf } from '@/lib/post-ad/tree';
import { adKeys, useMyAdsQuery } from '@/lib/queries/ads';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { usePostAdStore } from '@/store/post-ad';

import { AdFormView } from './AdFormView';
import { AdPreviewView } from './AdPreviewView';
import type { SellerSummary } from './ProfileCard';
import { ReviewState } from './ReviewState';
import { beginPostAdSession, photoQueue } from './session';
import { isPublishable, usePostAdActions } from './usePostAdActions';
import { VIEW_HEADING_ID } from './view-heading';

export interface PostAdFlowProps {
  user: User;
  /** The ad being edited; omit to post a new one. */
  ad?: Ad;
  breadcrumb: BreadcrumbItem[];
}

const NO_CATEGORIES: CategoryNode[] = [];
const NO_LOCATIONS: Location[] = [];

/**
 * The post-ad and edit-ad flow: the form (add-ads.html), the preview
 * (preview.html), the publish step (publish.html) and the "under review"
 * confirmation, all on one route.
 */
export function PostAdFlow({ user, ad, breadcrumb }: PostAdFlowProps) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [ready, setReady] = useState(false);
  const view = usePostAdStore((state) => state.view);
  const savedAd = usePostAdStore((state) => state.ad);
  const categoryId = usePostAdStore((state) => state.values.categoryId);
  const { data: tree = NO_CATEGORIES } = useCategoryTreeQuery();
  const { data: cities = NO_LOCATIONS } = useQatarLocationsQuery();
  const { data: liveAds } = useMyAdsQuery({ status: 'active' });
  const fields = useMemo(() => fieldsOf(tree, categoryId), [tree, categoryId]);
  const actions = usePostAdActions(fields);
  const adId = ad?.id;

  useEffect(() => {
    // The ad prop is read once per ad: refetches of it must not wipe the seller's edits.
    beginPostAdSession(adId ? `edit:${adId}` : 'create', ad ?? null);
    setReady(true);
  }, [adId]);

  useEffect(
    () =>
      photoQueue.onImagesChanged((changedAdId) => {
        void queryClient.invalidateQueries({ queryKey: adKeys.detail(changedAdId) });
        void queryClient.invalidateQueries({ queryKey: adKeys.myLists() });
      }),
    [queryClient],
  );

  // A new step starts at its top, with focus on its heading.
  const shownView = useRef(view);
  useEffect(() => {
    if (shownView.current === view) return;
    shownView.current = view;
    window.scrollTo({ top: 0 });
    document.getElementById(VIEW_HEADING_ID)?.focus({ preventScroll: true });
  }, [view]);

  if (!ready) return <div aria-busy="true" className="min-h-[60vh]" />;

  const seller: SellerSummary = {
    name: user.full_name,
    avatarUrl: user.avatar_thumb_url ?? user.avatar_url,
    accountType: user.account_type,
    verified: user.phone_verified,
    adsCount: liveAds?.meta.total,
    memberSince: user.created_at,
  };
  const canPublish = isPublishable(savedAd);

  if (view === 'done') {
    return (
      <ReviewState
        ad={savedAd}
        onPostAnother={() => {
          if (adId) {
            router.push('/post-ad');
            return;
          }
          beginPostAdSession('create', null);
        }}
      />
    );
  }

  if (view === 'preview' || view === 'publish') {
    return (
      <AdPreviewView
        variant={view}
        seller={seller}
        tree={tree}
        cities={cities}
        fields={fields}
        canPublish={canPublish}
        running={actions.running}
        onEdit={actions.edit}
        onPublish={() => usePostAdStore.getState().setView('publish')}
        onConfirm={() => void actions.publish()}
      />
    );
  }

  return (
    <>
      <Breadcrumb items={breadcrumb} className="mb-6 max-qb-tablet:text-qb-body qb-tablet:mb-8" />
      <AdFormView
        title={adId ? t('post_ad.edit_title') : t('post_ad.page_title')}
        seller={seller}
        tree={tree}
        cities={cities}
        fields={fields}
        canPublish={canPublish}
        running={actions.running}
        onSaveDraft={() => void actions.saveDraft()}
        onPreview={() => void actions.preview()}
        onSubmit={() => void actions.submit()}
      />
    </>
  );
}
