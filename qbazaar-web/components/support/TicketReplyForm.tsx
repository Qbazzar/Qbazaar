'use client';

/**
 * Reply box under a ticket thread, in the chat input's place (365:14788).
 *
 * - RHF + Zod validation (`body` required, max 4000 chars).
 * - Resolved and closed tickets take no replies; a notice says why instead.
 * - Mutation invalidation is handled by `useReplyToTicketMutation`.
 */
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Loader2, Send } from 'lucide-react';
import { z } from 'zod';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Textarea } from '@/components/design-system/Input';
import { fieldErrorText } from '@/components/auth/FieldError';
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

  const form = useForm<ReplyFormInput, unknown, ReplyFormOutput>({
    resolver: zodResolver(replySchema),
    defaultValues: { body: '' },
    mode: 'onSubmit',
  });

  if (TERMINAL_STATUSES.has(status)) {
    return (
      <p className="border-t border-qb-line px-[21px] py-5 text-qb-caption text-qb-ink-body qb-tablet:px-6 qb-desktop:px-10">
        {t('support.ticket_closed_notice', 'لا يمكن الرد على تذكرة مغلقة. افتح تذكرة جديدة.')}
      </p>
    );
  }

  const onSubmit = form.handleSubmit((values) => {
    mutation.mutate(
      { ticketId, body: values.body },
      { onSuccess: () => form.reset({ body: '' }) },
    );
  });

  return (
    <form
      onSubmit={onSubmit}
      noValidate
      className="flex flex-col gap-3 border-t border-qb-line px-[21px] py-5 qb-tablet:px-6 qb-desktop:px-10 qb-desktop:pb-8"
    >
      <Field label={t('support.reply_label', 'إضافة رد')} error={fieldErrorText(form.formState.errors.body?.message)}>
        {(control) => (
          <Textarea
            {...control}
            rows={4}
            maxLength={4000}
            placeholder={t('support.reply_placeholder', 'اكتب ردك هنا…')}
            {...form.register('body')}
          />
        )}
      </Field>
      <div className="flex justify-end">
        <Button type="submit" size="sm" disabled={mutation.isPending}>
          {mutation.isPending ? (
            <Loader2 className="animate-spin" aria-hidden="true" />
          ) : (
            <Send className="rtl:-scale-x-100" aria-hidden="true" />
          )}
          {t('support.send_reply', 'إرسال الرد')}
        </Button>
      </div>
    </form>
  );
}
