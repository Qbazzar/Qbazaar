'use client';

import { useId, useState } from 'react';
import { ThumbsDown, ThumbsUp, type LucideIcon } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Card } from '@/components/design-system/Card';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

type Vote = 'yes' | 'no';

const OPTIONS: { vote: Vote; icon: LucideIcon; label: string; thanks: string }[] = [
  { vote: 'yes', icon: ThumbsUp, label: 'help.helpful_yes', thanks: 'help.feedback_thanks_yes' },
  { vote: 'no', icon: ThumbsDown, label: 'help.helpful_no', thanks: 'help.feedback_thanks_no' },
];

/**
 * "Was this article helpful?" under a help article. The API has no feedback
 * endpoint yet, so a vote only thanks the reader; it can still be changed.
 */
export function HelpFeedback({ className }: { className?: string }) {
  const headingId = useId();
  const [vote, setVote] = useState<Vote | null>(null);
  const thanks = OPTIONS.find((option) => option.vote === vote)?.thanks;

  return (
    <Card
      large
      elevated
      className={cn(
        'flex flex-col items-start gap-4 qb-tablet:flex-row qb-tablet:items-center qb-tablet:justify-between',
        className,
      )}
    >
      <div className="min-w-0">
        <h2 id={headingId} className="font-qb text-qb-body-lg font-medium tracking-normal text-qb-ink qb-desktop:text-qb-h5">
          {t('help.helpful_question')}
        </h2>
        <p role="status" className="text-qb-caption text-qb-ink-muted qb-desktop:text-qb-body">
          {thanks ? t(thanks) : ''}
        </p>
      </div>
      <div role="group" aria-labelledby={headingId} className="flex shrink-0 gap-3">
        {OPTIONS.map((option) => (
          <Button
            key={option.vote}
            variant="outline"
            size="sm"
            aria-pressed={vote === option.vote}
            onClick={() => setVote(option.vote)}
            className="aria-pressed:border-qb-brand aria-pressed:bg-qb-brand-soft"
          >
            <Icon icon={option.icon} />
            {t(option.label)}
          </Button>
        ))}
      </div>
    </Card>
  );
}
