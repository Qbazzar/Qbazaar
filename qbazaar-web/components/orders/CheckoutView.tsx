'use client';

import { useMemo, useRef, useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Banknote, CheckCircle2, Loader2, ShieldCheck, Store, Truck, XCircle } from 'lucide-react';

import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { Button, buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { Modal } from '@/components/design-system/Modal';
import { Notice } from '@/components/design-system/Notice';
import { useAuth } from '@/hooks/useAuth';
import { useIdempotencyKey } from '@/hooks/useIdempotencyKey';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { ApiClientError } from '@/lib/api/auth';
import type { Checkout, CheckoutAddressInput, CheckoutPayload, Fulfillment } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { EMPTY_ADDRESS, toAddressPayload, validateAddress, type AddressField } from '@/lib/orders/address';
import { dealErrorMessage, fieldErrors, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney, isPositiveAmount } from '@/lib/orders/money';
import { isolate } from '@/lib/orders/text';
import { useAdQuery } from '@/lib/queries/ads';
import { useCheckoutQuery, useSubmitCheckoutMutation } from '@/lib/queries/orders';
import { cn } from '@/lib/utils';

import '@/styles/design-tokens-sell.css';

import { pageFrame } from './AccountPageFrame';
import { ShippingAddressPanel, type AddressChoice } from './AddressPicker';
import { CheckoutPanel } from './CheckoutPanel';
import { focusFirstInvalid } from './focus-invalid';
import { FormError } from './NoteField';
import { OptionTile } from './OptionTile';
import { OrderSummaryCard, type SummaryLine } from './OrderSummaryCard';
import { PageState } from './PageState';
import { orderNumber } from './order-number';

/** The CTA of checkout.html: 47 px, radius 10, 15 px semibold. */
const ctaButton = 'h-[47px] rounded-qb-md text-qb-body-sm';

/**
 * The result dialog of 684:33194 and 689:33489 (buynow.js payModal): 430 px,
 * radius 20, the peach icon circle over a 20 px title and a 14 px grey line.
 */
const resultDialog =
  'max-w-[430px] rounded-[20px] px-[34px] pt-[110px] pb-[26px] [&_h2]:text-qb-h5 [&_h2]:font-semibold [&_h2]:text-qb-ink [&_p]:text-qb-caption [&_p]:leading-[1.65] [&_p]:text-(--color-qb-ink-dialog)';
const resultButton = 'h-auto rounded-qb-md py-3.5 text-qb-caption';
const resultLink = 'h-auto p-0 text-qb-caption text-qb-brand hover:bg-transparent hover:underline';
/** Two tiles a row at every width, as checkout.html. */
const optionGrid = '[display:grid] grid-cols-2 gap-3.5';

/** Plain text link under a notice. */
const noticeLink = cn('mt-2 inline-block rounded-qb-xs font-semibold text-qb-ink underline underline-offset-2', focusRing);

/** `/checkout/{orderId}`: the buyer picks pickup or delivery and confirms a cash order (682:32513). */
export function CheckoutView({ orderId }: { orderId: string }) {
  const router = useRouter();
  const { user } = useRequireAuth();
  const query = useCheckoutQuery(orderId);
  const [placed, setPlaced] = useState(false);
  const orderHref = `/account/orders/${encodeURIComponent(orderId)}`;

  return (
    <main className="bg-qb-page font-qb text-qb-ink">
      <div className={cn(pageFrame, 'pt-6 pb-16 qb-tablet:pt-[35px] qb-desktop:pt-16 qb-desktop:pb-24')}>
        <Breadcrumb
          items={[
            { label: t('home.breadcrumb'), href: '/' },
            { label: t('orders.checkout.orders'), href: '/account/orders' },
            { label: t('orders.checkout.title') },
          ]}
          className="hidden text-qb-caption qb-tablet:block qb-desktop:text-qb-h5"
        />
        <h1 className="font-qb text-qb-h2 font-semibold tracking-normal text-qb-ink qb-tablet:mt-5 qb-tablet:text-[32px] qb-desktop:sr-only">
          {t('orders.checkout.title')}
        </h1>
        <div className="mt-6 qb-desktop:mt-[62px]">
          {!user || query.isPending ? (
            <PageState kind="loading" />
          ) : query.isError ? (
            <CheckoutError error={query.error} orderHref={orderHref} onRetry={() => query.refetch()} />
          ) : query.data.order.status !== 'created' ? (
            <Notice tone="brand" role="status" className="max-w-2xl">
              <p>{t(query.data.order.status === 'awaiting_handover' ? 'orders.checkout.already_done' : 'orders.checkout.closed')}</p>
              <Link href={orderHref} className={noticeLink}>
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
          className={resultDialog}
        >
          <ResultIcon tone="success" />
          <div className="mt-1.5 flex flex-col items-center gap-3.5">
            <Link href={orderHref} className={cn(buttonVariants({ fullWidth: true }), resultButton)}>
              {t('orders.checkout.view_order')}
            </Link>
            <Link href="/" className={cn(buttonVariants({ variant: 'ghost' }), resultLink)}>
              {t('orders.checkout.back_home')}
            </Link>
          </div>
        </Modal>
      </div>
    </main>
  );
}

function CheckoutError({ error, orderHref, onRetry }: { error: ApiClientError; orderHref: string; onRetry: () => void }) {
  if (error.status === 403) {
    return (
      <Notice tone="brand" role="status" className="max-w-2xl">
        <p>{t('orders.checkout.seller_view')}</p>
        <Link href={orderHref} className={noticeLink}>
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
  // The order carries only the ad's id and title; its photo and seller come from the ad.
  const adQuery = useAdQuery(order.ad.id);
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
  const adPhoto = adQuery.data?.images?.[0];
  const sellerName = adQuery.data?.user?.business_name || adQuery.data?.user?.full_name;

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

  /** Checks the typed address and shows its errors; true when it can be sent. */
  const checkAddress = (): boolean => {
    const errors = validateAddress(address);
    setAddressErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const payload = (): CheckoutPayload | null => {
    if (!isDelivery) return { fulfillment, payment_method: 'cash' };
    if (choice.kind === 'saved') return { fulfillment, payment_method: 'cash', address_id: choice.id };
    if (!checkAddress()) return null;
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
          <div role="radiogroup" aria-labelledby="checkout-fulfillment" className={optionGrid}>
            {checkout.fulfillment_options.map((option) => (
              <OptionTile
                key={option}
                name="fulfillment"
                value={option}
                checked={fulfillment === option}
                onChange={() => setFulfillment(option)}
                icon={option === 'delivery' ? <Truck /> : <Store />}
                label={t(`orders.common.${option}`)}
                srDescription={
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
          <ShippingAddressPanel
            addresses={checkout.saved_addresses}
            choice={choice}
            onChoiceChange={setChoice}
            address={address}
            onAddressChange={setAddress}
            errors={addressErrors}
            onCheckAddress={checkAddress}
          />
        ) : null}

        <CheckoutPanel title={t('orders.checkout.payment_title')} titleId="checkout-payment">
          <div role="radiogroup" aria-labelledby="checkout-payment" className={optionGrid}>
            {checkout.payment_methods.map((method) => (
              <OptionTile
                key={method}
                name="payment_method"
                value={method}
                defaultChecked
                icon={<Banknote />}
                label={t('orders.common.cash')}
                srDescription={t('orders.checkout.cash_hint')}
              />
            ))}
          </div>
        </CheckoutPanel>
      </div>

      {/* Sticks 24 px under the 88 px site header. */}
      <div className="qb-tablet:w-[278px] qb-tablet:shrink-0 qb-desktop:sticky qb-desktop:top-[112px] qb-desktop:w-[421px]">
        <OrderSummaryCard
          title={order.ad.title}
          photoUrl={adPhoto?.sizes.thumbnail || adPhoto?.sizes.medium}
          subtitle={
            sellerName
              ? t('orders.checkout.sold_by', { name: isolate(sellerName) })
              : adQuery.isError
                ? t('orders.common.order_number', { number: orderNumber(order.id) })
                : undefined
          }
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
              <p className="mt-4 flex items-center justify-center gap-1.5 text-qb-label text-qb-ink-subtle">
                <Icon icon={ShieldCheck} size="sm" className="size-3.5 text-qb-success" />
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
        className={resultDialog}
      >
        <ResultIcon tone="danger" />
        <div className="mt-1.5 flex flex-col items-center gap-3.5">
          <Button fullWidth className={resultButton} onClick={() => void send()} disabled={submit.isPending} aria-busy={submit.isPending}>
            {t('orders.common.retry')}
          </Button>
          <Button variant="ghost" className={resultLink} onClick={() => setFailure(null)}>
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
      className="absolute inset-x-0 top-[38px] mx-auto flex size-[54px] items-center justify-center rounded-full bg-qb-brand-soft text-qb-brand"
    >
      <Icon icon={tone === 'success' ? CheckCircle2 : XCircle} className="size-[26px]" />
    </span>
  );
}
