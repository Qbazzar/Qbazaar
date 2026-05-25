'use client';

/**
 * New support ticket form.
 *
 * - Auth users: subject + category + body (the backend reads email + name
 *   from the session). On success the mutation hook navigates to the new
 *   ticket detail.
 * - Anonymous users: same fields plus an email input. On success we render
 *   a confirmation card with the assigned ticket id so they can reference it
 *   when replying via email.
 */
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Loader2Icon } from 'lucide-react';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { FieldError } from '@/components/auth/FieldError';
import { useCreateTicketMutation } from '@/lib/queries/support';
import { ApiClientError } from '@/lib/api/auth';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { useAuthStore } from '@/store/auth';
import type {
  MakeSupportTicketRequest,
  SupportTicket,
  SupportTicketCategory,
} from '@/lib/api/types';

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

export function NewTicketClient() {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  const mutation = useCreateTicketMutation();
  const [anonResult, setAnonResult] = useState<SupportTicket | null>(null);

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
      const ticket = await mutation.mutateAsync(payload);
      // Auth users: the mutation hook routes to /account/support/{id}.
      // Anonymous users stay on this page and see the success card.
      if (!isAuthenticated) setAnonResult(ticket);
    } catch (err) {
      handleError(err, form);
    }
  });

  if (anonResult) {
    return <AnonymousSuccessCard ticket={anonResult} />;
  }

  const errors = form.formState.errors;
  const submitting = mutation.isPending;
  const selectedCategory = form.watch('category');

  return (
    <main>
      <div className="container" style={{ maxWidth: 720, paddingTop: 32, paddingBottom: 64 }}>
        <header className="mb-8">
          <h1 className="text-h2 text-ink-900">
            {t('support.new_ticket', 'تواصل مع الدعم')}
          </h1>
          <p className="text-ink-700 mt-2 text-sm leading-relaxed">
            {t(
              'support.new_ticket_subtitle',
              'صف مشكلتك بتفصيل بسيط وسيرد فريق الدعم خلال ساعات قليلة.',
            )}
          </p>
        </header>

        <form onSubmit={onSubmit} noValidate className="space-y-5">
          <div className="space-y-1.5">
            <Label htmlFor="subject">
              {t('support.subject_label', 'الموضوع')}
            </Label>
            <Input
              id="subject"
              maxLength={160}
              placeholder={t('support.subject_placeholder', 'مثلاً: لم أتمكن من نشر إعلان')}
              aria-invalid={Boolean(errors.subject)}
              {...form.register('subject')}
            />
            <FieldError id="subject-error" message={errors.subject?.message} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="category">
              {t('support.category_label', 'التصنيف')}
            </Label>
            <Select
              value={selectedCategory}
              onValueChange={(v) => {
                if (!v) return;
                form.setValue(
                  'category',
                  v as SupportTicketCategory,
                  { shouldValidate: true },
                );
              }}
            >
              <SelectTrigger
                id="category"
                className="w-full rounded-lg"
                aria-invalid={Boolean(errors.category)}
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {CATEGORIES.map((c) => (
                  <SelectItem key={c} value={c}>
                    {t(`support.categories.${c}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FieldError id="category-error" message={errors.category?.message} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="body">{t('support.body_label', 'تفاصيل المشكلة')}</Label>
            <Textarea
              id="body"
              rows={6}
              maxLength={4000}
              placeholder={t(
                'support.body_placeholder',
                'صف المشكلة بدقة. الخطوات التي قمت بها، رسائل الخطأ إن وجدت، ومتى بدأت تواجهها.',
              )}
              aria-invalid={Boolean(errors.body)}
              {...form.register('body')}
            />
            <FieldError id="body-error" message={errors.body?.message} />
          </div>

          {!isAuthenticated ? (
            <div className="space-y-1.5">
              <Label htmlFor="email">
                {t('support.email_label', 'بريدك الإلكتروني')}
              </Label>
              <Input
                id="email"
                type="email"
                inputMode="email"
                autoComplete="email"
                placeholder={t('support.email_placeholder', 'you@example.com')}
                aria-invalid={Boolean(errors.email)}
                {...form.register('email')}
              />
              <FieldError id="email-error" message={errors.email?.message} />
              <p className="text-ink-500 text-xs">
                {t(
                  'support.email_hint',
                  'سنتواصل معك على هذا البريد. سجّل الدخول لمتابعة تذاكرك من حسابك.',
                )}
              </p>
            </div>
          ) : null}

          <Button
            type="submit"
            size="lg"
            disabled={submitting}
            className="bg-coral hover:bg-coral/90 h-11 rounded-full px-6 text-sm font-semibold text-white"
          >
            {submitting ? (
              <Loader2Icon className="size-4 animate-spin" aria-hidden />
            ) : null}
            {t('support.submit', 'إرسال')}
          </Button>
        </form>
      </div>
    </main>
  );
}

function AnonymousSuccessCard({ ticket }: { ticket: SupportTicket }) {
  return (
    <main>
      <div className="container" style={{ maxWidth: 560, paddingTop: 64, paddingBottom: 64 }}>
        <div className="card card--lg text-center">
          <h1 className="text-h2 text-ink-900">
            {t('support.submit_success_title', 'تم استلام رسالتك')}
          </h1>
          <p className="text-ink-700 mt-3 text-sm leading-relaxed">
            {t(
              'support.submit_success_body',
              'سنرد على البريد الذي زوّدتنا به خلال ساعات قليلة. احتفظ برقم التذكرة التالي للمراجعة:',
            )}
          </p>
          <p className="text-ink-900 mt-4 text-sm font-mono">{ticket.id}</p>
        </div>
      </div>
    </main>
  );
}

function handleError(
  err: unknown,
  form: ReturnType<typeof useForm<FormInput, unknown, FormOutput>>,
) {
  if (!(err instanceof ApiClientError)) return;
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
