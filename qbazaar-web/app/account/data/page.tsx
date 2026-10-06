'use client';

/**
 * FE-2.7 — Data & account (Delete Account, 401:10866 / 438:18762).
 *
 *   1. Export my data           POST  /account/data-export-request
 *   2. Deactivate my account    POST  /account/deactivate
 *   3. Delete my account        DELETE /account/delete-request
 *
 * Steps 2 & 3 both require the current password (so a hijacked session
 * can't kill an account) and accept an optional reason; the delete reason is
 * picked from the design's list. After success we sign the user out +
 * redirect to `/login` with a sticky notice (`?deactivated=1` or
 * `?deleted=1`) so the login page can explain what happened next.
 *
 * The export action keeps the user on this page — the actual file is
 * delivered out-of-band over email.
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Loader2, Trash2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Textarea } from '@/components/design-system/Input';
import { Modal } from '@/components/design-system/Modal';
import { announcedError } from '@/components/auth/FieldError';
import { PasswordInput } from '@/components/auth/PasswordInput';
import { SettingsList, SettingsPanel, SettingsRow } from '@/components/account/SettingsPanel';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import {
  deactivateAccount,
  requestAccountDeletion,
  requestDataExport,
} from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode, UserErrorCode } from '@/lib/api/types';
import {
  deactivateSchema,
  deleteAccountSchema,
  type DeactivateInput,
  type DeleteAccountInput,
} from '@/lib/validation/account';
import { useAuth } from '@/hooks/useAuth';

type LifecycleInput = DeactivateInput | DeleteAccountInput;
type LifecycleFlow = 'deactivate' | 'delete';

const DELETE_REASONS = ['not_using', 'other_account', 'problems', 'privacy', 'prefer_not', 'something_else'] as const;
type DeleteReason = (typeof DELETE_REASONS)[number];

export default function AccountDataPage() {
  return (
    <SettingsPanel title={t('account.data.title')} description={t('account.data.subtitle')}>
      <SettingsList>
        <ExportDataRow />
        <DeactivateAccountRow />
      </SettingsList>
      <DeleteAccountSection />
    </SettingsPanel>
  );
}

// ── 1. Export ─────────────────────────────────────────────────────────────

function ExportDataRow() {
  const [queued, setQueued] = useState(false);

  const mutation = useMutation({
    mutationFn: requestDataExport,
    onSuccess: () => setQueued(true),
    onError: (err) => {
      if (err instanceof ApiClientError) {
        toast.error(
          translateMaybeKey(`account.errors.${err.code}`) ||
            translateMaybeKey(`auth.errors.${err.code}`) ||
            err.message,
        );
        return;
      }
      toast.error(t('auth.errors.unknown'));
    },
  });

  return (
    <SettingsRow
      value={t('account.data.export.title')}
      description={
        queued ? (
          <span role="status">
            <span className="block font-semibold text-qb-success">{t('account.data.export.queued_title')}</span>
            {t('account.data.export.queued_body')}
          </span>
        ) : (
          t('account.data.export.body')
        )
      }
      action={
        queued ? (
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setQueued(false);
              mutation.reset();
            }}
          >
            {t('account.data.export.request_again')}
          </Button>
        ) : (
          <Button variant="outline" size="sm" onClick={() => mutation.mutate()} disabled={mutation.isPending}>
            {mutation.isPending ? (
              <>
                <Loader2 className="animate-spin" aria-hidden="true" />
                {t('account.data.export.submitting')}
              </>
            ) : (
              t('account.data.export.submit')
            )}
          </Button>
        )
      }
    />
  );
}

// ── 2. Deactivate ─────────────────────────────────────────────────────────

function DeactivateAccountRow() {
  const router = useRouter();
  const { logout } = useAuth();
  const [open, setOpen] = useState(false);

  return (
    <>
      <SettingsRow
        value={t('account.data.deactivate.title')}
        description={t('account.data.deactivate.body')}
        action={
          <Button variant="danger" size="sm" onClick={() => setOpen(true)}>
            {t('account.data.deactivate.submit')}
          </Button>
        }
      />
      <LifecycleDialog
        flow="deactivate"
        open={open}
        onOpenChange={setOpen}
        title={t('account.data.deactivate.dialog_title')}
        description={t('account.data.deactivate.dialog_body')}
        withReason
        onConfirm={async (values) => {
          await deactivateAccount({ password: values.password, reason: values.reason ?? null });
          // Sign the user out locally so the deactivated session can't keep poking
          // protected endpoints; the login notice is the user-visible confirmation.
          await logout();
          router.replace('/login?deactivated=1');
        }}
      />
    </>
  );
}

// ── 3. Delete ─────────────────────────────────────────────────────────────

function DeleteAccountSection() {
  const router = useRouter();
  const { logout } = useAuth();
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState<DeleteReason | null>(null);
  const [otherReason, setOtherReason] = useState('');

  const reasonText =
    reason === null || reason === 'prefer_not'
      ? null
      : reason === 'something_else'
        ? otherReason.trim() || null
        : t(`account.data.delete.reasons.${reason}`);

  return (
    <section aria-labelledby="delete-account-title" className="mt-10 qb-desktop:mt-12">
      <h2 id="delete-account-title" className="text-qb-body-lg font-semibold tracking-normal text-qb-ink qb-tablet:text-qb-h5">
        {t('account.data.delete.title')}
      </h2>
      <p className="mt-2 text-qb-caption text-qb-ink-subtle">{t('account.data.delete.body')}</p>

      <fieldset className="mt-6">
        <legend className="text-qb-body font-medium text-qb-ink-body">{t('account.data.delete.reason_question')}</legend>
        <div className="mt-4 flex flex-col gap-3 qb-desktop:gap-4">
          {DELETE_REASONS.map((key) => (
            <label
              key={key}
              className={cn(
                'flex min-h-14 cursor-pointer items-center justify-between gap-4 rounded-qb-lg border border-qb-line bg-qb-surface px-4 text-qb-caption font-semibold text-qb-ink-title transition-colors qb-tablet:px-6 qb-desktop:min-h-[72px] qb-desktop:text-qb-body',
                'has-[:checked]:border-qb-brand has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-qb-brand-active',
              )}
            >
              {t(`account.data.delete.reasons.${key}`)}
              <input
                type="radio"
                name="delete-reason"
                value={key}
                checked={reason === key}
                onChange={() => setReason(key)}
                className="size-[19px] shrink-0 accent-qb-brand"
              />
            </label>
          ))}
        </div>
        {reason === 'something_else' ? (
          <Field label={t('account.data.delete.reason_label')} className="mt-4">
            {(control) => (
              <Textarea
                {...control}
                rows={3}
                maxLength={280}
                value={otherReason}
                onChange={(event) => setOtherReason(event.target.value)}
                placeholder={t('account.data.delete.reason_placeholder')}
              />
            )}
          </Field>
        ) : null}
      </fieldset>

      <Button fullWidth onClick={() => setOpen(true)} className="mt-8 h-14 rounded-qb-xl qb-desktop:mt-[44px]">
        <Trash2 aria-hidden="true" />
        {t('account.data.delete.submit')}
      </Button>

      <LifecycleDialog
        flow="delete"
        open={open}
        onOpenChange={setOpen}
        title={t('account.data.delete.dialog_title')}
        description={t('account.data.delete.dialog_body')}
        reason={reasonText}
        onConfirm={async (values) => {
          await requestAccountDeletion({ password: values.password, reason: values.reason ?? null });
          toast.success(t('account.data.delete.scheduled_toast'));
          await logout();
          router.replace('/login?deleted=1');
        }}
      />
    </section>
  );
}

// ── Shared confirmation dialog ────────────────────────────────────────────

interface LifecycleDialogProps {
  flow: LifecycleFlow;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  /** Show the free-text reason field (deactivate). */
  withReason?: boolean;
  /** Reason picked outside the dialog (delete). */
  reason?: string | null;
  onConfirm: (values: LifecycleInput) => Promise<void>;
}

