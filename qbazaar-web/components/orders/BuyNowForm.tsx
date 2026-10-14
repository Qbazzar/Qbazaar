'use client';

import { useId, useRef, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { buttonVariants } from '@/components/design-system/Button';
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

import { DealActions, DealPanel, HowItWorks, InfoHint, dealLabelClass } from './DealFormParts';
import { focusFirstInvalid } from './focus-invalid';
import { FormError, NoteField } from './NoteField';

interface FormErrors {
  quantity?: string;
  note?: string;
  form?: string;
}

/**
 * Buy Now (buy-now.html, 659:58417): the buyer asks to buy at the ad's price. The request is
 * posted as a card in their chat with the seller, where the seller accepts or declines it; no
 * money moves here.
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
        <div className="flex flex-col items-start gap-4">
          <Notice tone={blocker === 'own_ad' ? 'brand' : 'neutral'} role="status">
            {t(`orders.deal.${blocker}`)}
          </Notice>
          <Link href={adHref} className={buttonVariants({ variant: 'outline' })}>
            {t('orders.deal.back_to_ad')}
          </Link>
        </div>
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
      <form ref={formRef} onSubmit={submit} noValidate>
        {/* "Contact Details" and its pill take the place of the offer's amount (buynow.js). */}
        <div>
          <p className="mb-2.5 text-qb-body-sm font-medium text-qb-ink-body">{t('orders.deal.contact_details')}</p>
          <p className="rounded-qb-md border border-qb-brand bg-qb-brand-soft px-[18px] py-[15px] text-qb-body-sm font-medium text-qb-brand">
            {t('orders.deal.contact_chat')}
          </p>
        </div>

        {available > 1 ? (
          <QuantityField available={available} value={quantity} onChange={setQuantity} error={errors.quantity} />
        ) : null}

        <NoteField
          label={t('orders.deal.message')}
          value={note}
          onChange={setNote}
          max={LIMITS.noteMax}
          error={errors.note}
          placeholder={t('orders.deal.message_placeholder')}
          textareaClassName="mb-4"
        />
        <InfoHint>{t('orders.deal.buy_hint')}</InfoHint>

        <HowItWorks
          title={t('orders.deal.how_it_works')}
          steps={[t('orders.deal.buy_step_1'), t('orders.deal.buy_step_2'), t('orders.deal.buy_step_3')]}
        />

        {errors.form ? (
          <div className="mb-4">
            <FormError>{errors.form}</FormError>
          </div>
        ) : null}
        <DealActions
          submitLabel={t('orders.deal.send_request')}
          busy={create.isPending || gateStatus === 'loading'}
          cancelLabel={t('common.cancel')}
          cancelHref={adHref}
        />
      </form>
    </DealPanel>
  );
}

/** How many to buy, when the ad has more than one: the offer page's r10 box, 160 px wide. */
function QuantityField({
  available,
  value,
  onChange,
  error,
}: {
  available: number;
  value: string;
  onChange: (value: string) => void;
  error: string | undefined;
}) {
  const id = useId();
  const hintId = `${id}-hint`;
  const errorId = error ? `${id}-error` : undefined;
  return (
    <div className="mt-[22px] mb-[22px]">
      <label htmlFor={id} className={dealLabelClass}>
        {t('orders.deal.quantity')}
        <span aria-hidden="true" className="text-qb-brand">
          {' *'}
        </span>
      </label>
      <Input
        id={id}
        inputMode="numeric"
        autoComplete="off"
        required
        aria-invalid={error ? true : undefined}
        aria-describedby={[hintId, errorId].filter(Boolean).join(' ')}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="h-auto max-w-40 p-3.5 text-qb-body-sm text-qb-black"
      />
      <p id={hintId} className="mt-2 text-qb-caption text-qb-ink-subtle">
        {t('orders.deal.quantity_available', { count: available })}
      </p>
      {error ? (
        <p id={errorId} role="alert" className="mt-2 text-qb-caption text-qb-danger">
          {error}
        </p>
      ) : null}
    </div>
  );
}
