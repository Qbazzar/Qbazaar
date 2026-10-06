'use client';

/**
 * "Block this user" control: a confirmation dialog around
 * `POST /users/{id}/block`. On success it closes, says so and calls
 * `onBlocked` so the parent can update.
 */
import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Ban, Loader2Icon } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Modal } from '@/components/design-system/Modal';
import { t } from '@/lib/i18n/messages';
import { blockUser } from '@/lib/api/users';
import { ApiClientError } from '@/lib/api/auth';
import { cn } from '@/lib/utils';

/** Blocking is idempotent, so "already blocked" is never an error. */
const BLOCK_ERROR_KEYS: Record<string, string> = {
  USER_002: 'users.block.errors.admin',
  USER_003: 'users.block.errors.self',
  RATE_LIMIT_EXCEEDED: 'auth.errors.RATE_LIMIT_EXCEEDED',
};

export interface BlockUserButtonProps {
  userId: string;
  userName: string;
  /** Fired after the block succeeds; the parent can hide the user's content. */
  onBlocked?: () => void;
  className?: string;
}

export function BlockUserButton({ userId, userName, onBlocked, className }: BlockUserButtonProps) {
  const queryClient = useQueryClient();
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

  return (
    <Modal
      open={open}
      onOpenChange={setOpen}
      title={t('users.block.confirm_title', { name: userName })}
      description={t('users.block.confirm_body')}
      trigger={
        <Button variant="ghost" size="sm" className={cn('font-normal text-qb-ink-subtle hover:text-qb-ink', className)}>
          <Ban aria-hidden />
          {t('users.block.button')}
        </Button>
      }
    >
      <div className="mt-2 flex flex-col-reverse gap-3 qb-tablet:flex-row qb-tablet:justify-center">
        <Button variant="outline" onClick={() => setOpen(false)}>
          {t('users.block.cancel')}
        </Button>
        <Button variant="danger" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
          {mutation.isPending ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
          {mutation.isPending ? t('users.block.blocking') : t('users.block.confirm')}
        </Button>
      </div>
    </Modal>
  );
}
