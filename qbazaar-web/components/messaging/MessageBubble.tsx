'use client';

/**
 * Single chat message (365:14788, 604:33673) in the shared `ChatBubble`.
 *
 * - The sender's avatar shows on the first incoming message of a streak
 *   (tablet and up).
 * - For "mine" bubbles we paint a `read_at` indicator under the last one.
 */
import { Avatar } from '@/components/design-system/Avatar';
import type { Message } from '@/lib/api/types';
import { ChatBubble } from './ChatBubble';
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
    <ChatBubble
      mine={isMine}
      title={time}
      pending={isPending}
      avatar={
        showAvatar ? (
          <Avatar
            name={message.sender.full_name}
            src={message.sender.avatar_thumb_url}
            tone="brand"
            className="mt-3 size-[53px] text-qb-brand-on-soft"
          />
        ) : null
      }
      meta={
        <>
          <span>{time}</span>
          {isMine && isLastInOwnStreak && message.read_at ? (
            <span className="font-semibold text-qb-brand">
              · {t('messaging.read_indicator', 'تم القراءة')}
            </span>
          ) : null}
        </>
      }
    >
      {message.body}
    </ChatBubble>
  );
}
