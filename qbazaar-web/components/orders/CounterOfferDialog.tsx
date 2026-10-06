'use client';

import { useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { Modal } from '@/components/design-system/Modal';
import type { DealOffer } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { LIMITS, validateAmount, validateText } from '@/lib/orders/validation';
import { useCounterOfferMutation } from '@/lib/queries/offers';

import { AmountField } from './AmountField';
import { focusFirstInvalid } from './focus-invalid';
import { FormError, NoteField } from './NoteField';

interface CounterOfferDialogProps {
  offer: DealOffer;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** The responder answers a pending offer with a new price (`POST /offers/{id}/counter`). */
export function CounterOfferDialog({ offer, open, onOpenChange }: CounterOfferDialogProps) {
  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title={t('orders.card.counter_dialog.title')}
      description={t('orders.card.counter_dialog.description')}
      showCloseButton
    >
      <CounterOfferForm offer={offer} onDone={() => onOpenChange(false)} />
    </Modal>
  );
}

interface FormErrors {
  amount?: string;
  note?: string;
  form?: string;
}

function CounterOfferForm({ offer, onDone }: { offer: DealOffer; onDone: () => void }) {
  const counter = useCounterOfferMutation();
  const formRef = useRef<HTMLFormElement>(null);
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [errors, setErrors] = useState<FormErrors>({});

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const checked = validateAmount(amount, { min: LIMITS.offerMin, max: LIMITS.offerMax, currency: offer.currency });
    const noteError = validateText(note, { max: LIMITS.noteMax });
    if (checked.amount === null || noteError) {
      setErrors({ amount: checked.error ?? undefined, note: noteError ?? undefined });
      focusFirstInvalid(formRef.current);
      return;
    }

    try {
      await counter.mutateAsync({ offerId: offer.id, payload: { amount: checked.amount, note: note.trim() || null } });
      toast.success(t('orders.card.toast.offer_countered'));
      onDone();
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const fields = fieldErrors(error);
      const next: FormErrors = { amount: fields.amount, note: fields.note };
      if (!next.amount && !next.note) next.form = dealErrorMessage(error);
      setErrors(next);
      focusFirstInvalid(formRef.current);
    }
  };

  return (
    <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-4 text-start">
      <AmountField
        label={t('orders.card.counter_dialog.amount')}
        value={amount}
        onChange={setAmount}
        error={errors.amount}
        currency={offer.currency}
        required
        autoFocus
      />
      <NoteField
        label={t('orders.card.counter_dialog.message')}
        value={note}
        onChange={setNote}
        max={LIMITS.noteMax}
        error={errors.note}
      />
      <FormError>{errors.form}</FormError>
      <div className="mt-2 flex flex-col-reverse gap-3 qb-tablet:flex-row">
        <Button variant="muted" fullWidth onClick={onDone}>
          {t('orders.common.cancel')}
        </Button>
        <Button type="submit" fullWidth disabled={counter.isPending} aria-busy={counter.isPending}>
          {t('orders.card.counter_dialog.submit')}
        </Button>
      </div>
    </form>
  );
}
