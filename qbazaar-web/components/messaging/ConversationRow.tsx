'use client';

/**
 * One inbox row of messages.html (370:18776, chat.js): the other person's
 * 44 px avatar and name, the time, the last message preview and the orange
 * unread counter. The open row gets the soft orange fill and the orange
 * start bar (desktop only; the phone inbox has no selected tint). In select
 * mode a checkbox leads the row.
 *
 * Pure presentational — selection state is controlled by the parent so the
 * URL stays the source of truth.
 */
import { focusRing } from '@/components/design-system/focus-ring';
import { NamedAvatar } from '@/components/account/NamedAvatar';
import { SelectCheckbox } from '@/components/account/SelectCheckbox';
import { cn } from '@/lib/utils';
import type { ConversationListItem } from '@/lib/api/types';
import { formatRelativeTime } from './relative-time';
import { t } from '@/lib/i18n/messages';

interface Props {
  conversation: ConversationListItem;
  active: boolean;
  onSelect: (id: string) => void;
  /** Select mode: the row's checkbox. */
  selection?: { checked: boolean; onChange: (checked: boolean) => void };
}

export function ConversationRow({ conversation, active, onSelect, selection }: Props) {
  const { other_participant, last_message_preview, last_message_at, unread_count } = conversation;
  const preview = last_message_preview ?? t('messaging.empty.preview', 'لا توجد رسائل بعد');
  const when = formatRelativeTime(last_message_at);
  const unread = unread_count > 0;

  return (
    <div className="flex items-center gap-3">
      {selection ? (
        <SelectCheckbox
          checked={selection.checked}
          onChange={(event) => selection.onChange(event.target.checked)}
          aria-label={t('messaging.select_one', { name: other_participant.full_name })}
        />
      ) : null}
      <button
        type="button"
        onClick={() => onSelect(conversation.id)}
        aria-current={active ? 'true' : undefined}
        data-conversation-id={conversation.id}
        className={cn(
          'flex min-w-0 flex-1 cursor-pointer items-start gap-3 rounded-qb-lg border-s-[3px] border-transparent p-3.5 text-start transition-colors',
          active ? 'qb-desktop:border-qb-brand qb-desktop:bg-qb-acct-active' : 'hover:bg-qb-hover',
          focusRing,
          '-outline-offset-2',
        )}
      >
        <NamedAvatar
          name={other_participant.full_name}
          src={other_participant.avatar_thumb_url}
          className="size-11 text-qb-body-sm"
        />
        <span className="min-w-0 flex-1">
          <span className="flex items-baseline justify-between gap-2">
            <span className="truncate text-qb-body font-semibold text-qb-ink">
              <bdi>{other_participant.full_name}</bdi>
            </span>
            {when ? <span className="shrink-0 text-qb-label text-qb-ink-subtle">{when}</span> : null}
          </span>
          <span className="mt-1 flex items-center justify-between gap-2">
            <span className="truncate text-qb-caption font-semibold text-qb-ink-secondary">
              <bdi>{preview}</bdi>
            </span>
            {unread ? (
              <span className="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-qb-brand px-1 text-[11px] text-qb-on-brand">
                {unread_count > 99 ? '99+' : unread_count}
                <span className="sr-only"> {t('messaging.unread_label')}</span>
              </span>
            ) : null}
          </span>
        </span>
      </button>
    </div>
  );
}
