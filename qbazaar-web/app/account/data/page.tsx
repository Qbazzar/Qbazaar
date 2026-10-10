'use client';

/**
 * The "Delete Account" panel of account.html (Figma 401:10866 / 438:18762):
 * the warning, the "I understand" box, then "Delete My Account", which asks
 * for the password the API requires (`DELETE /account/delete-request`).
 * Under it, deactivating (`POST /account/deactivate`) stays available as the
 * gentler option. "Export my data" lives in Data Protection.
 *
 * After either request the user is signed out and sent to `/login` with a
 * sticky notice (`?deactivated=1` or `?deleted=1`).
 */
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { showDesignToast } from '@/components/design-system/design-toast';
import { Field } from '@/components/design-system/Field';
import { focusRing } from '@/components/design-system/focus-ring';
import { Textarea } from '@/components/design-system/Input';
import { Modal } from '@/components/design-system/Modal';
import { fieldErrorText } from '@/components/auth/FieldError';
import { PasswordInput } from '@/components/auth/PasswordInput';
import { ModalActions } from '@/components/account/ModalActions';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { deactivateAccount, requestAccountDeletion } from '@/lib/api/account';
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

export default function AccountDataPage() {
  return (
    <div className="flex flex-col gap-5 font-qb">
      <DeleteAccountCard />
      <DeactivateAccountLine />
    </div>
  );
}

function DeleteAccountCard() {
  const router = useRouter();
  const { logout } = useAuth();
  const [understood, setUnderstood] = useState(false);
  const [confirmMissing, setConfirmMissing] = useState(false);
  const [open, setOpen] = useState(false);

  return (
    <section
      aria-labelledby="delete-account-title"
      className="rounded-qb-xl border border-qb-acct-delete-line bg-qb-surface p-7"
    >
      <h2 id="delete-account-title" className="mb-3 text-qb-h5 font-semibold tracking-normal text-qb-acct-danger">
        {t('account.data.delete.title')}
      </h2>
      <p className="mb-5 text-qb-body-sm leading-[1.6] text-qb-ink-faint">{t('account.data.delete.body')}</p>
      <label className="mb-5 flex w-fit cursor-pointer items-center gap-2.5 text-qb-body-sm text-qb-ink-body">
        <input
          type="checkbox"
          checked={understood}
          onChange={(event) => {
            setUnderstood(event.target.checked);
            setConfirmMissing(false);
          }}
          aria-invalid={confirmMissing || undefined}
          aria-describedby={confirmMissing ? 'delete-account-confirm-error' : undefined}
          className={cn('ms-1 size-[18px] shrink-0 cursor-pointer accent-qb-acct-danger', focusRing)}
        />
        {t('account.data.delete.understand')}
      </label>
      {confirmMissing ? (
        <p id="delete-account-confirm-error" role="alert" className="-mt-3 mb-5 text-qb-caption text-qb-danger">
          {t('account.data.delete.understand_required')}
        </p>
      ) : null}
      {/* Never disabled, as in the reference: without the tick it asks for it instead. */}
      <button
        type="button"
        onClick={() => (understood ? setOpen(true) : setConfirmMissing(true))}
        className={cn(
          'cursor-pointer rounded-qb-md bg-qb-acct-danger px-8 py-[13px] text-[13.3333px] leading-[1.15] font-semibold text-qb-on-brand hover:brightness-[0.96]',
          focusRing,
        )}
      >
        {t('account.data.delete.submit')}
      </button>

      <LifecycleDialog
        flow="delete"
        open={open}
        onOpenChange={setOpen}
        title={t('account.data.delete.dialog_title')}
        description={t('account.data.delete.dialog_body')}
        reason={null}
        onConfirm={async (values) => {
          await requestAccountDeletion({ password: values.password, reason: values.reason ?? null });
          showDesignToast(t('account.data.delete.scheduled_toast'));
          await logout();
          router.replace('/login?deleted=1');
        }}
      />
    </section>
  );
}

/** Deactivating hides the account until the next sign-in; nothing is deleted. */
function DeactivateAccountLine() {
  const router = useRouter();
  const { logout } = useAuth();
  const [open, setOpen] = useState(false);

  return (
    <>
      <p className="text-qb-caption text-qb-ink-subtle">
        {t('account.data.deactivate.instead')}{' '}
        <button
          type="button"
          onClick={() => setOpen(true)}
          className={cn('cursor-pointer rounded-qb-xs font-medium text-qb-brand underline', focusRing)}
        >
          {t('account.data.deactivate.submit')}
        </button>
      </p>
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
          error={fieldErrorText(errors.password?.message)}
        >
          {(control) => (
            <PasswordInput {...control} autoComplete="current-password" placeholder="••••••••" {...form.register('password')} />
          )}
        </Field>

        {withReason ? (
          <Field
            id={`${prefix}-reason`}
            label={t(`account.data.${flow}.reason_label`)}
            error={fieldErrorText(errors.reason?.message)}
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

        <ModalActions className="mt-1">
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
        </ModalActions>
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
