'use client';

import { useRef, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { buttonVariants } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { Notice } from '@/components/design-system/Notice';
import { useAuth } from '@/hooks/useAuth';
import { usePhoneVerificationGate } from '@/hooks/usePhoneVerificationGate';
import type { DealAd } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { buyNowBlocker } from '@/lib/orders/offers';
import { LIMITS, validateQuantity, validateText } from '@/lib/orders/validation';
import { useCreatePurchaseRequestMutation } from '@/lib/queries/purchase-requests';
import { cn } from '@/lib/utils';

import { DealActions, DealPanel, HowItWorks, InfoHint, dealButton } from './DealFormParts';
import { focusFirstInvalid } from './focus-invalid';
import { FormError, NoteField } from './NoteField';

interface FormErrors {
  quantity?: string;
  note?: string;
  form?: string;
}

/**
 * Buy Now (659:58417): the buyer asks to buy at the ad's price. The request
 * is posted as a card in their chat with the seller, where the seller
 * accepts or declines it; no money moves here.
 */
export function BuyNowForm({ ad }: { ad: DealAd }) {
  const router = useRouter();
  const { user } = useAuth();
  const { status: gateStatus, ensureVerifiedPhone } = usePhoneVerificationGate();
  const create = useCreatePurchaseRequestMutation(ad.id);
  const formRef = useRef<HTMLFormElement>(null);
  const [quantity, setQuantity] = useState('1');
  const [note, setNote] = useState('');
  const [errors, setErrors] = useState<FormErrors>({});

  const adHref = `/ads/${encodeURIComponent(ad.id)}`;
  const available = Math.min(ad.quantity ?? 1, LIMITS.quantityMax);
  const blocker = buyNowBlocker(ad, user?.id);

  if (blocker) {
    return (
      <DealPanel title={t('orders.deal.buy_title')} titleId="buy-now-title">
        <Notice tone={blocker === 'own_ad' ? 'info' : 'neutral'} role="status">
          {t(`orders.deal.${blocker}`)}
        </Notice>
        <Link href={adHref} className={cn(buttonVariants({ variant: 'outline' }), dealButton, 'self-start')}>
          {t('orders.deal.back_to_ad')}
        </Link>
      </DealPanel>
    );
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const checkedQuantity = available > 1 ? validateQuantity(quantity, available) : ({ quantity: 1, error: null } as const);
    const noteError = validateText(note, { max: LIMITS.noteMax });
    if (checkedQuantity.quantity === null || noteError) {
      setErrors({ quantity: checkedQuantity.error ?? undefined, note: noteError ?? undefined });
      focusFirstInvalid(formRef.current);
      return;
    }
    if (!ensureVerifiedPhone()) return;

    try {
      const request = await create.mutateAsync({ quantity: checkedQuantity.quantity, note: note.trim() || null });
      toast.success(t('orders.deal.request_sent'));
      router.push(`/account/messages?c=${encodeURIComponent(request.conversation_id)}`);
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const fields = fieldErrors(error);
      const next: FormErrors = { quantity: fields.quantity, note: fields.note };
      if (!next.quantity && !next.note) next.form = dealErrorMessage(error);
      setErrors(next);
      focusFirstInvalid(formRef.current);
    }
  };

  return (
    <DealPanel title={t('orders.deal.buy_title')} titleId="buy-now-title">
      <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-4">
        <div>
          <p className="text-qb-body text-qb-ink-body">{t('orders.deal.contact_details')}</p>
          <p className="mt-2 rounded-qb-lg border border-qb-brand bg-qb-brand-soft px-4 py-3.5 text-qb-caption text-qb-brand-active">
            {t('orders.deal.contact_chat')}
          </p>
        </div>

        {available > 1 ? (
          <Field
            label={t('orders.deal.quantity')}
            hint={t('orders.deal.quantity_available', { count: available })}
            error={errors.quantity}
            required
          >
            {(control) => (
              <Input
                {...control}
                inputMode="numeric"
                autoComplete="off"
                value={quantity}
                onChange={(event) => setQuantity(event.target.value)}
                className="h-12 max-w-40 rounded-qb-lg"
              />
            )}
          </Field>
        ) : null}

        <NoteField
          label={t('orders.deal.message')}
          value={note}
          onChange={setNote}
          max={LIMITS.noteMax}
          error={errors.note}
          placeholder={t('orders.deal.message_placeholder')}
        />
        <InfoHint>{t('orders.deal.buy_hint')}</InfoHint>

        <HowItWorks
          title={t('orders.deal.how_it_works')}
          steps={[t('orders.deal.buy_step_1'), t('orders.deal.buy_step_2'), t('orders.deal.buy_step_3')]}
        />

        <FormError>{errors.form}</FormError>
        <DealActions
          submitLabel={t('orders.deal.send_request')}
          busy={create.isPending || gateStatus === 'loading'}
          cancelLabel={t('orders.common.cancel')}
          cancelHref={adHref}
        />
      </form>
    </DealPanel>
  );
}
