'use client';

/**
 * Single message bubble (365:14788, 604:33673).
 *
 * - Incoming bubbles are white with a border and align to the start; the
 *   sender's avatar shows on the first message of a streak (tablet and up).
 * - "Mine" bubbles align to the end: orange on phones and tablets, white on
 *   the desktop frame.
 * - For "mine" bubbles we paint a `read_at` indicator under the last one.
 */
import { Avatar } from '@/components/design-system/Avatar';
import { cn } from '@/lib/utils';
import type { Message } from '@/lib/api/types';
import { formatClockTime } from './relative-time';
import { t } from '@/lib/i18n/messages';

interface Props {
  message: Message;
  isMine: boolean;
  showAvatar: boolean;
  isLastInOwnStreak: boolean;
}

export function MessageBubble({
  message,
  isMine,
  showAvatar,
  isLastInOwnStreak,
}: Props) {
  const time = formatClockTime(message.created_at);
  // Temporary client-side ids start with "temp-" — show a subtle muted state
  // so the user knows the message is in flight.
  const isPending = message.id.startsWith('temp-');

  return (
    <div className={cn('flex items-start gap-4', isMine ? 'flex-row-reverse' : 'flex-row')}>
      {isMine ? null : (
        <div className="hidden w-[53px] shrink-0 qb-tablet:block">
          {showAvatar ? (
            <Avatar
              name={message.sender.full_name}
              src={message.sender.avatar_thumb_url}
              tone="brand"
              className="mt-3 size-[53px]"
            />
          ) : null}
        </div>
      )}

      <div className={cn('flex max-w-[80%] flex-col gap-1.5 qb-tablet:max-w-[534px]', isMine ? 'items-end' : 'items-start')}>
        <div
          title={time}
          dir="auto"
          className={cn(
            'rounded-qb-xl px-4 py-3 font-qb text-qb-body leading-snug font-medium whitespace-pre-wrap break-words qb-desktop:rounded-qb-md',
            isMine
              ? 'bg-qb-brand text-white qb-desktop:border qb-desktop:border-qb-line qb-desktop:bg-qb-surface qb-desktop:text-qb-ink qb-desktop:shadow-qb-soft'
              : 'border border-qb-line bg-qb-surface text-qb-ink shadow-qb-soft',
            isPending && 'opacity-70',
          )}
        >
          {message.body}
        </div>
        <div className="flex items-center gap-1 text-qb-tiny text-qb-ink-subtle">
          <span>{time}</span>
          {isMine && isLastInOwnStreak && message.read_at ? (
            <span className="font-semibold text-qb-brand">
              · {t('messaging.read_indicator', 'تم القراءة')}
            </span>
          ) : null}
        </div>
      </div>
    </div>
  );
}
