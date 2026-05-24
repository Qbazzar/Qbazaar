'use client';

/**
 * Single conversation row in the inbox list.
 *
 * Layout:
 *  - leading 56×56 ad image (rounded)
 *  - title + last-message preview + relative timestamp
 *  - trailing coral unread pill when count > 0
 *  - footer: other participant name (coral, xs)
 *
 * Pure presentational — selection state is controlled by the parent so the
 * URL stays the source of truth.
 */
import Image from 'next/image';
import { ImageIcon } from 'lucide-react';
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
  const image = ad.primary_image?.sizes.thumbnail ?? ad.primary_image?.url ?? null;
  const preview = last_message_preview ?? t('messaging.empty.preview', 'لا توجد رسائل بعد');
  const when = formatRelativeTime(last_message_at);

  return (
    <button
      type="button"
      onClick={() => onSelect(conversation.id)}
      aria-pressed={active}
      className={cn(
        'flex w-full items-start gap-3 rounded-2xl border p-3 text-start transition-colors',
        active
          ? 'border-coral bg-cream-200'
          : 'border-transparent hover:bg-cream-100',
      )}
    >
      <div className="bg-cream-200 relative size-14 shrink-0 overflow-hidden rounded-xl">
        {image ? (
          <Image
            src={image}
            alt={ad.title}
            fill
            sizes="56px"
            className="object-cover"
          />
        ) : (
          <div className="text-ink-500 flex size-full items-center justify-center">
            <ImageIcon className="size-5" aria-hidden />
          </div>
        )}
      </div>

      <div className="min-w-0 flex-1">
        <div className="flex items-start justify-between gap-2">
          <p className="text-ink-900 truncate text-sm font-bold">{ad.title}</p>
          {when ? (
            <span className="text-ink-500 shrink-0 text-[10px]">{when}</span>
          ) : null}
        </div>
        <p
          className={cn(
            'mt-0.5 truncate text-xs',
            unread_count > 0 ? 'text-ink-900 font-semibold' : 'text-ink-700',
          )}
        >
          {preview}
        </p>
        <div className="mt-1 flex items-center justify-between gap-2">
          <p className="text-coral truncate text-[11px]">
            {other_participant.full_name}
          </p>
          {unread_count > 0 ? (
            <span className="bg-coral inline-flex min-w-[18px] items-center justify-center rounded-full px-1.5 text-[10px] font-bold leading-[18px] text-white">
              {unread_count > 99 ? '99+' : unread_count}
            </span>
          ) : null}
        </div>
      </div>
    </button>
  );
}
