import { describe, expect, it } from 'vitest';

import type { Ad, CategoryField } from '@/lib/api/types';

import {
  EMPTY_AD_FORM,
  adFormFromAd,
  firstErrorField,
  isAmount,
  normalizeDigits,
  optionLabel,
  serverErrorsToForm,
  toAdPayload,
  validateAdForm,
  type AdFormValues,
} from './form';

const CAR_FIELDS: CategoryField[] = [
  { key: 'make', label: { ar: 'الماركة', en: 'Make' }, type: 'select', required: true, options: ['Toyota', 'BMW'] },
  { key: 'year', label: { ar: 'السنة', en: 'Year' }, type: 'number', required: true, options: null },
  { key: 'mileage_km', label: { ar: 'الكيلومترات', en: 'Mileage' }, type: 'number', required: false, options: null },
  { key: 'imported', label: { ar: 'مستورد', en: 'Imported' }, type: 'boolean', required: false, options: null },
];

const VALID: AdFormValues = {
  ...EMPTY_AD_FORM,
  title: 'Toyota Land Cruiser 2021',
  categoryId: 'cat-cars',
  description: 'One owner, full service history, no accidents.',
  price: '185000',
  locationId: 'loc-west-bay',
  customFields: { make: 'Toyota', year: '2021' },
};

const validate = (values: AdFormValues, photoCount = 1, requirePhoto = true) =>
  validateAdForm(values, { fields: CAR_FIELDS, photoCount, requirePhoto });

describe('validateAdForm', () => {
  it('accepts a complete ad', () => {
    expect(validate(VALID)).toEqual({});
  });

  it('asks for every required field of an empty form', () => {
    expect(validate(EMPTY_AD_FORM, 0)).toEqual({
      title: 'post_ad.errors.title_required',
      categoryId: 'post_ad.errors.category_required',
      photos: 'post_ad.errors.photos_required',
      description: 'post_ad.errors.description_required',
      price: 'post_ad.errors.price_required',
      locationId: 'post_ad.errors.location_required',
      'custom.make': 'post_ad.errors.field_required',
      'custom.year': 'post_ad.errors.field_required',
    });
  });

  it('lets a draft be saved without photos', () => {
    expect(validate(VALID, 0, false)).toEqual({});
  });

  it('applies the title and description lengths of the API', () => {
    expect(validate({ ...VALID, title: 'Car' }).title).toBe('post_ad.errors.title_min');
    expect(validate({ ...VALID, title: 'x'.repeat(121) }).title).toBe('post_ad.errors.title_max');
    expect(validate({ ...VALID, description: 'Too short' }).description).toBe('post_ad.errors.description_min');
    expect(validate({ ...VALID, title: '  Car  ' }).title).toBe('post_ad.errors.title_min');
  });

  it('checks the price only for price types that have one', () => {
    expect(validate({ ...VALID, price: '12.345' }).price).toBe('post_ad.errors.price_invalid');
    expect(validate({ ...VALID, price: '-5' }).price).toBe('post_ad.errors.price_invalid');
    expect(validate({ ...VALID, price: '10000000' }).price).toBe('post_ad.errors.price_max');
    expect(validate({ ...VALID, price: '9999999.01' }).price).toBe('post_ad.errors.price_max');
    expect(validate({ ...VALID, price: '9999999.00' }).price).toBeUndefined();
    expect(validate({ ...VALID, price: '', priceType: 'free' }).price).toBeUndefined();
    expect(validate({ ...VALID, price: '', priceType: 'contact' }).price).toBeUndefined();
    expect(validate({ ...VALID, price: '', priceType: 'negotiable' }).price).toBe('post_ad.errors.price_required');
  });

  it('checks the delivery fee only when delivery is offered', () => {
    expect(validate({ ...VALID, shipping: 'delivery', shippingFee: '15.5' }).shippingFee).toBeUndefined();
    expect(validate({ ...VALID, shipping: 'delivery', shippingFee: 'abc' }).shippingFee).toBe('post_ad.errors.fee_invalid');
    expect(validate({ ...VALID, shipping: 'delivery', shippingFee: '10001' }).shippingFee).toBe('post_ad.errors.fee_max');
    expect(validate({ ...VALID, shipping: 'pickup_only', shippingFee: 'abc' }).shippingFee).toBeUndefined();
  });

  it('validates the optional address fields', () => {
    expect(validate({ ...VALID, postalCode: '12345' }).postalCode).toBeUndefined();
    expect(validate({ ...VALID, postalCode: 'دوحة' }).postalCode).toBe('post_ad.errors.postal_code_invalid');
    expect(validate({ ...VALID, postalCode: '12345678901' }).postalCode).toBe('post_ad.errors.postal_code_max');
    expect(validate({ ...VALID, street: 'x'.repeat(256) }).street).toBe('post_ad.errors.street_max');
  });

  it('validates custom fields by type', () => {
    expect(validate({ ...VALID, customFields: { make: 'Lada', year: '2021' } })['custom.make']).toBe('post_ad.errors.field_required');
    expect(validate({ ...VALID, customFields: { make: 'BMW', year: 'new' } })['custom.year']).toBe('post_ad.errors.field_number');
    expect(validate({ ...VALID, customFields: { make: 'BMW', year: '2020', mileage_km: '' } })).toEqual({});
  });
});

