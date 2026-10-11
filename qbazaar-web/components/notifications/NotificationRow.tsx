'use client';

/**
 * One row of notifications.html (455:14636): a 180 px card with the picture
 * slot, title, body, time and the orange unread dot. Notifications carry no
 * ad photo, so the slot shows the notification's icon. The whole card is a
 * button that marks the row read and opens `cta_url`; selecting and
 * deleting happen through the list's checkboxes.
 */
import { useRouter } from 'next/navigation';
import { Clock } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { useMarkNotificationReadMutation } from '@/lib/queries/notifications';
import { formatRelativeTime } from '@/components/messaging/relative-time';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Notification } from '@/lib/api/types';

interface Props {
  notification: Notification;
  className?: string;
}

export function NotificationRow({ notification: n, className }: Props) {
  const router = useRouter();
  const markRead = useMarkNotificationReadMutation();
  const unread = !n.read_at;

  const handleOpen = () => {
    if (unread) markRead.mutate(n.id);
    if (n.cta_url) router.push(n.cta_url);
  };

  return (
    <article
      className={cn(
        'relative flex items-center gap-3 rounded-qb-2xl bg-qb-surface p-3.5 font-qb shadow-qb-acct-notification qb-tablet:gap-4 qb-tablet:p-2.5',
        className,
      )}
    >
      <button
        type="button"
        onClick={handleOpen}
        className={cn('absolute inset-0 cursor-pointer rounded-qb-2xl', focusRing)}
        aria-label={unread ? t('notifications.open_unread', { title: n.title }) : n.title}
      />

      <span
        aria-hidden="true"
        // polish.css: a 112×92 r16 picture on phones, 191×160 r24 from the tablet layout up.
        className="flex h-[92px] w-[112px] shrink-0 items-center justify-center rounded-qb-xl bg-qb-brand-soft text-qb-brand qb-tablet:h-40 qb-tablet:w-[191px] qb-tablet:rounded-qb-2xl"
      >
        <DynamicIcon name={n.icon} className="size-10 qb-tablet:size-12" strokeWidth={1.5} />
      </span>

      <div className="flex min-w-0 flex-1 flex-col gap-4 py-2 pe-6 qb-tablet:gap-6 qb-tablet:px-2.5 qb-tablet:py-4 qb-tablet:pe-12">
        <div className="flex flex-col gap-2">
          <h2 className="text-qb-h5 font-medium tracking-normal text-qb-ink-body">
            <bdi>{n.title}</bdi>
          </h2>
          <p className="line-clamp-3 text-qb-caption text-qb-acct-meta">
            <bdi>{n.body}</bdi>
          </p>
        </div>
        <p className="flex items-center gap-1.5 text-qb-caption text-qb-ink-subtle">
          <Clock aria-hidden="true" className="size-3.5" strokeWidth={1.6} />
          {formatRelativeTime(n.created_at)}
        </p>
      </div>

      {unread ? (
        <span
          aria-hidden="true"
          className="absolute end-4 top-1/2 size-3.5 -translate-y-1/2 rounded-full bg-qb-brand qb-tablet:end-6"
        />
      ) : null}
    </article>
  );
}
