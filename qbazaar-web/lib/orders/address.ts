import type { CheckoutAddressInput, DeliveryAddress } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';

import { toAsciiDigits } from './money';
import { validateText } from './validation';

export type AddressField = keyof CheckoutAddressInput;

export const EMPTY_ADDRESS: CheckoutAddressInput = {
  full_name: '',
  phone: '',
  street: '',
  house_number: '',
  supplement: '',
  city: '',
  postal_code: '',
};

/** Same bounds as `ValidatesAddressFields` on the API. */
const MAX: Record<AddressField, number> = {
  full_name: 80,
  phone: 16,
  street: 120,
  house_number: 20,
  supplement: 120,
  city: 80,
  postal_code: 20,
};

const REQUIRED: AddressField[] = ['full_name', 'street', 'house_number', 'city'];
const E164 = /^\+[1-9]\d{6,14}$/;
const POSTAL = /^[A-Za-z0-9_-]+$/;

/** Phone as E.164 without spaces, with Arabic digits turned into ASCII. */
export function normalizePhone(phone: string): string {
  return toAsciiDigits(phone).replace(/[\s()-]/g, '');
}

/** Field errors of an inline delivery address; empty when it can be sent. */
export function validateAddress(address: CheckoutAddressInput): Partial<Record<AddressField, string>> {
  const errors: Partial<Record<AddressField, string>> = {};
  for (const field of Object.keys(MAX) as AddressField[]) {
    const value = (address[field] ?? '').trim();
    if (field === 'phone') {
      if (value && !E164.test(normalizePhone(value))) errors.phone = t('orders.checkout.field_errors.phone');
      continue;
    }
    if (field === 'postal_code' && value && !POSTAL.test(value)) {
      errors.postal_code = t('orders.checkout.field_errors.postal_code');
      continue;
    }
    const error = validateText(value, {
      max: MAX[field],
      min: field === 'full_name' ? 2 : 0,
      required: REQUIRED.includes(field),
    });
    if (error) errors[field] = field === 'full_name' && value ? t('orders.checkout.field_errors.full_name') : error;
  }
  return errors;
}

/** The address as the API wants it: trimmed, optional parts null. */
export function toAddressPayload(address: CheckoutAddressInput): CheckoutAddressInput {
  const optional = (value: string | null) => (value?.trim() ? value.trim() : null);
  return {
    full_name: address.full_name.trim(),
    phone: address.phone?.trim() ? normalizePhone(address.phone) : null,
    street: address.street.trim(),
    house_number: address.house_number.trim(),
    supplement: optional(address.supplement),
    city: address.city.trim(),
    postal_code: optional(address.postal_code),
  };
}

/** Display lines of an address: name, street, city and phone. */
export function addressLines(address: DeliveryAddress): string[] {
  const street = [address.house_number, address.street].filter(Boolean).join(', ');
  const city = [address.city, address.postal_code].filter(Boolean).join(' ');
  return [street, address.supplement ?? '', city, address.phone ?? ''].filter((line) => line.trim() !== '');
}