describe('firstErrorField', () => {
  it('follows the page order, with custom fields after the basic information', () => {
    const errors = validate({ ...EMPTY_AD_FORM, title: 'A good title here', categoryId: 'cat-cars' }, 1);
    expect(firstErrorField(errors, CAR_FIELDS)).toBe('description');
    expect(firstErrorField({ price: 'x', 'custom.year': 'y' }, CAR_FIELDS)).toBe('custom.year');
    expect(firstErrorField({}, CAR_FIELDS)).toBeNull();
  });
});

describe('toAdPayload', () => {
  it('sends trimmed text, the amount and the listing details', () => {
    const payload = toAdPayload(
      { ...VALID, title: '  Toyota Land Cruiser 2021 ', postalCode: ' 12345 ', street: '', shipping: 'delivery', shippingFee: '25.50' },
      CAR_FIELDS,
    );

    expect(payload).toEqual({
      category_id: 'cat-cars',
      location_id: 'loc-west-bay',
      title: 'Toyota Land Cruiser 2021',
      description: 'One owner, full service history, no accidents.',
      price: 185000,
      price_type: 'fixed',
      condition: null,
      custom_fields: { make: 'Toyota', year: 2021, imported: false },
      ad_type: 'offering',
      shipping: 'delivery',
      shipping_fee: 25.5,
      postal_code: '12345',
      street: null,
      show_full_address: false,
    });
  });

  it('drops the price for free and contact ads and the fee for pickup', () => {
    const payload = toAdPayload({ ...VALID, priceType: 'free', shipping: 'pickup_only', shippingFee: '20' }, CAR_FIELDS);
    expect(payload.price).toBeNull();
    expect(payload.shipping_fee).toBeNull();
  });

  it("only sends the chosen category's fields", () => {
    const payload = toAdPayload({ ...VALID, customFields: { make: 'BMW', year: '2020', bedrooms: '3' } }, CAR_FIELDS);
    expect(payload.custom_fields).toEqual({ make: 'BMW', year: 2020, imported: false });
  });
});

describe('adFormFromAd', () => {
  it('fills the form from an ad being edited', () => {
    const ad = {
      ad_type: 'wanted',
      title: 'Looking for a road bike',
      category_id: 'cat-bikes',
      description: 'Size 54, carbon frame preferred, budget flexible.',
      condition: 'used',
      custom_fields: { year: 2019, imported: true, note: null },
      shipping: 'delivery',
      shipping_fee: '15.00',
      price: 1250.5,
      price_type: 'negotiable',
      postal_code: null,
      location_id: 'loc-doha',
      street: 'Street 140',
      show_full_address: true,
    } as unknown as Ad;

    expect(adFormFromAd(ad)).toEqual({
      adType: 'wanted',
      title: 'Looking for a road bike',
      categoryId: 'cat-bikes',
      description: 'Size 54, carbon frame preferred, budget flexible.',
      condition: 'used',
      customFields: { year: '2019', imported: true },
      shipping: 'delivery',
      shippingFee: '15.00',
      price: '1250.5',
      priceType: 'negotiable',
      postalCode: '',
      locationId: 'loc-doha',
      street: 'Street 140',
      showFullAddress: true,
    });
  });
});

describe('adFormFromAd without ids', () => {
  it('reads the category and location from the loaded relations of the ad detail', () => {
    const ad = {
      ad_type: 'offering',
      title: 'Toyota Land Cruiser 2021',
      description: 'One owner, full service history, no accidents.',
      condition: null,
      custom_fields: null,
      shipping: 'pickup_only',
      shipping_fee: null,
      price: null,
      price_type: 'contact',
      postal_code: null,
      street: null,
      show_full_address: false,
      category: { id: 'cat-cars' },
      location: { id: 'loc-west-bay' },
    } as unknown as Ad;

    expect(adFormFromAd(ad)).toMatchObject({ categoryId: 'cat-cars', locationId: 'loc-west-bay', price: '', customFields: {} });
  });
});

describe('optionLabel', () => {
  it('turns raw option values into words', () => {
    expect(optionLabel('like_new')).toBe('Like new');
    expect(optionLabel('petrol')).toBe('Petrol');
    expect(optionLabel('BMW')).toBe('BMW');
  });
});

describe('amount input', () => {
  it('accepts whole amounts and up to two decimals', () => {
    expect(isAmount('250')).toBe(true);
    expect(isAmount('250.5')).toBe(true);
    expect(isAmount('250.55')).toBe(true);
    expect(isAmount('250.555')).toBe(false);
    expect(isAmount('1e5')).toBe(false);
    expect(isAmount('')).toBe(false);
  });

  it('turns Arabic-Indic and Persian digits into ASCII', () => {
    expect(normalizeDigits('١٢٣٫٥')).toBe('123.5');
    expect(normalizeDigits('۴۵۰')).toBe('450');
    expect(normalizeDigits('QA-12')).toBe('QA-12');
  });
});

describe('serverErrorsToForm', () => {
  it('maps API field paths onto the form, keeping the first message', () => {
    expect(
      serverErrorsToForm({
        title: ['The title is too short.', 'second'],
        category_id: ['Pick a leaf category.'],
        'custom_fields.year': ['The year must be a number.'],
        quantity: ['ignored'],
      }),
    ).toEqual({
      title: 'The title is too short.',
      categoryId: 'Pick a leaf category.',
      'custom.year': 'The year must be a number.',
    });
    expect(serverErrorsToForm(null)).toEqual({});
  });
});
