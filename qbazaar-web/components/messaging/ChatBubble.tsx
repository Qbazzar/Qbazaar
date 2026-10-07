import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export interface ChatBubbleProps {
  /** The viewer's own messages sit on the end side. */
  mine: boolean;
  /** Avatar beside an incoming bubble from the tablet layout up; null keeps its column empty. */
  avatar?: ReactNode;
  /** Line under the bubble: time, author, read receipt. */
  meta: ReactNode;
  /** Tooltip on the bubble (the full time). */
  title?: string;
  /** Dimmed while the message is still being sent. */
  pending?: boolean;
  children: ReactNode;
}

/**
 * Chat bubble of 365:14788 and 604:33673: incoming ones white with a border
 * on the start side, the viewer's own on the end side, orange on phones and
 * tablets and white on the desktop frame. Shared by the chat and the support
 * ticket thread.
 */
export function ChatBubble({ mine, avatar = null, meta, title, pending = false, children }: ChatBubbleProps) {
  return (
    <div className={cn('flex items-start gap-4', mine ? 'flex-row-reverse' : 'flex-row')}>
      {mine ? null : <div className="hidden w-[53px] shrink-0 qb-tablet:block">{avatar}</div>}

      <div className={cn('flex max-w-[80%] flex-col gap-1.5 qb-tablet:max-w-[534px]', mine ? 'items-end' : 'items-start')}>
        <div
          title={title}
          dir="auto"
          className={cn(
            'rounded-qb-xl px-4 py-3 font-qb text-qb-body leading-snug font-medium whitespace-pre-wrap break-words qb-desktop:rounded-qb-md',
            mine
              ? 'bg-qb-brand text-white qb-desktop:border qb-desktop:border-qb-line qb-desktop:bg-qb-surface qb-desktop:text-qb-ink qb-desktop:shadow-qb-soft'
              : 'border border-qb-line bg-qb-surface text-qb-ink shadow-qb-soft',
            pending && 'opacity-70',
          )}
        >
          {children}
        </div>
        <div className="flex flex-wrap items-center gap-1 text-qb-tiny text-qb-ink-subtle">{meta}</div>
      </div>
    </div>
  );
}
