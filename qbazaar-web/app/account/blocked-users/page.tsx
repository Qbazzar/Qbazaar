'use client';

/**
 * FE-2.10 — Blocked users. No frame: built from the Following cards of
 * `users.html` (376:9268) with "Unblock" in place of "Following".
 *
 * Each card exposes an "Unblock" button calling `DELETE /users/{id}/block`.
 * On success the list is refetched so the card drops out.
 */
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2, UserX } from 'lucide-react';

import { Avatar } from '@/components/design-system/Avatar';
import { Button } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { PanelState } from '@/components/account/PanelState';
import { SettingsPanel } from '@/components/account/SettingsPanel';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { getLocale } from '@/lib/i18n/locale';
import { cn, formatRelativeTime } from '@/lib/utils';
import { listBlockedUsers } from '@/lib/api/account';
import { unblockUser } from '@/lib/api/users';
import { ApiClientError } from '@/lib/api/auth';
import type { BlockedUser } from '@/lib/api/types';

export default function BlockedUsersPage() {
  const queryClient = useQueryClient();
  const {
    data: blocked = [],
    isLoading,
    error,
  } = useQuery({
    queryKey: ['account', 'blocked-users'],
    queryFn: listBlockedUsers,
  });

  const mutation = useMutation({
    mutationFn: (userId: string) => unblockUser(userId),
    onSuccess: () => {
      toast.success(t('account.blocked_users.unblock_success'));
      queryClient.invalidateQueries({ queryKey: ['account', 'blocked-users'] });
    },
    onError: (err) => {
      if (err instanceof ApiClientError) {
        toast.error(
          translateMaybeKey(`account.errors.${err.code}`) ||
            translateMaybeKey(`auth.errors.${err.code}`) ||
            err.message,
        );
      } else {
        toast.error(t('auth.errors.unknown'));
      }
    },
  });

  return (
    <SettingsPanel title={t('account.blocked_users.title')} description={t('account.blocked_users.subtitle')}>
      {isLoading || error ? (
        <PanelState loading={isLoading} />
      ) : blocked.length === 0 ? (
        <EmptyState
          icon={<Icon icon={UserX} size="lg" />}
          title={t('account.blocked_users.empty_title')}
          description={t('account.blocked_users.empty_body')}
          className="rounded-qb-2xl border border-qb-line shadow-qb-card"
        />
      ) : (
        <ul className="grid gap-4 qb-desktop:grid-cols-2">
          {blocked.map((user) => (
            <li key={user.id}>
              <BlockedUserCard
                user={user}
                pending={mutation.isPending && mutation.variables === user.id}
                disabled={mutation.isPending}
                onUnblock={() => mutation.mutate(user.id)}
              />
            </li>
          ))}
        </ul>
      )}
    </SettingsPanel>
  );
}

function BlockedUserCard({
  user,
  pending,
  disabled,
  onUnblock,
}: {
  user: BlockedUser;
  pending: boolean;
  disabled: boolean;
  onUnblock: () => void;
}) {
  return (
    <article className="flex h-full flex-col gap-5 rounded-qb-2xl border border-qb-line bg-qb-surface p-4 shadow-qb-card qb-tablet:gap-8 qb-tablet:p-6">
      <Link href={`/u/${user.id}`} className={cn('flex min-w-0 items-center gap-3.5 rounded-qb-md', focusRing)}>
        <Avatar name={user.full_name} src={user.avatar_url} tone="brand" className="size-[42px] qb-tablet:size-[53px]" />
        <span className="min-w-0">
          <span className="block truncate text-qb-body font-semibold text-qb-ink qb-tablet:text-qb-h5">
            {user.full_name}
          </span>
          <span className="mt-1 block text-qb-micro text-qb-ink-subtle qb-tablet:text-qb-caption">
            {t('account.blocked_users.blocked_at', { when: formatRelativeTime(user.blocked_at, getLocale()) })}
          </span>
        </span>
      </Link>
      <Button variant="outline" fullWidth onClick={onUnblock} disabled={disabled} className="mt-auto h-11 qb-tablet:h-12">
        {pending ? (
          <>
            <Loader2 className="animate-spin" aria-hidden="true" />
            {t('account.blocked_users.unblocking')}
          </>
        ) : (
          t('account.blocked_users.unblock')
        )}
      </Button>
    </article>
  );
}
