'use client';

import { useRef, useState, type FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { Crown, Highlighter, Images, Landmark, Rocket, Wallet as WalletIcon } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { Field } from '@/components/design-system/Field';
import { Input } from '@/components/design-system/Input';
import { Notice } from '@/components/design-system/Notice';
import { RadioCard } from '@/components/design-system/RadioCard';
import { AccountPageFrame } from '@/components/orders/AccountPageFrame';
import { CheckoutPanel } from '@/components/orders/CheckoutPanel';
import { focusFirstInvalid } from '@/components/orders/focus-invalid';
import { FormError } from '@/components/orders/NoteField';
import { PageState } from '@/components/orders/PageState';
import { useAuth } from '@/hooks/useAuth';
import { useIdempotencyKey } from '@/hooks/useIdempotencyKey';
import { ApiClientError } from '@/lib/api/auth';
import type { PromotionOffer, PromotionPaymentMethod, PromotionType, Wallet } from '@/lib/api/commerce-types';
import { errorDetail } from '@/lib/api/request';
import type { Ad } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { formatDate } from '@/lib/orders/dates';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { compareAmounts, formatMoney, normalizeAmountInput } from '@/lib/orders/money';
import { LIMITS } from '@/lib/orders/validation';
import { useAdQuery } from '@/lib/queries/ads';
import { usePromotionOffersQuery, usePurchasePromotionMutation } from '@/lib/queries/promotions';
import { useWalletQuery } from '@/lib/queries/wallet';

const TYPE_ICON: Record<PromotionType, typeof Crown> = {
  highlight: Highlighter,
  push_up: Rocket,
  gallery: Images,
  premium: Crown,
};

const TRANSFER_REFERENCE = /^[A-Za-z0-9][A-Za-z0-9 /._-]*$/;

/**
 * `/account/ads/{id}/promote`: buy a promotion for one of the viewer's live
 * ads. `initialType` preselects the promotion ticked while posting the ad.
 */
export function PromoteAdView({ adId, initialType }: { adId: string; initialType?: string }) {
  const { user } = useAuth();
  const ad = useAdQuery(adId);
  const offers = usePromotionOffersQuery();
  const wallet = useWalletQuery();
  const trail = [
    { label: t('orders.promotion.title'), href: '/account/promotions' },
    { label: t('orders.promotion.page_title') },
  ];

  const loading = ad.isPending || offers.isPending || wallet.isPending;
  const failed = ad.isError || offers.isError || wallet.isError;

  return (
    <AccountPageFrame breadcrumb={trail} title={t('orders.promotion.page_title')} description={ad.data?.title}>
      {loading ? (
        <PageState kind="loading" />
      ) : failed ? (
        ad.error?.status === 404 ? (
          <PageState kind="empty" message={t('orders.promotion.not_found')} />
        ) : (
          <PageState
            kind="error"
            onRetry={() => {
              void ad.refetch();
              void offers.refetch();
              void wallet.refetch();
            }}
          />
        )
      ) : ad.data.user_id !== user?.id ? (
        <PageState kind="empty" message={t('orders.promotion.not_found')} />
      ) : ad.data.status !== 'active' ? (
        <Notice tone="neutral" role="status">
          {t('orders.promotion.not_live')}
        </Notice>
      ) : (
        <PromoteForm ad={ad.data} offers={offers.data} wallet={wallet.data} initialType={initialType} />
      )}
    </AccountPageFrame>
  );
}

interface FormErrors {
  reference?: string;
  form?: string;
}

function PromoteForm({ ad, offers, wallet, initialType }: { ad: Ad; offers: PromotionOffer[]; wallet: Wallet; initialType?: string }) {
  const router = useRouter();
  const purchase = usePurchasePromotionMutation(ad.id);
  const { key, renew } = useIdempotencyKey();
  const formRef = useRef<HTMLFormElement>(null);
  const [type, setType] = useState<PromotionType>(
    offers.find((offer) => offer.type === initialType)?.type ?? offers[0]?.type ?? 'highlight',
  );
  const [method, setMethod] = useState<PromotionPaymentMethod>('wallet');
  const [reference, setReference] = useState('');
  const [errors, setErrors] = useState<FormErrors>({});
  const [cap, setCap] = useState<string | null>(null);

  const offer = offers.find((candidate) => candidate.type === type) ?? offers[0];
  if (!offer) return <PageState kind="empty" message={t('orders.errors.generic')} />;

  const withdrawable = cap ?? wallet.withdrawable_balance;
  const walletCovers = compareAmounts(withdrawable, offer.price) >= 0;
  const payByWallet = method === 'wallet' && walletCovers;
  const price = formatMoney(offer.price, offer.currency);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setErrors({});
    const paymentMethod: PromotionPaymentMethod = payByWallet ? 'wallet' : 'bank_transfer';
    if (paymentMethod === 'bank_transfer') {
      const value = reference.trim();
      const referenceError = !value
        ? t('orders.validation.required')
        : value.length > LIMITS.transferReferenceMax
          ? t('orders.validation.max_chars', { max: LIMITS.transferReferenceMax })
          : !TRANSFER_REFERENCE.test(value)
            ? t('orders.promotion.reference_format')
            : null;
      if (referenceError) {
        setErrors({ reference: referenceError });
        focusFirstInvalid(formRef.current);
        return;
      }
    }

    try {
      const promotion = await purchase.mutateAsync({
        payload: { type: offer.type, payment_method: paymentMethod, transfer_reference: paymentMethod === 'bank_transfer' ? reference.trim() : null },
        idempotencyKey: key,
      });
      renew();
      toast.success(
        promotion.status === 'active' && promotion.ends_at
          ? t('orders.promotion.toast_active', { date: formatDate(promotion.ends_at) })
          : t('orders.promotion.toast_pending'),
      );
      router.push('/account/promotions');
    } catch (error) {
      if (isHandledGlobally(error)) return;
      if (error instanceof ApiClientError && error.code === 'WALLET_003') {
        const detail = errorDetail(error, 'withdrawable');
        setCap(detail ? (normalizeAmountInput(detail) ?? wallet.withdrawable_balance) : '0.00');
        setMethod('bank_transfer');
        return;
      }
      const fields = fieldErrors(error);
      if (fields.transfer_reference) {
        setErrors({ reference: fields.transfer_reference });
        focusFirstInvalid(formRef.current);
        return;
      }
      setErrors({ form: dealErrorMessage(error) });
    }
  };

  return (
    <form ref={formRef} onSubmit={submit} noValidate className="flex flex-col gap-6">
      <CheckoutPanel title={t('orders.promotion.choose')} titleId="promote-type">
        <div role="radiogroup" aria-labelledby="promote-type" className="[display:grid] grid-cols-1 gap-3 qb-desktop:grid-cols-2 qb-desktop:gap-5">
          {offers.map((candidate) => {
            const TypeIcon = TYPE_ICON[candidate.type];
            return (
              <RadioCard
                key={candidate.type}
                name="promotion_type"
                value={candidate.type}
                checked={type === candidate.type}
                onChange={() => setType(candidate.type)}
                icon={<TypeIcon />}
                label={
                  <span className="flex flex-wrap items-baseline justify-between gap-x-3">
                    <span>{t(`orders.promotion.types.${candidate.type}`)}</span>
                    <span className="text-qb-caption font-semibold text-qb-ink">
                      {formatMoney(candidate.price, candidate.currency)} · {tPlural('orders.promotion.duration', candidate.duration_days)}
                    </span>
                  </span>
                }
                description={t(`orders.promotion.types.${candidate.type}_body`)}
              />
            );
          })}
        </div>
      </CheckoutPanel>

      <CheckoutPanel title={t('orders.promotion.pay_with')} titleId="promote-pay">
        <div className="flex flex-col gap-4">
          <div role="radiogroup" aria-labelledby="promote-pay" className="[display:grid] grid-cols-1 gap-3 qb-desktop:grid-cols-2 qb-desktop:gap-5">
            <RadioCard
              name="payment_method"
              value="wallet"
              checked={payByWallet}
              disabled={!walletCovers}
              onChange={() => setMethod('wallet')}
              icon={<WalletIcon />}
              label={t('orders.promotion.wallet')}
              description={t('orders.promotion.wallet_hint', { amount: formatMoney(withdrawable, wallet.currency) })}
            />
            <RadioCard
              name="payment_method"
              value="bank_transfer"
              checked={!payByWallet}
              onChange={() => setMethod('bank_transfer')}
              icon={<Landmark />}
              label={t('orders.promotion.bank_transfer')}
              description={t('orders.promotion.bank_hint')}
            />
          </div>
          {!walletCovers ? (
            <Notice tone="info" role="status">
              {t('orders.promotion.cap', { amount: formatMoney(withdrawable, wallet.currency) })}
            </Notice>
          ) : null}
          {!payByWallet ? (
            <Field label={t('orders.promotion.reference')} error={errors.reference} required className="max-w-md">
              {(control) => (
                <Input
                  {...control}
                  dir="ltr"
                  autoComplete="off"
                  maxLength={LIMITS.transferReferenceMax}
                  placeholder={t('orders.promotion.reference_placeholder')}
                  value={reference}
                  onChange={(event) => setReference(event.target.value)}
                />
              )}
            </Field>
          ) : null}
          <FormError>{errors.form}</FormError>
          <Button type="submit" className="h-11 self-start rounded-qb-sm" disabled={purchase.isPending} aria-busy={purchase.isPending}>
            {t('orders.promotion.submit', { price })}
          </Button>
        </div>
      </CheckoutPanel>
    </form>
  );
}
