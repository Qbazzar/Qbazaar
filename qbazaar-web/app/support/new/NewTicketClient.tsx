'use client';

/**
 * New support ticket form. No Figma frame: the add/edit form of the account
 * Billing Info panel (412:10263, 563:27380, 614:28114) in a white panel.
 *
 * - Signed-in users: subject, category and details (the backend reads email
 *   and name from the session); the mutation hook opens the new ticket.
 * - Guests: the same plus an email, then a confirmation with the ticket id to
 *   quote when they reply by email.
 */
import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { CircleCheck, LoaderCircle } from 'lucide-react';
import { toast } from 'sonner';
import { z } from 'zod';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Card } from '@/components/design-system/Card';
import { Field } from '@/components/design-system/Field';
import { Icon } from '@/components/design-system/Icon';
import { Input, Select, Textarea } from '@/components/design-system/Input';
import { PageShell } from '@/components/design-system/PageShell';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { Turnstile, type TurnstileHandle } from '@/components/auth/Turnstile';
import { useCreateTicketMutation } from '@/lib/queries/support';
import { ApiClientError } from '@/lib/api/auth';
import { AuthErrorCode } from '@/lib/api/types';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { useAuthStore } from '@/store/auth';
import type { MakeSupportTicketRequest, SupportTicket, SupportTicketCategory } from '@/lib/api/types';

const CATEGORIES: readonly SupportTicketCategory[] = [
  'general',
  'billing',
  'technical',
  'abuse',
  'feedback',
  'other',
] as const;

const baseSchema = z.object({
  subject: z
    .string()
    .trim()
    .min(3, 'support.errors.subject_min')
    .max(160, 'support.errors.subject_max'),
  category: z.enum(
    [...CATEGORIES] as [SupportTicketCategory, ...SupportTicketCategory[]],
    { message: 'support.errors.category_required' },
  ),
  body: z
    .string()
    .trim()
    .min(10, 'support.errors.body_min')
    .max(4000, 'support.errors.body_max'),
  email: z
    .string()
    .trim()
    .email('auth.errors.email_invalid')
    .optional()
    .or(z.literal('')),
});

type FormInput = z.input<typeof baseSchema>;
type FormOutput = z.output<typeof baseSchema>;

/** Error text announced as soon as it appears; Field links it to the control. */
function fieldError(message?: string) {
  return message ? <span role="alert">{translateMaybeKey(message)}</span> : undefined;
}

