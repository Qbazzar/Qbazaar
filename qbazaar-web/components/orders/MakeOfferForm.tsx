'use client';

import { useRef, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

import { buttonVariants } from '@/components/design-system/Button';
import { Notice } from '@/components/design-system/Notice';
import { focusRing } from '@/components/design-system/focus-ring';
import { useAuth } from '@/hooks/useAuth';
import { usePhoneVerificationGate } from '@/hooks/usePhoneVerificationGate';
import type { DealAd } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { formatListPrice, normalizeAmountInput } from '@/lib/orders/money';
import { offerBlocker, suggestedOffers } from '@/lib/orders/offers';
import { LIMITS, validateAmount, validateText } from '@/lib/orders/validation';
import { useStartConversationMutation } from '@/lib/queries/messaging';
import { useMakeOfferMutation } from '@/lib/queries/offers';
import { cn } from '@/lib/utils';

import { AmountField } from './AmountField';
import { DealActions, DealPanel, HowItWorks, InfoHint, dealButton } from './DealFormParts';
import { focusFirstInvalid } from './focus-invalid';
import { FormError, NoteField } from './NoteField';

interface FormErrors {
  amount?: string;
  note?: string;
  form?: string;
}

/**
 * Make an Offer (657:57378): the buyer proposes a price. The offer opens (or
 * reuses) their conversation with the seller and lands there as a card.
 */
export function MakeOfferForm({ ad }: { ad: DealAd }) {
  const router = useRouter();
  const { user } = useAuth();
  const { status: gateStatus, ensureVerifiedPhone } = usePhoneVerificationGate();
  const startConversation = useStartConversationMutation();
  const makeOffer = useMakeOfferMutation();
  const formRef = useRef<HTMLFormElement>(null);
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [errors, setErrors] = useState<FormErrors>({});

  const adHref = `/ads/${encodeURIComponent(ad.id)}`;
  const blocker = offerBlocker(ad, user?.id);
  const suggestions = suggestedOffers(ad.price);
  const busy = startConversation.isPending || makeOffer.isPending || gateStatus === 'loading';

  if (blocker) {
    return (
      <DealPanel title={t('orders.deal.offer_title')} titleId="offer-title">
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
    const checked = validateAmount(amount, { min: LIMITS.offerMin, max: LIMITS.offerMax, currency: ad.currency });
    const noteError = validateText(note, { max: LIMITS.noteMax });
    if (checked.amount === null || noteError) {
      setErrors({ amount: checked.error ?? undefined, note: noteError ?? undefined });
      focusFirstInvalid(formRef.current);
      return;
    }
    if (!ensureVerifiedPhone()) return;

    try {
      const conversation = await startConversation.mutateAsync(ad.id);
      await makeOffer.mutateAsync({ conversationId: conversation.id, payload: { amount: checked.amount, note: note.trim() || null } });
      toast.success(t('orders.deal.offer_sent'));
      router.push(`/account/messages?c=${encodeURIComponent(conversation.id)}`);
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const fields = fieldErrors(error);
      const next: FormErrors = { amount: fields.amount, note: fields.note };
      if (!next.amount && !next.note) next.form = dealErrorMessage(error);
      setErrors(next);
      focusFirstInvalid(formRef.current);
    }
  };

  const selected = normalizeAmountInput(amount);

  return (
    <DealPanel title={t('orders.deal.offer_title')} titleId="offer-title">
      <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-4">
        <AmountField
          label={t('orders.deal.your_offer')}
          value={amount}
          onChange={setAmount}
          error={errors.amount}
          currency={ad.currency}
          required
        />
        {suggestions.length > 0 ? (
          <div role="group" aria-label={t('orders.deal.suggested')} className="flex flex-wrap gap-2.5">
            {suggestions.map((suggestion) => {
              const label = formatListPrice(Number(suggestion.amount), ad.currency);
              return (
                <button
                  key={suggestion.amount}
                  type="button"
                  aria-pressed={selected === suggestion.amount}
                  aria-label={t('orders.deal.suggested_label', { amount: label, percent: suggestion.percent })}
                  onClick={() => setAmount(suggestion.amount.replace(/\.00$/, ''))}
                  className={cn(
                    'h-[47px] rounded-qb-lg border border-qb-line bg-qb-surface px-4 text-qb-caption font-medium text-qb-ink transition-colors hover:bg-qb-hover qb-desktop:px-6 qb-desktop:text-qb-body',
                    'aria-pressed:border-qb-brand aria-pressed:bg-qb-brand-soft aria-pressed:text-qb-brand-active',
                    focusRing,
                  )}
                >
                  {label}
                </button>
              );
            })}
          </div>
        ) : null}

        <NoteField
          label={t('orders.deal.message')}
          value={note}
          onChange={setNote}
          max={LIMITS.noteMax}
          error={errors.note}
          placeholder={t('orders.deal.message_placeholder')}
        />
        <InfoHint>{t('orders.deal.offer_hint')}</InfoHint>

        <HowItWorks
          title={t('orders.deal.how_it_works')}
          steps={[t('orders.deal.offer_step_1'), t('orders.deal.offer_step_2'), t('orders.deal.offer_step_3')]}
        />

        <FormError>{errors.form}</FormError>
        <DealActions submitLabel={t('orders.deal.send_offer')} busy={busy} cancelLabel={t('orders.common.cancel')} cancelHref={adHref} />
      </form>
    </DealPanel>
  );
}
