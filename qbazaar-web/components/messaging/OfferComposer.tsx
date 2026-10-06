'use client';

/**
 * Buyer-side offer composer.
 *
 * Renders a brand outline trigger ("اعرض سعر") that opens a dialog with a
 * single amount + optional note form. On submit we call
 * `useMakeOfferMutation`; the resulting offer arrives in the timeline via
 * the messages query invalidation (the backend auto-creates an offer
 * Message). The composer is only mounted on the buyer side — the parent
 * ChatInput gates rendering via the `viewerRole` prop.
 */
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Handshake, Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input, Textarea } from '@/components/design-system/Input';
import { Modal } from '@/components/design-system/Modal';
import { announcedError } from '@/components/auth/FieldError';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { useMakeOfferMutation } from '@/lib/queries/offers';
import { AuthErrorCode } from '@/lib/api/types';
import { ApiClientError } from '@/lib/api/auth';

const NOTE_MAX = 280;

const schema = z.object({
  // `valueAsNumber` on the <Input> below produces a real number (or NaN when
  // the field is empty), so we validate as a number directly without coercion.
  amount: z
    .number({ message: 'messaging.offer.errors.amount_required' })
    .finite('messaging.offer.errors.amount_required')
    .gt(0, 'messaging.offer.errors.amount_required'),
  note: z
    .string()
    .max(NOTE_MAX, 'messaging.offer.errors.note_max')
    .optional()
    .or(z.literal('')),
});

type FormValues = z.infer<typeof schema>;

interface Props {
  conversationId: string;
}

export function OfferComposer({ conversationId }: Props) {
  const [open, setOpen] = useState(false);
  const mutation = useMakeOfferMutation();

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { amount: undefined as unknown as number, note: '' },
  });

  const handleClose = (next: boolean) => {
    setOpen(next);
    if (!next) form.reset({ amount: undefined as unknown as number, note: '' });
  };

  const onSubmit = form.handleSubmit((values) => {
    const note = (values.note ?? '').trim();
    mutation.mutate(
      {
        conversationId,
        payload: { amount: values.amount, note: note.length > 0 ? note : null },
      },
      {
        onSuccess: () => {
          toast.success(
            t('messaging.offer.success_toast', 'تم إرسال العرض بنجاح'),
          );
          handleClose(false);
        },
        onError: (err) => {
          if (!(err instanceof ApiClientError)) {
            toast.error(t('common.error', 'حدث خطأ، حاول مرة أخرى'));
            return;
          }
          // Friendly toasts for the well-known offer error codes.
          switch (err.code) {
            case AuthErrorCode.PhoneNotVerified:
              // The API client already routes to phone verification.
              handleClose(false);
              return;
            case 'OFFER_ACTIVE_EXISTS':
              toast.error(
                t(
                  'messaging.offer.errors.active_exists',
                  'لديك عرض مفتوح بالفعل',
                ),
              );
              return;
            case 'OFFER_OWN_AD':
              toast.error(
                t(
                  'messaging.offer.errors.own_ad',
                  'لا يمكنك تقديم عرض على إعلانك',
                ),
              );
              return;
            case 'OFFER_AD_NOT_ACTIVE':
              toast.error(
                t(
                  'messaging.offer.errors.ad_not_active',
                  'الإعلان لم يعد متاحاً لاستقبال العروض',
                ),
              );
              return;
            case 'VALIDATION_FAILED':
              toast.error(
                translateMaybeKey(err.messageKey) ||
                  t('common.error', 'حدث خطأ، حاول مرة أخرى'),
              );
              return;
            default:
              toast.error(
                translateMaybeKey(err.messageKey) ||
                  t('common.error', 'حدث خطأ، حاول مرة أخرى'),
              );
          }
        },
      },
    );
  });

  const errors = form.formState.errors;

  return (
    <Modal
      open={open}
      onOpenChange={handleClose}
      title={t('messaging.offer.make', 'اعرض سعر')}
      description={t(
        'messaging.offer.dialog_description',
        'اقترح سعراً مناسباً، وسيقوم البائع بقبوله أو رفضه.',
      )}
      trigger={
        <Button
          variant="secondary"
          size="icon"
          className="size-10 shrink-0 rounded-qb-md"
          aria-label={t('messaging.offer.make', 'اعرض سعر')}
        >
          <Handshake aria-hidden="true" />
        </Button>
      }
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-5 text-start" aria-busy={mutation.isPending}>
        <Field
          id="offer-amount"
          label={t('messaging.offer.amount_label', 'المبلغ (QAR)')}
          required
          error={announcedError(errors.amount?.message)}
        >
          {(control) => (
            <Input
              {...control}
              type="number"
              inputMode="decimal"
              step="1"
              min={1}
              autoFocus
              dir="ltr"
              placeholder={t('messaging.offer.amount_placeholder', 'مثلاً: 1500')}
              {...form.register('amount', { valueAsNumber: true })}
            />
          )}
        </Field>

        <Field
          id="offer-note"
          label={t('messaging.offer.note_label', 'ملاحظة (اختياري)')}
          error={announcedError(errors.note?.message)}
        >
          {(control) => (
            <Textarea
              {...control}
              rows={3}
              maxLength={NOTE_MAX}
              placeholder={t('messaging.offer.note_placeholder', 'أضف ملاحظة موجزة للبائع…')}
              {...form.register('note')}
            />
          )}
        </Field>

        <div className="mt-1 grid grid-cols-2 gap-3 qb-tablet:gap-5">
          <Button type="submit" size="sm" disabled={mutation.isPending}>
            {mutation.isPending ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {t('messaging.offer.submit', 'إرسال العرض')}
          </Button>
          <Button type="button" variant="muted" size="sm" onClick={() => handleClose(false)}>
            {t('messaging.offer.cancel', 'إلغاء')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
