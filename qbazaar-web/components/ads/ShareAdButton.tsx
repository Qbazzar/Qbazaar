'use client';

import { useEffect, useState } from 'react';
import { Check, Share2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** How long the button shows that the link was copied (190:9777). */
const COPIED_MS = 3000;

interface ShareAdButtonProps {
  title: string;
  className?: string;
}

/**
 * Shares the current page with the system share sheet when the browser has
 * one, otherwise copies the link and turns green for a moment to say so.
 */
export function ShareAdButton({ title, className }: ShareAdButtonProps) {
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (!copied) return;
    const timer = window.setTimeout(() => setCopied(false), COPIED_MS);
    return () => window.clearTimeout(timer);
  }, [copied]);

  const share = () => {
    const url = window.location.href;
    if (typeof navigator.share === 'function') {
      void navigator.share({ title, url }).catch(() => undefined);
      return;
    }
    if (navigator.clipboard?.writeText) {
      void navigator.clipboard
        .writeText(url)
        .then(() => setCopied(true))
        .catch(() => undefined);
    }
  };

  return (
    <>
      <Button
        variant="outline"
        size="sm"
        onClick={share}
        className={cn(
          'font-normal focus-visible:outline-solid',
          copied
            ? 'border-qb-success bg-qb-success-soft text-qb-success hover:bg-qb-success-soft'
            : 'text-qb-ink-subtle hover:text-qb-ink',
          className,
        )}
      >
        {copied ? <Check aria-hidden /> : <Share2 aria-hidden />}
        {copied ? t('ads.actions.share_copied') : t('ads.detail.share_ad')}
      </Button>
      <span role="status" className="sr-only">
        {copied ? t('ads.actions.share_copied') : ''}
      </span>
    </>
  );
}
