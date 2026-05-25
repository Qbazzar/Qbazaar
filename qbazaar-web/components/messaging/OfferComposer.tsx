'use client';

/**
 * Buyer-side offer composer.
 *
 * Renders a coral outline trigger ("اعرض سعر") that opens a Dialog with a
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
import { HandshakeIcon, Loader2Icon } from 'lucide-react';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FieldError } from '@/components/auth/FieldError';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { useMakeOfferMutation } from '@/lib/queries/offers';
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

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogTrigger
        render={
          <Button
            type="button"
            variant="outline"
            size="icon"
            className="border-coral text-coral hover:bg-coral/10 shrink-0 rounded-full"
            aria-label={t('messaging.offer.make', 'اعرض سعر')}
          >
            <HandshakeIcon className="size-4" aria-hidden />
          </Button>
        }
      />
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {t('messaging.offer.make', 'اعرض سعر')}
          </DialogTitle>
          <DialogDescription>
            {t(
              'messaging.offer.dialog_description',
              'اقترح سعراً مناسباً، وسيقوم البائع بقبوله أو رفضه.',
            )}
          </DialogDescription>
        </DialogHeader>

        <form
          onSubmit={onSubmit}
          className="flex flex-col gap-4"
          aria-busy={mutation.isPending}
        >
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="offer-amount">
              {t('messaging.offer.amount_label', 'المبلغ (QAR)')}
            </Label>
            <Input
              id="offer-amount"
              type="number"
              inputMode="decimal"
              step="1"
              min={1}
              autoFocus
              placeholder={t(
                'messaging.offer.amount_placeholder',
                'مثلاً: 1500',
              )}
              aria-invalid={Boolean(form.formState.errors.amount)}
              {...form.register('amount', { valueAsNumber: true })}
            />
            <FieldError
              id="offer-amount-error"
              message={form.formState.errors.amount?.message}
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <Label htmlFor="offer-note">
              {t('messaging.offer.note_label', 'ملاحظة (اختياري)')}
            </Label>
            <Textarea
              id="offer-note"
              rows={3}
              maxLength={NOTE_MAX}
              placeholder={t(
                'messaging.offer.note_placeholder',
                'أضف ملاحظة موجزة للبائع…',
              )}
              aria-invalid={Boolean(form.formState.errors.note)}
              {...form.register('note')}
            />
            <FieldError
              id="offer-note-error"
              message={form.formState.errors.note?.message}
            />
          </div>

          <DialogFooter>
            <DialogClose
              render={
                <Button type="button" variant="outline" className="rounded-full">
                  {t('messaging.offer.cancel', 'إلغاء')}
                </Button>
              }
            />
            <Button
              type="submit"
              disabled={mutation.isPending}
              className="bg-coral hover:bg-coral/90 rounded-full text-white"
            >
              {mutation.isPending ? (
                <Loader2Icon className="size-4 animate-spin" aria-hidden />
              ) : null}
              {t('messaging.offer.submit', 'إرسال العرض')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
