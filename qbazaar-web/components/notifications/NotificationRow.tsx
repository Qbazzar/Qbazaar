'use client';

/**
 * One row on the notifications index page (455:14636).
 *
 * Icon tile in the photo slot, title, body, timestamp and the orange unread
 * dot. The whole row is a button that marks the row read + (if present)
 * navigates to `cta_url`; the delete button sits above it and asks for
 * confirmation first.
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Clock, Loader2, Trash2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import {
  useDeleteNotificationMutation,
  useMarkNotificationReadMutation,
} from '@/lib/queries/notifications';
import { formatRelativeTime } from '@/components/messaging/relative-time';
import { lucideIconName } from './icon-name';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { ApiClientError } from '@/lib/api/auth';
import type { Notification } from '@/lib/api/types';
import { ModalActions } from '@/components/account/ModalActions';

interface Props {
  notification: Notification;
}

export function NotificationRow({ notification: n }: Props) {
  const router = useRouter();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const markRead = useMarkNotificationReadMutation();
  const deleteMutation = useDeleteNotificationMutation();
  const unread = !n.read_at;

  const handleOpen = () => {
    if (unread) markRead.mutate(n.id);
    if (n.cta_url) router.push(n.cta_url);
  };

  const handleDelete = () => {
    deleteMutation.mutate(n.id, {
      onSuccess: () => {
        toast.success(t('notifications.delete_success'));
        setConfirmOpen(false);
      },
      onError: (err) => {
        const message =
          err instanceof ApiClientError
            ? err.message
            : t('common.error', 'حدث خطأ، حاول مرة أخرى');
        toast.error(message);
      },
    });
  };

  return (
    <article className="relative flex items-center gap-2 rounded-qb-xl border border-qb-line bg-qb-surface p-[9px] font-qb shadow-qb-card qb-tablet:gap-4 qb-tablet:rounded-qb-2xl qb-tablet:p-[11px]">
      <button
        type="button"
        onClick={handleOpen}
        className={cn('absolute inset-0 rounded-qb-xl qb-tablet:rounded-qb-2xl', focusRing)}
        aria-label={unread ? t('notifications.open_unread', { title: n.title }) : n.title}
      />

      <span
        aria-hidden="true"
        className={cn(
          'flex h-[86px] w-[108px] shrink-0 items-center justify-center rounded-qb-lg qb-tablet:h-40 qb-tablet:w-[191px] qb-tablet:rounded-qb-2xl',
          unread ? 'bg-qb-brand-soft text-qb-brand' : 'bg-qb-fill text-qb-ink-secondary',
        )}
      >
        <DynamicIcon name={lucideIconName(n.icon)} className="size-8 qb-tablet:size-12" strokeWidth={1.5} />
      </span>

      <div className="min-w-0 flex-1 py-1 qb-tablet:py-3">
        <h2 className="truncate text-qb-micro font-semibold tracking-normal text-qb-ink-body qb-tablet:text-qb-body qb-tablet:font-medium qb-desktop:text-qb-h5">
          <bdi>{n.title}</bdi>
        </h2>
        <p className="mt-2 line-clamp-3 text-qb-tiny text-qb-ink-muted qb-tablet:mt-4 qb-tablet:line-clamp-2 qb-tablet:text-qb-caption qb-desktop:text-qb-body">
          <bdi>{n.body}</bdi>
        </p>
        <p className="mt-2 flex items-center gap-1.5 text-qb-tiny text-qb-ink-subtle qb-tablet:mt-5 qb-tablet:text-qb-micro qb-desktop:text-qb-caption">
          <Icon icon={Clock} size="sm" className="size-3 qb-tablet:size-4" />
          {formatRelativeTime(n.created_at)}
        </p>
      </div>

      <div className="relative flex shrink-0 flex-col items-center gap-3 self-stretch justify-between py-1 qb-tablet:flex-row qb-tablet:self-center qb-tablet:py-0 qb-tablet:pe-2">
        {unread ? (
          <span
            aria-hidden="true"
            className="size-2.5 rounded-full bg-qb-brand shadow-qb-brand qb-tablet:order-last qb-tablet:size-[19px]"
          />
        ) : (
          <span aria-hidden="true" className="size-2.5 qb-tablet:hidden" />
        )}
        <Button
          variant="ghost"
          size="icon"
          className="size-9 text-qb-ink-subtle hover:text-qb-danger"
          aria-label={t('notifications.delete_label', { title: n.title })}
          onClick={() => setConfirmOpen(true)}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>

      <Modal
        open={confirmOpen}
        onOpenChange={(open) => {
          if (!open && !deleteMutation.isPending) setConfirmOpen(false);
        }}
        title={t('notifications.delete_confirm.title', 'حذف الإشعار؟')}
        description={t('notifications.delete_confirm.body', 'لا يمكن التراجع.')}
      >
        <ModalActions className="mt-2">
          <Button size="sm" disabled={deleteMutation.isPending} onClick={handleDelete}>
            {deleteMutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('notifications.delete_confirm.confirm', 'حذف')}
          </Button>
          <Button variant="muted" size="sm" disabled={deleteMutation.isPending} onClick={() => setConfirmOpen(false)}>
            {t('common.cancel', 'إلغاء')}
          </Button>
        </ModalActions>
      </Modal>
    </article>
  );
}
