'use client';

/**
 * Reply form attached to the bottom of a ticket timeline.
 *
 * - RHF + Zod validation (`body` required, max 4000 chars).
 * - Disabled when the ticket is `resolved`/`closed`; we still render an
 *   info notice so the user knows why.
 * - Mutation invalidation is handled by `useReplyToTicketMutation`.
 */
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Loader2Icon, SendIcon } from 'lucide-react';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FieldError } from '@/components/auth/FieldError';
import { useReplyToTicketMutation } from '@/lib/queries/support';
import { t } from '@/lib/i18n/messages';
import type { SupportTicketStatus } from '@/lib/api/types';

const TERMINAL_STATUSES: ReadonlySet<SupportTicketStatus> = new Set([
  'resolved',
  'closed',
]);

const replySchema = z.object({
  body: z
    .string()
    .trim()
    .min(1, 'support.errors.body_required')
    .max(4000, 'support.errors.body_max'),
});

type ReplyFormInput = z.input<typeof replySchema>;
type ReplyFormOutput = z.output<typeof replySchema>;

interface Props {
  ticketId: string;
  status: SupportTicketStatus;
}

export function TicketReplyForm({ ticketId, status }: Props) {
  const mutation = useReplyToTicketMutation();
  const locked = TERMINAL_STATUSES.has(status);

  const form = useForm<ReplyFormInput, unknown, ReplyFormOutput>({
    resolver: zodResolver(replySchema),
    defaultValues: { body: '' },
    mode: 'onSubmit',
  });

  const onSubmit = form.handleSubmit((values) => {
    mutation.mutate(
      { ticketId, body: values.body },
      {
        onSuccess: () => {
          form.reset({ body: '' });
        },
      },
    );
  });

  if (locked) {
    return (
      <div className="ring-ink-200 bg-cream-100 text-ink-700 mt-6 rounded-2xl p-5 text-sm leading-relaxed ring-1">
        {t(
          'support.ticket_closed_notice',
          'لا يمكن الرد على تذكرة مغلقة. افتح تذكرة جديدة.',
        )}
      </div>
    );
  }

  const bodyError = form.formState.errors.body?.message;

  return (
    <form onSubmit={onSubmit} noValidate className="mt-6 space-y-3">
      <div className="space-y-1.5">
        <Label htmlFor="ticket-reply">
          {t('support.reply_label', 'إضافة رد')}
        </Label>
        <Textarea
          id="ticket-reply"
          rows={4}
          maxLength={4000}
          placeholder={t('support.reply_placeholder', 'اكتب ردك هنا…')}
          aria-invalid={Boolean(bodyError)}
          aria-describedby={bodyError ? 'ticket-reply-error' : undefined}
          {...form.register('body')}
        />
        <FieldError id="ticket-reply-error" message={bodyError} />
      </div>
      <div className="flex justify-end">
        <Button
          type="submit"
          size="lg"
          disabled={mutation.isPending}
          className="bg-coral hover:bg-coral/90 h-11 rounded-full px-6 text-sm font-semibold text-white"
        >
          {mutation.isPending ? (
            <Loader2Icon className="size-4 animate-spin" aria-hidden />
          ) : (
            <SendIcon className="size-4" aria-hidden />
          )}
          {t('support.send_reply', 'إرسال الرد')}
        </Button>
      </div>
    </form>
  );
}
