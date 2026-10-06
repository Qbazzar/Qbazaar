'use client';

/**
 * "Description" panel of the ad detail. Long texts are clamped to six lines
 * with a "show more / show less" toggle, which only appears when the text
 * actually overflows (measured after mount).
 */
import { useEffect, useId, useRef, useState } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { detailCard, detailCardMain, detailCardTitle } from './detail-card';

interface AdDescriptionProps {
  text: string;
  className?: string;
}

export function AdDescription({ text, className }: AdDescriptionProps) {
  const [expanded, setExpanded] = useState(false);
  const [overflows, setOverflows] = useState(false);
  const textRef = useRef<HTMLParagraphElement>(null);
  const titleId = useId();
  const textId = useId();

  useEffect(() => {
    const element = textRef.current;
    if (!element) return;
    setOverflows(element.scrollHeight > element.clientHeight + 1);
  }, [text]);

  return (
    <section aria-labelledby={titleId} className={cn(detailCard, detailCardMain, className)}>
      <h2 id={titleId} className={detailCardTitle}>
        {t('ads.detail.description')}
      </h2>
      <p
        id={textId}
        ref={textRef}
        className={cn(
          'mt-4 text-qb-micro leading-[22px] break-words whitespace-pre-line text-qb-ink-subtle qb-tablet:leading-6 qb-desktop:text-qb-body',
          !expanded && 'line-clamp-6',
        )}
      >
        {text}
      </p>
      {overflows ? (
        <button
          type="button"
          aria-expanded={expanded}
          aria-controls={textId}
          onClick={() => setExpanded((value) => !value)}
          className={cn('mt-2 rounded-qb-xs text-qb-caption font-medium text-qb-brand hover:text-qb-brand-active', focusRing)}
        >
          {expanded ? t('ads.description.show_less') : t('ads.description.show_more')}
        </button>
      ) : null}
    </section>
  );
}
