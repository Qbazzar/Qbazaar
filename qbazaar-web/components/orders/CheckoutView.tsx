'use client';

import { useMemo, useRef, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Banknote, CheckCircle2, Loader2, Store, Truck, XCircle } from 'lucide-react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { Button, buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import { Notice } from '@/components/design-system/Notice';
import { RadioCard } from '@/components/design-system/RadioCard';
import { useAuth } from '@/hooks/useAuth';
import { useIdempotencyKey } from '@/hooks/useIdempotencyKey';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { ApiClientError } from '@/lib/api/auth';
import type { Checkout, CheckoutAddressInput, CheckoutPayload, Fulfillment } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { EMPTY_ADDRESS, toAddressPayload, validateAddress, type AddressField } from '@/lib/orders/address';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney, isPositiveAmount } from '@/lib/orders/money';
import { useCheckoutQuery, useSubmitCheckoutMutation } from '@/lib/queries/orders';
import { cn } from '@/lib/utils';

import { pageFrame } from './AccountPageFrame';
import { AddressPicker, type AddressChoice } from './AddressPicker';
import { CheckoutPanel } from './CheckoutPanel';
import { focusFirstInvalid } from './focus-invalid';
import { FormError } from './NoteField';
import { OrderSummaryCard, type SummaryLine } from './OrderSummaryCard';
import { PageState } from './PageState';
import { orderNumber } from './order-number';

const ctaButton = 'h-10 rounded-qb-sm text-qb-caption';

/** `/checkout/{orderId}`: the buyer picks pickup or delivery and confirms a cash order (682:32513). */
export function CheckoutView({ orderId }: { orderId: string }) {
  const router = useRouter();
  const { user } = useRequireAuth();
  const query = useCheckoutQuery(orderId);
  const [placed, setPlaced] = useState(false);
  const orderHref = `/account/orders/${encodeURIComponent(orderId)}`;

  return (
    <div className={cn(pageFrame, 'pt-6 pb-16 font-qb qb-tablet:pt-[35px] qb-desktop:pt-16 qb-desktop:pb-24')}>
      <Breadcrumb
        items={[
          { label: t('orders.common.home'), href: '/' },
          { label: t('orders.checkout.orders'), href: '/account/orders' },
          { label: t('orders.checkout.title') },
        ]}
        className="hidden text-qb-caption qb-tablet:block qb-desktop:text-qb-h5"
      />
      <h1 className="text-qb-h2 font-semibold tracking-normal text-qb-ink qb-tablet:mt-5 qb-tablet:text-[32px] qb-desktop:sr-only">
        {t('orders.checkout.title')}
      </h1>
      <div className="mt-6 qb-desktop:mt-[62px]">
        {!user || query.isPending ? (
          <PageState kind="loading" />
        ) : query.isError ? (
          <CheckoutError error={query.error} orderHref={orderHref} onRetry={() => query.refetch()} />
        ) : query.data.order.status !== 'created' ? (
          <Notice tone="info" role="status" className="max-w-2xl">
            <p>{t(query.data.order.status === 'awaiting_handover' ? 'orders.checkout.already_done' : 'orders.checkout.closed')}</p>
            <Link href={orderHref} className="mt-2 inline-block font-semibold text-qb-ink underline underline-offset-2">
              {t('orders.checkout.view_order')}
            </Link>
          </Notice>
        ) : (
          <CheckoutForm checkout={query.data} onPlaced={() => setPlaced(true)} />
        )}
      </div>

      {/* Lives here, not in the form: the order refetch after the checkout swaps the form for the "already done" notice. */}
      <Modal
        open={placed}
        onOpenChange={(open) => {
          if (!open) router.push(orderHref);
        }}
        title={t('orders.checkout.success_title')}
        description={t('orders.checkout.success_body')}
        className="pt-[100px]"
      >
        <ResultIcon tone="success" />
        <div className="mt-6 flex flex-col items-center gap-3">
          <Link href={orderHref} className={cn(buttonVariants({ fullWidth: true }), ctaButton)}>
            {t('orders.checkout.view_order')}
          </Link>
          <Link href="/" className={cn(buttonVariants({ variant: 'ghost' }), 'text-qb-brand')}>
            {t('orders.checkout.back_home')}
          </Link>
        </div>
      </Modal>
    </div>
  );
}

