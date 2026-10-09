'use client';

import { useEffect, useState } from 'react';

import type { CategoryField, CategoryNode, Location } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { usePostAdStore } from '@/store/post-ad';

import { BasicInfoSection, DetailsSection, LocationSection, PriceSection } from './AdFormSections';
import { ProfileCard, type ProfileAction, type SellerSummary } from './ProfileCard';
import { HighlightSection } from './PromotionChoices';
import { TipsCard } from './TipsCard';
import type { PostAdAction } from './usePostAdActions';
import { VIEW_HEADING_ID } from './view-heading';

export interface AdFormViewProps {
  title: string;
  seller: SellerSummary;
  tree: readonly CategoryNode[];
  cities: readonly Location[];
  fields: readonly CategoryField[];
  /** A new, draft or rejected ad goes on to the publish step; a live ad is saved in place. */
  canPublish: boolean;
  running: PostAdAction | null;
  onSaveDraft: () => void;
  onPreview: () => void;
  onSubmit: () => void;
}

const ASIDE_GAP = 32;

/**
 * Where the sticky side column stops: below the site header when that header
 * sticks to the top (120 px on the design), near the top when the page has none.
 */
function useStickyTop(): number {
  const [top, setTop] = useState(ASIDE_GAP);
  useEffect(() => {
    const header = document.querySelector('header');
    const sticks = header && ['sticky', 'fixed'].includes(getComputedStyle(header).position);
    setTop((sticks ? header.getBoundingClientRect().height : 0) + ASIDE_GAP);
  }, []);
  return top;
}

/** add-ads.html: the form cards on the start side, profile and tips on the end side. */
export function AdFormView({ title, seller, tree, cities, fields, canPublish, running, onSaveDraft, onPreview, onSubmit }: AdFormViewProps) {
  const hasErrors = usePostAdStore((state) => Object.keys(state.errors).length > 0);
  const stickyTop = useStickyTop();
  const categoryChosen = usePostAdStore((state) => state.values.categoryId !== null);

  const action = (label: string, onClick: () => void, name: PostAdAction): ProfileAction => ({
    label,
    onClick,
    busy: running === name,
  });

  return (
    <>
      <h1 id={VIEW_HEADING_ID} tabIndex={-1} className="sr-only">
        {title}
      </h1>
      <p role="status" className="sr-only">
        {hasErrors ? t('post_ad.toast.fix_errors') : ''}
      </p>
      <div className="flex flex-wrap items-start gap-6">
        <div className="flex min-w-[min(300px,100%)] flex-[2_1_560px] flex-col gap-6">
          <BasicInfoSection tree={tree} />
          {categoryChosen ? <DetailsSection fields={fields} /> : null}
          <PriceSection />
          <LocationSection cities={cities} />
          {canPublish ? <HighlightSection /> : null}
        </div>
        <aside
          aria-label={t('post_ad.profile.aside_label')}
          style={{ top: stickyTop }}
          className="flex min-w-[min(280px,100%)] flex-[1_1_300px] flex-col gap-5 qb-desktop:sticky"
        >
          <ProfileCard
            seller={seller}
            disabled={running !== null}
            primary={
              canPublish
                ? action(t('post_ad.actions.add_ads'), onSubmit, 'submit')
                : action(t('post_ad.actions.save_changes'), onSubmit, 'submit')
            }
            secondary={canPublish ? action(t('post_ad.actions.save_draft'), onSaveDraft, 'draft') : undefined}
            preview={action(t('post_ad.actions.preview'), onPreview, 'preview')}
          />
          <TipsCard />
        </aside>
      </div>
    </>
  );
}
