'use client';

/**
 * One inbox row (370:18776): the other person's avatar and name, the ad the
 * conversation is about, the last message preview, the time and the orange
 * unread counter. The active row gets the soft orange fill and start bar.
 *
 * Pure presentational — selection state is controlled by the parent so the
 * URL stays the source of truth.
 */
import { focusRing } from '@/components/design-system/focus-ring';
import { NamedAvatar } from '@/components/account/NamedAvatar';
import { cn } from '@/lib/utils';
import type { ConversationListItem } from '@/lib/api/types';
import { formatRelativeTime } from './relative-time';
import { t } from '@/lib/i18n/messages';

interface Props {
  conversation: ConversationListItem;
  active: boolean;
  onSelect: (id: string) => void;
}

export function ConversationRow({ conversation, active, onSelect }: Props) {
  const { ad, other_participant, last_message_preview, last_message_at, unread_count } =
    conversation;
  const preview = last_message_preview ?? t('messaging.empty.preview', 'لا توجد رسائل بعد');
  const when = formatRelativeTime(last_message_at);
  const unread = unread_count > 0;
  // The muted grey falls just under 4.5:1 on the active row's soft orange fill.
  const mutedText = active ? 'text-qb-ink-secondary' : 'text-qb-ink-muted';

  return (
    <button
      type="button"
      onClick={() => onSelect(conversation.id)}
      aria-current={active ? 'true' : undefined}
      data-conversation-id={conversation.id}
      className={cn(
        'relative flex w-full items-start gap-3.5 px-[17px] py-3 text-start transition-colors qb-desktop:px-6',
        active
          ? 'bg-qb-brand-soft before:absolute before:inset-y-0 before:start-0 before:w-1 before:bg-qb-brand'
          : 'hover:bg-qb-hover',
        focusRing,
        '-outline-offset-2',
      )}
    >
      <NamedAvatar
        name={other_participant.full_name}
        src={other_participant.avatar_thumb_url}
        className="size-12 qb-desktop:size-[47px]"
      />
      <span className="min-w-0 flex-1">
        <span className="flex items-baseline justify-between gap-2">
          <span className="truncate text-qb-body font-semibold text-qb-ink">
            <bdi>{other_participant.full_name}</bdi>
          </span>
          {when ? <span className={cn('shrink-0 text-qb-micro', mutedText)}>{when}</span> : null}
        </span>
        <span className="mt-0.5 block truncate text-qb-micro font-medium text-qb-ink-body">
          <bdi>{ad.title}</bdi>
        </span>
        <span className="mt-1 flex items-center justify-between gap-2">
          <span className={cn('truncate text-qb-micro', unread ? 'font-semibold text-qb-ink' : mutedText)}>
            <bdi>{preview}</bdi>
          </span>
          {unread ? (
            <span className="inline-flex h-[21px] min-w-[21px] shrink-0 items-center justify-center rounded-qb-pill bg-qb-brand px-1 text-qb-tiny font-semibold text-white">
              {unread_count > 99 ? '99+' : unread_count}
              <span className="sr-only"> {t('messaging.unread_label')}</span>
            </span>
          ) : null}
        </span>
      </span>
    </button>
  );
}
