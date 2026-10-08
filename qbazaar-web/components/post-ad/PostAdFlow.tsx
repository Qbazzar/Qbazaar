'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { CloudOff } from 'lucide-react';

import { Breadcrumb, type BreadcrumbItem } from '@/components/design-system/Breadcrumb';
import { Button } from '@/components/design-system/Button';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import type { Ad, CategoryNode, Location, User } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { isPublishable } from '@/lib/post-ad/form';
import { fieldsOf } from '@/lib/post-ad/tree';
import { useAccountSummaryQuery } from '@/lib/queries/account';
import { adKeys } from '@/lib/queries/ads';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { usePostAdStore } from '@/store/post-ad';

import { AdFormView } from './AdFormView';
import { AdPreviewView } from './AdPreviewView';
import { PostAdLoading, formBreadcrumbClass } from './PostAdPage';
import type { SellerSummary } from './ProfileCard';
import { ReviewState } from './ReviewState';
import { beginPostAdSession, createSession, photoQueue } from './session';
import { usePostAdActions } from './usePostAdActions';
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
  const treeQuery = useCategoryTreeQuery();
  const citiesQuery = useQatarLocationsQuery();
  const { data: summary } = useAccountSummaryQuery();
  const tree = treeQuery.data ?? NO_CATEGORIES;
  const cities = citiesQuery.data ?? NO_LOCATIONS;
  const fields = useMemo(() => fieldsOf(tree, categoryId), [tree, categoryId]);
  const actions = usePostAdActions(fields);
  const adId = ad?.id;

  useEffect(() => {
    // The ad prop is read once per ad: refetches of it must not wipe the seller's edits.
    beginPostAdSession(adId ? `edit:${adId}` : createSession(user.id), ad ?? null);
    setReady(true);
  }, [adId, user.id]);

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

  // The custom fields and the areas come from these, so the form waits for both.
  if (!treeQuery.data || !citiesQuery.data) {
    if (!treeQuery.isError && !citiesQuery.isError) return <PostAdLoading />;
    return (
      <StatePanel
        icon={<StateIcon icon={CloudOff} tone="muted" />}
        title={t('post_ad.load_failed')}
        description={t('common.error')}
        action={
          <Button
            disabled={treeQuery.isFetching || citiesQuery.isFetching}
            onClick={() => {
              if (treeQuery.isError) void treeQuery.refetch();
              if (citiesQuery.isError) void citiesQuery.refetch();
            }}
          >
            {t('common.retry')}
          </Button>
        }
      />
    );
  }
  if (!ready) return <PostAdLoading />;

  const seller: SellerSummary = {
    name: user.full_name,
    avatarUrl: user.avatar_thumb_url ?? user.avatar_url,
    accountType: user.account_type,
    verified: user.phone_verified,
    adsCount: summary?.ads_by_status.active,
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
          beginPostAdSession(createSession(user.id), null);
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
        onDelete={() => void actions.remove()}
      />
    );
  }

  return (
    <>
      <Breadcrumb items={breadcrumb} className={formBreadcrumbClass} />
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
