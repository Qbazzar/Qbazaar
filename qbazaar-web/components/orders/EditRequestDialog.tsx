'use client';

import { useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { Modal } from '@/components/design-system/Modal';
import { ApiClientError } from '@/lib/api/auth';
import type { PurchaseRequest } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { LIMITS, validateQuantity, validateText } from '@/lib/orders/validation';
import { useUpdatePurchaseRequestMutation } from '@/lib/queries/purchase-requests';

import { focusFirstInvalid } from './focus-invalid';
import { FormError, NoteField } from './NoteField';

interface EditRequestDialogProps {
  request: PurchaseRequest;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** The buyer changes the quantity or the message of a pending request; the price stays the ad's. */
export function EditRequestDialog({ request, open, onOpenChange }: EditRequestDialogProps) {
  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title={t('orders.card.edit_dialog.title')}
      description={t('orders.card.edit_dialog.description')}
      showCloseButton
    >
      <EditRequestForm request={request} onDone={() => onOpenChange(false)} />
    </Modal>
  );
}

interface FormErrors {
  quantity?: string;
  note?: string;
  form?: string;
}

function EditRequestForm({ request, onDone }: { request: PurchaseRequest; onDone: () => void }) {
  const update = useUpdatePurchaseRequestMutation();
  const formRef = useRef<HTMLFormElement>(null);
  const [quantity, setQuantity] = useState(String(request.quantity));
  const [note, setNote] = useState(request.note ?? '');
  const [errors, setErrors] = useState<FormErrors>({});

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const checkedQuantity = validateQuantity(quantity, LIMITS.quantityMax);
    const noteError = validateText(note, { max: LIMITS.noteMax });
    if (checkedQuantity.quantity === null || noteError) {
      setErrors({ quantity: checkedQuantity.error ?? undefined, note: noteError ?? undefined });
      focusFirstInvalid(formRef.current);
      return;
    }

    try {
      await update.mutateAsync({ id: request.id, payload: { quantity: checkedQuantity.quantity, note: note.trim() || null } });
      toast.success(t('orders.card.toast.request_updated'));
      onDone();
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const fields = fieldErrors(error);
      const quantityError = error instanceof ApiClientError && error.code === 'PURCHASE_007' ? dealErrorMessage(error) : fields.quantity;
      const next: FormErrors = { quantity: quantityError, note: fields.note };
      if (!next.quantity && !next.note) next.form = dealErrorMessage(error);
      setErrors(next);
      focusFirstInvalid(formRef.current);
    }
  };

  return (
    <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-4 text-start">
      <Field label={t('orders.card.edit_dialog.quantity')} error={errors.quantity} required>
        {(control) => (
          <Input
            {...control}
            inputMode="numeric"
            autoComplete="off"
            value={quantity}
            onChange={(event) => setQuantity(event.target.value)}
            className="h-12 rounded-qb-lg"
          />
        )}
      </Field>
      <NoteField
        label={t('orders.card.edit_dialog.message')}
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
        <Button type="submit" fullWidth disabled={update.isPending} aria-busy={update.isPending}>
          {t('orders.card.edit_dialog.save')}
        </Button>
      </div>
    </form>
  );
}
