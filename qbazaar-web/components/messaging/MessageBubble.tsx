'use client';

/**
 * Single message bubble.
 *
 * - "Mine" bubbles are coral with white text and align to the end (logical).
 * - "Theirs" bubbles are cream-100 with ink-900 text and align to the start.
 * - The avatar only renders on the first message in a streak from the same
 *   sender so the column stays tidy when one person sends a burst.
 * - For "mine" bubbles we paint a `read_at` indicator under the last one.
 */
import Image from 'next/image';
import { cn } from '@/lib/utils';
import type { Message } from '@/lib/api/types';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
    <div
      className={cn(
        'flex items-end gap-2',
        isMine ? 'flex-row-reverse' : 'flex-row',
      )}
    >
      <div className="w-8 shrink-0">
        {!isMine && showAvatar ? (
          <Avatar className="size-8">
            {message.sender.avatar_thumb_url ? (
              <Image
                src={message.sender.avatar_thumb_url}
                alt={message.sender.full_name}
                width={32}
                height={32}
                className="size-full rounded-full object-cover"
              />
            ) : (
              <AvatarFallback>
                {message.sender.full_name.charAt(0) || '?'}
              </AvatarFallback>
            )}
          </Avatar>
        ) : null}
      </div>

      <div
        className={cn(
          'flex max-w-[78%] flex-col gap-0.5',
          isMine ? 'items-end' : 'items-start',
        )}
      >
        <div
          title={time}
          className={cn(
            'rounded-2xl px-4 py-2 text-sm leading-relaxed whitespace-pre-wrap break-words',
            isMine
              ? 'bg-coral text-white rounded-ee-sm'
              : 'bg-cream-100 text-ink-900 rounded-es-sm',
            isPending && 'opacity-70',
          )}
        >
          {message.body}
        </div>
        <div
          className={cn(
            'flex items-center gap-1 text-[10px]',
            isMine ? 'text-ink-500' : 'text-ink-500',
          )}
        >
          <span>{time}</span>
          {isMine && isLastInOwnStreak && message.read_at ? (
            <span className="text-coral font-semibold">
              · {t('messaging.read_indicator', 'تم القراءة')}
            </span>
          ) : null}
        </div>
      </div>
    </div>
  );
}
