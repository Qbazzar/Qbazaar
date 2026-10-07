'use client';

import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';

export interface PagerProps {
  page: number;
  lastPage: number;
  onChange: (page: number) => void;
}

/** Previous / "Page 2 of 5" / next for the account lists, which keep the page in state. */
export function Pager({ page, lastPage, onChange }: PagerProps) {
  if (lastPage <= 1) return null;
  return (
    <nav aria-label={t('ui.pagination.label')} className="mt-8 flex items-center justify-center gap-3 font-qb">
      <Button
        variant="outline"
        size="icon"
        onClick={() => onChange(Math.max(1, page - 1))}
        disabled={page <= 1}
        aria-label={t('ui.pagination.previous')}
      >
        <Icon icon={ChevronLeft} flipInRtl />
      </Button>
      <span aria-live="polite" className="text-qb-body-sm text-qb-ink-body">
        {t('ads.list.page_of', { current: String(page), total: String(lastPage) })}
      </span>
      <Button
        variant="outline"
        size="icon"
        onClick={() => onChange(Math.min(lastPage, page + 1))}
        disabled={page >= lastPage}
        aria-label={t('ui.pagination.next')}
      >
        <Icon icon={ChevronRight} flipInRtl />
      </Button>
    </nav>
  );
}
