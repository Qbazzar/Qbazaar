import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { EMPTY_ADDRESS, addressLines, toAddressPayload, validateAddress } from './address';

beforeEach(() => setClientLocale('en'));

describe('validateAddress', () => {
  it('asks for the required fields', () => {
    expect(validateAddress(EMPTY_ADDRESS)).toEqual({
      full_name: 'This field is required.',
      street: 'This field is required.',
      house_number: 'This field is required.',
      city: 'This field is required.',
    });
  });

  it('checks the phone format, the postal code and markup', () => {
    const errors = validateAddress({
      ...EMPTY_ADDRESS,
      full_name: 'F',
      phone: '5555 1234',
      street: 'West Bay <b>',
      house_number: '42',
      city: 'Doha',
      postal_code: '12 345',
    });

    expect(errors).toEqual({
      full_name: 'Enter a name of 2 to 80 characters.',
      phone: 'Use the international format, e.g. +97455551234.',
      street: 'Please remove the < and > characters.',
      postal_code: 'Use letters, numbers, dashes or underscores only.',
    });
  });

  it('accepts a complete address with a spaced or Arabic-digit phone', () => {
    const address = { ...EMPTY_ADDRESS, full_name: 'Farah', street: 'Al Sadd', house_number: '12', city: 'Doha' };
    expect(validateAddress({ ...address, phone: '+974 5512 3456' })).toEqual({});
    expect(validateAddress({ ...address, phone: '+٩٧٤٥٥١٢٣٤٥٦' })).toEqual({});
  });
});

describe('toAddressPayload', () => {
  it('trims, normalises the phone and nulls empty optional parts', () => {
    expect(
      toAddressPayload({
        full_name: ' Farah ',
        phone: '+974 5512-3456',
        street: 'Al Sadd ',
        house_number: '12',
        supplement: ' ',
        city: 'Doha',
        postal_code: '',
      }),
    ).toEqual({
      full_name: 'Farah',
      phone: '+97455123456',
      street: 'Al Sadd',
      house_number: '12',
      supplement: null,
      city: 'Doha',
      postal_code: null,
    });
  });
});

describe('addressLines', () => {
  it('formats the street, city and phone lines', () => {
    expect(
      addressLines({
        full_name: 'Farah',
        phone: '+97455123456',
        street: 'Al Waab Street',
        house_number: 'Villa 24',
        supplement: 'Zone 55',
        city: 'Doha',
        postal_code: null,
      }),
    ).toEqual(['Villa 24, Al Waab Street', 'Zone 55', 'Doha', '+97455123456']);
  });
});
