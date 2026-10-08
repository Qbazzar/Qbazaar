'use client';

/**
 * "Block" control: a confirmation dialog (369:17179 / 532:26075 / 604:33981)
 * around `POST /users/{id}/block`. Guests are sent to login and come back.
 * On success it closes, says so and calls `onBlocked` so the parent can update.
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Ban, Loader2Icon } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Modal } from '@/components/design-system/Modal';
import { useAuth } from '@/hooks/useAuth';
import { t } from '@/lib/i18n/messages';
import { blockUser } from '@/lib/api/users';
import { ApiClientError } from '@/lib/api/auth';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';
import { cn } from '@/lib/utils';

/** Blocking is idempotent, so "already blocked" is never an error. */
const BLOCK_ERROR_KEYS: Record<string, string> = {
  USER_002: 'users.block.errors.admin',
  USER_003: 'users.block.errors.self',
  RATE_LIMIT_EXCEEDED: 'auth.errors.RATE_LIMIT_EXCEEDED',
};

/** The two equal buttons of the reference: 34 px from 601 px, 46 px on phones. */
const dialogButton = 'h-[46px] flex-1 rounded-qb-lg px-4 focus-visible:outline-solid qb-tablet:h-[34px] qb-tablet:rounded-qb-sm';

export interface BlockUserButtonProps {
  userId: string;
  userName: string;
  /** Fired after the block succeeds; the parent can hide the user's content. */
  onBlocked?: () => void;
  className?: string;
}

export function BlockUserButton({ userId, userName, onBlocked, className }: BlockUserButtonProps) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { isAuthenticated, isHydrated } = useAuth();
  const [open, setOpen] = useState(false);

  const mutation = useMutation({
    mutationFn: () => blockUser(userId),
    onSuccess: () => {
      toast.success(t('users.block.success'));
      queryClient.invalidateQueries({ queryKey: ['account', 'blocked-users'] });
      setOpen(false);
      onBlocked?.();
    },
    onError: (err) => {
      const key = err instanceof ApiClientError ? BLOCK_ERROR_KEYS[err.code] : undefined;
      toast.error(t(key ?? 'common.error'));
    },
  });

  const openDialog = () => {
    if (!isHydrated) return;
    if (!isAuthenticated) {
      router.push(`/login?from=${encodeURIComponent(currentLocationPath())}`);
      return;
    }
    setOpen(true);
  };

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        onClick={openDialog}
        className={cn('font-normal text-qb-ink-subtle hover:text-qb-ink focus-visible:outline-solid', className)}
      >
        <Ban aria-hidden />
        {t('users.block.button')}
      </Button>
      <Modal
        open={open}
        onOpenChange={setOpen}
        title={t('users.block.confirm_title', { name: userName })}
        description={t('users.block.confirm_body')}
      >
        <div className="mt-2 flex gap-3 qb-tablet:gap-4">
          <Button
            aria-disabled={mutation.isPending || undefined}
            onClick={mutation.isPending ? undefined : () => mutation.mutate()}
            className={cn(dialogButton, 'text-qb-body qb-tablet:text-qb-caption')}
          >
            {mutation.isPending ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
            {mutation.isPending ? t('users.block.blocking') : t('users.block.confirm')}
          </Button>
          <Button variant="muted" onClick={() => setOpen(false)} className={cn(dialogButton, 'text-qb-body qb-tablet:text-qb-caption')}>
            {t('users.block.cancel')}
          </Button>
        </div>
      </Modal>
    </>
  );
}
