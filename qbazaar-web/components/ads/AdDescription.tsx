'use client';

/**
 * Collapsible long-form ad description.
 *
 * Renders the first ~6 lines clamped, with a "show more / show less" toggle
 * when the content actually overflows. The toggle is hidden if the
 * description is short enough to fit unclamped — measured client-side after
 * mount with a ResizeObserver-free trick (compare scrollHeight to clientHeight).
 */
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface Props {
  text: string;
  className?: string;
}

export function AdDescription({ text, className }: Props) {
  const [expanded, setExpanded] = useState(false);
  const [overflows, setOverflows] = useState(false);
  const ref = useRef<HTMLParagraphElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    setOverflows(el.scrollHeight > el.clientHeight + 1);
  }, [text]);

  return (
    <div className={cn('space-y-2', className)}>
      <p
        ref={ref}
        className={cn(
          'text-ink-700 whitespace-pre-line text-[15px] leading-relaxed',
          !expanded && 'line-clamp-6',
        )}
      >
        {text}
      </p>
      {overflows ? (
        <Button
          type="button"
          variant="link"
          size="sm"
          onClick={() => setExpanded((v) => !v)}
          className="text-coral px-0"
        >
          {expanded
            ? t('ads.description.show_less', 'عرض أقل')
            : t('ads.description.show_more', 'عرض المزيد')}
        </Button>
      ) : null}
    </div>
  );
}