function CheckoutError({ error, orderHref, onRetry }: { error: ApiClientError; orderHref: string; onRetry: () => void }) {
  if (error.status === 403) {
    return (
      <Notice tone="info" role="status" className="max-w-2xl">
        <p>{t('orders.checkout.seller_view')}</p>
        <Link href={orderHref} className="mt-2 inline-block font-semibold text-qb-ink underline underline-offset-2">
          {t('orders.checkout.view_order')}
        </Link>
      </Notice>
    );
  }
  if (error.status === 404) return <PageState kind="empty" message={t('orders.checkout.not_found')} />;
  return <PageState kind="error" onRetry={onRetry} />;
}

function defaultChoice(checkout: Checkout): AddressChoice {
  const saved = checkout.saved_addresses.find((address) => address.is_default) ?? checkout.saved_addresses[0];
  return saved ? { kind: 'saved', id: saved.id } : { kind: 'new' };
}

function CheckoutForm({ checkout, onPlaced }: { checkout: Checkout; onPlaced: () => void }) {
  const { user } = useAuth();
  const { order } = checkout;
  const submit = useSubmitCheckoutMutation(order.id);
  const { key, renew } = useIdempotencyKey();
  const formRef = useRef<HTMLFormElement>(null);

  const [fulfillment, setFulfillment] = useState<Fulfillment>(checkout.fulfillment_options[0] ?? 'pickup');
  const [choice, setChoice] = useState<AddressChoice>(() => defaultChoice(checkout));
  const [address, setAddress] = useState<CheckoutAddressInput>(() => ({
    ...EMPTY_ADDRESS,
    full_name: user?.full_name ?? '',
    phone: user?.phone ?? '',
  }));
  const [addressErrors, setAddressErrors] = useState<Partial<Record<AddressField, string>>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [failure, setFailure] = useState<string | null>(null);

  const quote = checkout.quotes[fulfillment];
  const isDelivery = fulfillment === 'delivery';

  const lines = useMemo<SummaryLine[]>(() => {
    if (!quote) return [];
    const out: SummaryLine[] = [];
    if (quote.quantity > 1) {
      out.push({
        label: t('orders.checkout.quantity'),
        amount: t('orders.card.quantity_line', { count: quote.quantity, price: formatMoney(quote.unit_price, checkout.currency) }),
        formatted: true,
      });
    }
    out.push({ label: t('orders.checkout.subtotal'), amount: quote.items_subtotal });
    if (isDelivery) {
      out.push(
        isPositiveAmount(quote.shipping_fee)
          ? { label: t('orders.checkout.delivery_fee'), amount: quote.shipping_fee }
          : { label: t('orders.checkout.delivery_fee'), amount: t('orders.checkout.free'), formatted: true },
      );
    }
    return out;
  }, [checkout.currency, isDelivery, quote]);

  const payload = (): CheckoutPayload | null => {
    if (!isDelivery) return { fulfillment, payment_method: 'cash' };
    if (choice.kind === 'saved') return { fulfillment, payment_method: 'cash', address_id: choice.id };
    const errors = validateAddress(address);
    setAddressErrors(errors);
    if (Object.keys(errors).length > 0) return null;
    return { fulfillment, payment_method: 'cash', address: toAddressPayload(address) };
  };

  const send = async () => {
    const body = payload();
    setFormError(null);
    if (!body) {
      focusFirstInvalid(formRef.current);
      return;
    }
    try {
      await submit.mutateAsync({ payload: body, idempotencyKey: key });
      renew();
      setFailure(null);
      onPlaced();
    } catch (error) {
      if (isHandledGlobally(error)) return;
      const fields = fieldErrors(error);
      const addressFieldErrors = Object.fromEntries(
        Object.entries(fields)
          .filter(([name]) => name.startsWith('address.'))
          .map(([name, message]) => [name.slice('address.'.length), message]),
      ) as Partial<Record<AddressField, string>>;
      if (Object.keys(addressFieldErrors).length > 0) {
        setChoice({ kind: 'new' });
        setAddressErrors(addressFieldErrors);
        focusFirstInvalid(formRef.current);
        return;
      }
      if (error instanceof ApiClientError && error.code === 'VALIDATION_FAILED' && isDelivery && fields.address_id) {
        setFormError(t('orders.checkout.address_needed'));
        return;
      }
      setFailure(dealErrorMessage(error));
    }
  };

  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    void send();
  };

  return (
    <form ref={formRef} onSubmit={onSubmit} noValidate className="flex flex-col gap-6 qb-tablet:flex-row qb-tablet:items-start qb-desktop:gap-[33px]">
      <div className="flex min-w-0 flex-1 flex-col gap-6">
        <CheckoutPanel title={t('orders.checkout.fulfillment_title')} titleId="checkout-fulfillment">
          <div role="radiogroup" aria-labelledby="checkout-fulfillment" className="[display:grid] grid-cols-1 gap-3 qb-desktop:grid-cols-2 qb-desktop:gap-5">
            {checkout.fulfillment_options.map((option) => (
              <RadioCard
                key={option}
                name="fulfillment"
                value={option}
                checked={fulfillment === option}
                onChange={() => setFulfillment(option)}
                icon={option === 'delivery' ? <Truck /> : <Store />}
                label={t(`orders.common.${option}`)}
                description={
                  option === 'pickup'
                    ? t('orders.checkout.pickup_hint')
                    : checkout.delivery_fee && isPositiveAmount(checkout.delivery_fee)
                      ? t('orders.checkout.delivery_hint', { fee: formatMoney(checkout.delivery_fee, checkout.currency) })
                      : t('orders.checkout.delivery_free')
                }
              />
            ))}
          </div>
        </CheckoutPanel>

        {isDelivery ? (
          <CheckoutPanel title={t('orders.checkout.address_title')} titleId="checkout-address">
            <AddressPicker
              addresses={checkout.saved_addresses}
              choice={choice}
              onChoiceChange={setChoice}
              address={address}
              onAddressChange={setAddress}
              errors={addressErrors}
              labelledBy="checkout-address"
            />
          </CheckoutPanel>
        ) : null}

        <CheckoutPanel title={t('orders.checkout.payment_title')} titleId="checkout-payment">
          <div role="radiogroup" aria-labelledby="checkout-payment" className="[display:grid] grid-cols-1 gap-3 qb-desktop:grid-cols-2 qb-desktop:gap-5">
            {checkout.payment_methods.map((method) => (
              <RadioCard
                key={method}
                name="payment_method"
                value={method}
                defaultChecked
                icon={<Banknote />}
                label={t('orders.common.cash')}
                description={t('orders.checkout.cash_hint')}
              />
            ))}
          </div>
        </CheckoutPanel>
      </div>

      <div className="qb-tablet:w-[278px] qb-tablet:shrink-0 qb-desktop:sticky qb-desktop:top-6 qb-desktop:w-[421px]">
        <OrderSummaryCard
          title={order.ad.title}
          subtitle={t('orders.common.order_number', { number: orderNumber(order.id) })}
          currency={checkout.currency}
          lines={lines}
          total={quote?.total ?? order.total}
          footer={
            <>
              <FormError>{formError}</FormError>
              <Button type="submit" fullWidth className={cn(ctaButton, formError && 'mt-3')} disabled={submit.isPending} aria-busy={submit.isPending}>
                {submit.isPending ? <Loader2 className="animate-spin motion-reduce:animate-none" aria-hidden="true" /> : null}
                {t('orders.checkout.place_order')}
              </Button>
              <p className="mt-4 flex items-center justify-center gap-1.5 text-qb-micro text-qb-ink-subtle">
                <Icon icon={Banknote} size="sm" className="text-qb-success" />
                {t('orders.checkout.cash_note')}
              </p>
            </>
          }
        />
      </div>

      <Modal
        open={failure !== null}
        onOpenChange={(open) => !open && setFailure(null)}
        title={t('orders.checkout.failure_title')}
        description={failure ?? undefined}
        className="pt-[100px]"
      >
        <ResultIcon tone="danger" />
        <div className="mt-6 flex flex-col items-center gap-3">
          <Button fullWidth className={ctaButton} onClick={() => void send()} disabled={submit.isPending} aria-busy={submit.isPending}>
            {t('orders.common.retry')}
          </Button>
          <Button variant="ghost" className="text-qb-brand" onClick={() => setFailure(null)}>
            {t('orders.checkout.change_details')}
          </Button>
        </div>
      </Modal>
    </form>
  );
}

function ResultIcon({ tone }: { tone: 'success' | 'danger' }) {
  return (
    <span
      aria-hidden="true"
      className="absolute inset-x-0 top-6 mx-auto flex size-[54px] items-center justify-center rounded-full bg-qb-brand-soft text-qb-brand"
    >
      <Icon icon={tone === 'success' ? CheckCircle2 : XCircle} size="lg" />
    </span>
  );
}