export function NewTicketClient() {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const mutation = useCreateTicketMutation();
  const [anonResult, setAnonResult] = useState<SupportTicket | null>(null);
  const turnstile = useRef<TurnstileHandle>(null);

  const form = useForm<FormInput, unknown, FormOutput>({
    resolver: zodResolver(baseSchema),
    mode: 'onBlur',
    defaultValues: {
      subject: '',
      category: 'general',
      body: '',
      email: '',
    },
  });

  const onSubmit = form.handleSubmit(async (values) => {
    const payload: MakeSupportTicketRequest = {
      subject: values.subject,
      category: values.category,
      body: values.body,
    };
    if (!isAuthenticated && values.email) payload.email = values.email;

    try {
      const turnstileToken = await turnstile.current?.getToken();
      const ticket = await mutation.mutateAsync({ payload, turnstileToken });
      // Signed-in users are sent to /account/support/{id} by the mutation hook.
      if (!isAuthenticated) setAnonResult(ticket);
    } catch (err) {
      handleError(err, form);
    } finally {
      turnstile.current?.reset();
    }
  });

  const breadcrumb = [
    { label: t('home.breadcrumb'), href: '/' },
    { label: t('support.title'), href: '/support' },
    { label: t('support.new_ticket') },
  ];

  if (anonResult) {
    return (
      <PageShell breadcrumb={breadcrumb} title={t('support.new_ticket')}>
        <TicketReceived ticket={anonResult} />
      </PageShell>
    );
  }

  const { errors } = form.formState;
  const submitting = mutation.isPending;

  return (
    <PageShell breadcrumb={breadcrumb} title={t('support.new_ticket')} meta={t('support.new_ticket_subtitle')}>
      <Card large elevated className="max-w-[760px] qb-desktop:p-8">
        <form onSubmit={onSubmit} noValidate aria-busy={submitting} className="flex flex-col gap-5">
          <Field label={t('support.subject_label')} required error={fieldError(errors.subject?.message)}>
            {(control) => (
              <Input
                {...control}
                maxLength={160}
                placeholder={t('support.subject_placeholder')}
                {...form.register('subject')}
              />
            )}
          </Field>

          <Field label={t('support.category_label')} required error={fieldError(errors.category?.message)}>
            {(control) => (
              <Select {...control} {...form.register('category')}>
                {CATEGORIES.map((category) => (
                  <option key={category} value={category}>
                    {t(`support.categories.${category}`)}
                  </option>
                ))}
              </Select>
            )}
          </Field>

          <Field label={t('support.body_label')} required error={fieldError(errors.body?.message)}>
            {(control) => (
              <Textarea
                {...control}
                rows={6}
                maxLength={4000}
                placeholder={t('support.body_placeholder')}
                {...form.register('body')}
              />
            )}
          </Field>

          {!isAuthenticated ? (
            <Field label={t('support.email_label')} hint={t('support.email_hint')} error={fieldError(errors.email?.message)}>
              {(control) => (
                <Input
                  {...control}
                  type="email"
                  inputMode="email"
                  autoComplete="email"
                  dir="ltr"
                  placeholder={t('support.email_placeholder')}
                  {...form.register('email')}
                />
              )}
            </Field>
          ) : null}

          <Turnstile ref={turnstile} />

          <div className="mt-1 grid grid-cols-2 gap-3 qb-tablet:gap-[22px]">
            <Button type="submit" disabled={submitting}>
              {submitting ? <Icon icon={LoaderCircle} className="motion-safe:animate-spin" /> : null}
              {t('support.submit')}
            </Button>
            <Link href="/support" className={buttonVariants({ variant: 'muted' })}>
              {t('common.cancel')}
            </Link>
          </div>
        </form>
      </Card>
    </PageShell>
  );
}

/** Guest confirmation: focus moves here so screen readers hear that the form went through. */
function TicketReceived({ ticket }: { ticket: SupportTicket }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => ref.current?.focus(), []);

  return (
    <div ref={ref} tabIndex={-1} className="outline-none">
      <StatePanel
        icon={<StateIcon icon={CircleCheck} tone="success" />}
        title={t('support.submit_success_title')}
        description={
          <>
            {t('support.submit_success_body')}
            <span className="mt-3 block text-qb-body text-qb-ink">
              {t('support.ticket_reference')}:{' '}
              <span dir="ltr" className="font-medium break-all select-all">
                {ticket.id}
              </span>
            </span>
          </>
        }
        action={
          <div className="flex flex-wrap justify-center gap-3">
            <Link href="/help" className={buttonVariants({ size: 'sm' })}>
              {t('help.back_to_help')}
            </Link>
            <Link href="/" className={buttonVariants({ variant: 'outline', size: 'sm' })}>
              {t('errors.back_home')}
            </Link>
          </div>
        }
      />
    </div>
  );
}

function handleError(
  err: unknown,
  form: ReturnType<typeof useForm<FormInput, unknown, FormOutput>>,
) {
  if (!(err instanceof ApiClientError)) return;
  if (err.code === AuthErrorCode.TurnstileFailed) {
    toast.error(t('auth.errors.TURNSTILE_001'));
    return;
  }
  if (err.code === 'VALIDATION_FAILED' && err.details) {
    const known: (keyof FormInput)[] = ['subject', 'category', 'body', 'email'];
    for (const [field, messages] of Object.entries(err.details)) {
      if ((known as string[]).includes(field) && messages?.length) {
        form.setError(field as keyof FormInput, {
          type: 'server',
          message: translateMaybeKey(messages[0]) || messages[0],
        });
      }
    }
  }
}