/** "Delete Account?" confirmation of 438:18762, with the password the API requires. */
function LifecycleDialog({ flow, open, onOpenChange, title, description, withReason, reason, onConfirm }: LifecycleDialogProps) {
  const form = useForm<LifecycleInput>({
    resolver: zodResolver(flow === 'delete' ? deleteAccountSchema : deactivateSchema),
    mode: 'onBlur',
    defaultValues: { password: '', reason: '' },
  });

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await onConfirm(withReason ? values : { password: values.password, reason });
    } catch (err) {
      handleLifecycleError(err, form, flow);
    }
  });

  const submitting = form.formState.isSubmitting;
  const errors = form.formState.errors;
  const prefix = `${flow}-account`;

  return (
    <Modal
      open={open}
      onOpenChange={(next) => {
        if (!next && submitting) return;
        if (!next) form.reset({ password: '', reason: '' });
        onOpenChange(next);
      }}
      title={title}
      description={description}
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5 text-start">
        <Field
          id={`${prefix}-password`}
          label={t(`account.data.${flow}.password_label`)}
          required
          error={announcedError(errors.password?.message)}
        >
          {(control) => (
            <PasswordInput {...control} autoComplete="current-password" placeholder="••••••••" {...form.register('password')} />
          )}
        </Field>

        {withReason ? (
          <Field
            id={`${prefix}-reason`}
            label={t(`account.data.${flow}.reason_label`)}
            error={announcedError(errors.reason?.message)}
          >
            {(control) => (
              <Textarea
                {...control}
                rows={3}
                maxLength={280}
                placeholder={t(`account.data.${flow}.reason_placeholder`)}
                {...form.register('reason')}
              />
            )}
          </Field>
        ) : null}

        <div className="mt-1 grid grid-cols-2 gap-3 qb-tablet:gap-5">
          <Button type="submit" size="sm" disabled={submitting} className={cn(submitting && 'cursor-progress')}>
            {submitting ? (
              <>
                <Loader2 className="animate-spin" aria-hidden="true" />
                {t(`account.data.${flow}.confirming`)}
              </>
            ) : (
              t(`account.data.${flow}.confirm`)
            )}
          </Button>
          <Button type="button" variant="muted" size="sm" disabled={submitting} onClick={() => onOpenChange(false)}>
            {t(`account.data.${flow}.cancel`)}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

function handleLifecycleError(
  err: unknown,
  form: ReturnType<typeof useForm<LifecycleInput>>,
  flow: LifecycleFlow,
) {
  if (err instanceof ApiClientError) {
    if (err.code === AuthErrorCode.ValidationFailed && err.details) {
      const known: (keyof LifecycleInput)[] = ['password', 'reason'];
      let mapped = false;
      for (const [field, messages] of Object.entries(err.details)) {
        if ((known as string[]).includes(field) && messages?.length) {
          form.setError(field as keyof LifecycleInput, {
            type: 'server',
            message: messages[0],
          });
          mapped = true;
        }
      }
      if (mapped) return;
    }

    if (err.code === UserErrorCode.PasswordIncorrect) {
      const msg = t('account.errors.USER_002');
      form.setError('password', { type: 'server', message: msg });
      toast.error(msg);
      return;
    }

    const passwordMissingCode =
      flow === 'deactivate'
        ? UserErrorCode.DeactivationPasswordRequired
        : UserErrorCode.DeletionPasswordRequired;
    if (err.code === passwordMissingCode) {
      const msg = t(`account.errors.${err.code}`);
      form.setError('password', { type: 'server', message: msg });
      toast.error(msg);
      return;
    }

    const fallback =
      translateMaybeKey(`account.errors.${err.code}`) ||
      translateMaybeKey(`auth.errors.${err.code}`) ||
      err.message;
    toast.error(fallback);
    return;
  }
  toast.error(t('auth.errors.unknown'));
}
