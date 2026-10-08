'use client';

/**
 * "Description" panel of the ad detail. Long texts are clamped to six lines
 * with a "show more / show less" toggle, which only appears when the text
 * actually overflows (measured after mount). While clamped, the toggle sits
 * over the end of the last line, so its arrival moves nothing on the page.
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

const lineHeight = 'leading-[22px] qb-tablet:leading-6';

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
      <div className="relative mt-4">
        <p
          id={textId}
          ref={textRef}
          dir="auto"
          className={cn(
            'text-qb-micro break-words whitespace-pre-line text-qb-ink-subtle qb-desktop:text-qb-body',
            lineHeight,
            !expanded && 'line-clamp-6',
          )}
        >
          {text}
        </p>
        {overflows ? (
          // One button in both states, so it keeps the keyboard focus when it moves under the text.
          <button
            type="button"
            aria-expanded={expanded}
            aria-controls={textId}
            onClick={() => setExpanded((value) => !value)}
            className={cn(
              'rounded-qb-xs text-qb-caption font-medium text-qb-brand hover:text-qb-brand-active',
              lineHeight,
              focusRing,
              expanded
                ? 'mt-2'
                : 'absolute end-0 bottom-0 from-qb-surface from-60% to-transparent ps-10 ltr:bg-linear-to-l rtl:bg-linear-to-r',
            )}
          >
            {expanded ? t('ads.description.show_less') : t('ads.description.show_more')}
          </button>
        ) : null}
      </div>
    </section>
  );
}
