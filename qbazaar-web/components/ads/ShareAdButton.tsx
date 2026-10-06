'use client';

import { Share2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface ShareAdButtonProps {
  title: string;
  className?: string;
}

/**
 * Shares the current page with the system share sheet when the browser has
 * one, otherwise copies the link and says so.
 */
export function ShareAdButton({ title, className }: ShareAdButtonProps) {
  const share = () => {
    const url = window.location.href;
    if (typeof navigator.share === 'function') {
      void navigator.share({ title, url }).catch(() => undefined);
      return;
    }
    if (navigator.clipboard?.writeText) {
      void navigator.clipboard
        .writeText(url)
        .then(() => toast.success(t('ads.actions.share_copied')))
        .catch(() => undefined);
    }
  };

  return (
    <Button
      variant="outline"
      size="sm"
      onClick={share}
      className={cn('font-normal text-qb-ink-subtle hover:text-qb-ink', className)}
    >
      <Share2 aria-hidden />
      {t('ads.detail.share_ad')}
    </Button>
  );
}
