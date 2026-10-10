'use client';

import { useState, type ReactNode } from 'react';
import { Eye, Heart, LoaderCircle, PenLine, Trash2, type LucideIcon } from 'lucide-react';

import { ModalActions } from '@/components/account/ModalActions';
import { Button } from '@/components/design-system/Button';
import { cardVariants } from '@/components/design-system/Card';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import type { Ad } from '@/lib/api/types';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { formatDate } from '@/lib/post-ad/format';
import { cn } from '@/lib/utils';

import { FeaturedAdTable, usePromotionChoices } from './PromotionChoices';
import type { PostAdAction } from './usePostAdActions';

export interface YourAdPanelProps {
  ad: Ad;
  running: PostAdAction | null;
  onEdit: () => void;
  onDelete: () => void;
}

/**
 * The card at the top of publish.html (355:7297, 532:23641, 638:36533): "Your
 * Ad" with the ad's visits, wishlist count and publish date, Edit and Delete,
 * beside the "Featured Ad" promotion table (under it below 1001 px). On
 * phones the actions are a row of grey icon buttons at the bottom of the
 * card. The design's Report and Reserved are left out: a seller doesn't
 * report their own ad, and only a live ad can be reserved.
 */
export function YourAdPanel({ ad, running, onEdit, onDelete }: YourAdPanelProps) {
  const locale = getLocale();
  const [confirmDelete, setConfirmDelete] = useState(false);
  const { offers, chosen, toggle } = usePromotionChoices();
  const deleting = running === 'delete';
  const withPromotions = offers.length > 0;
  const actions = { disabled: running !== null, onEdit, onDelete: () => setConfirmDelete(true) };

  return (
    <section
      aria-labelledby="post-ad-your-ad"
      className={cn(cardVariants({ padding: 'none' }), 'flex flex-col text-qb-ink qb-desktop:flex-row', !withPromotions && 'qb-desktop:w-[390px]')}
    >
      <div className={cn('p-[26px]', withPromotions && 'qb-desktop:w-[390px] qb-desktop:shrink-0 qb-desktop:border-e qb-desktop:border-qb-line')}>
        <h2 id="post-ad-your-ad" className="flex items-center gap-2.5 text-qb-body-lg font-semibold tracking-normal">
          <span aria-hidden="true" className="h-5 w-1 rounded-[2px] bg-qb-brand" />
          {t('post_ad.your_ad.title')}
        </h2>
        <dl className="mt-[22px] flex flex-col gap-4 border-b border-qb-line pb-[18px] text-qb-body-sm">
          <SummaryRow label={t('post_ad.your_ad.visits')}>
            <Icon icon={Eye} size="sm" />
            {formatNumber(ad.views_count, locale)}
          </SummaryRow>
          <SummaryRow label={t('post_ad.your_ad.wishlist')}>
            <Icon icon={Heart} size="sm" />
            {formatNumber(ad.favorites_count, locale)}
          </SummaryRow>
          <SummaryRow label={t('post_ad.your_ad.published')}>{formatDate(ad.published_at ?? new Date().toISOString(), locale)}</SummaryRow>
        </dl>
        <PanelActions layout="rows" {...actions} />
      </div>
      {withPromotions ? <FeaturedAdTable offers={offers} chosen={chosen} onToggle={toggle} /> : null}
      <PanelActions layout="icons" {...actions} />

      <Modal
        open={confirmDelete}
        onOpenChange={(open) => {
          if (!open && !deleting) setConfirmDelete(false);
        }}
        title={t('ads.actions.delete_confirm_title')}
        description={t('ads.actions.delete_confirm_body')}
      >
        <ModalActions className="mt-2">
          <Button size="sm" disabled={running !== null} aria-busy={deleting || undefined} onClick={onDelete}>
            {deleting ? <Icon icon={LoaderCircle} size="sm" className="animate-spin motion-reduce:animate-none" /> : null}
            {t('common.delete')}
          </Button>
          <Button variant="muted" size="sm" disabled={deleting} onClick={() => setConfirmDelete(false)}>
            {t('common.cancel')}
          </Button>
        </ModalActions>
      </Modal>
    </section>
  );
}

function SummaryRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <dt className="text-qb-ink-faint">{label}</dt>
      <dd className="flex items-center gap-1.5 font-semibold">{children}</dd>
    </div>
  );
}

const ACTION_LAYOUTS = {
  /** Text rows under the figures, from the tablet up (355:7297, 532:23641). */
  rows: {
    list: 'mt-[18px] flex flex-col gap-4 text-qb-body-sm text-qb-ink-secondary max-qb-tablet:hidden',
    button: 'flex items-center gap-3 rounded-qb-xs hover:text-qb-ink [&_svg]:size-[17px] [&_svg]:text-qb-ink-subtle',
    label: undefined,
  },
  /** 40 px grey icon buttons, four to a row, at the bottom of the phone card (638:36533). */
  icons: {
    list: '[display:grid] grid-cols-4 gap-2 px-[26px] pb-[26px] qb-tablet:hidden',
    button:
      'flex h-10 w-full items-center justify-center rounded-qb-sm bg-qb-fill text-(--color-qb-icon-action) transition-colors hover:bg-qb-line [&_svg]:size-6',
    label: 'sr-only',
  },
} as const;

function PanelActions({
  layout,
  disabled,
  onEdit,
  onDelete,
}: {
  layout: keyof typeof ACTION_LAYOUTS;
  disabled: boolean;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const styles = ACTION_LAYOUTS[layout];
  const items: Array<{ icon: LucideIcon; label: string; onClick: () => void }> = [
    { icon: PenLine, label: t('post_ad.actions.edit'), onClick: onEdit },
    { icon: Trash2, label: t('common.delete'), onClick: onDelete },
  ];
  return (
    <ul className={styles.list}>
      {items.map((item) => (
        <li key={item.label}>
          <button
            type="button"
            disabled={disabled}
            onClick={item.onClick}
            className={cn(styles.button, 'disabled:cursor-not-allowed disabled:opacity-50', focusRing)}
          >
            <Icon icon={item.icon} />
            <span className={styles.label}>{item.label}</span>
          </button>
        </li>
      ))}
    </ul>
  );
}
