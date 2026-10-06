/**
 * The post-ad form: its values, the checks it runs before calling the API and
 * the payload it sends. Limits mirror config('qbazaar.ads') on the API, which
 * stays the source of truth and re-validates everything.
 */
import type {
  Ad,
  AdCondition,
  AdShipping,
  AdType,
  CategoryField,
  CreateAdRequest,
  PriceType,
} from '@/lib/api/types';

export const AD_LIMITS = {
  titleMin: 5,
  titleMax: 120,
  descriptionMin: 20,
  descriptionMax: 5000,
  priceMax: 9_999_999,
  shippingFeeMax: 10_000,
  postalCodeMax: 10,
  streetMax: 255,
  customTextMax: 255,
  photosMax: 20,
} as const;

export const AD_TYPES: readonly AdType[] = ['offering', 'wanted'];
export const SHIPPING_OPTIONS: readonly AdShipping[] = ['pickup_only', 'delivery'];
export const PRICE_TYPES: readonly PriceType[] = ['fixed', 'negotiable', 'free', 'contact'];
export const CONDITIONS: readonly AdCondition[] = ['new', 'like_new', 'used'];

export type CustomFieldValue = string | boolean;

export interface AdFormValues {
  adType: AdType;
  title: string;
  categoryId: string | null;
  description: string;
  condition: AdCondition | null;
  customFields: Record<string, CustomFieldValue>;
  shipping: AdShipping;
  /** Typed amount in QAR; empty means free delivery. */
  shippingFee: string;
  /** Typed amount in QAR, kept as text so it is never rounded. */
  price: string;
  priceType: PriceType;
  postalCode: string;
  locationId: string | null;
  street: string;
  showFullAddress: boolean;
}

export const EMPTY_AD_FORM: AdFormValues = {
  adType: 'offering',
  title: '',
  categoryId: null,
  description: '',
  condition: null,
  customFields: {},
  shipping: 'pickup_only',
  shippingFee: '',
  price: '',
  priceType: 'fixed',
  postalCode: '',
  locationId: null,
  street: '',
  showFullAddress: false,
};

/** Form fields that can carry an error; custom fields use `custom.<key>`. */
export type AdFormField =
  | 'title'
  | 'categoryId'
  | 'photos'
  | 'description'
  | 'price'
  | 'shippingFee'
  | 'postalCode'
  | 'locationId'
  | 'street'
  | `custom.${string}`;

/** Field → i18n key of its message. */
export type AdFormErrors = Partial<Record<AdFormField, string>>;

/** Order the fields appear on the page, so the first error gets focus. */
const FIELD_ORDER: readonly AdFormField[] = [
  'title',
  'categoryId',
  'photos',
  'description',
  'price',
  'shippingFee',
  'postalCode',
  'locationId',
  'street',
];

export function customFieldName(key: string): AdFormField {
  return `custom.${key}`;
}

/** Whether the price type carries an amount at all. */
export function hasPrice(priceType: PriceType): boolean {
  return priceType === 'fixed' || priceType === 'negotiable';
}

const AMOUNT_PATTERN = /^\d+(\.\d{1,2})?$/;
const POSTAL_CODE_PATTERN = /^[0-9A-Za-z -]+$/;
const ARABIC_INDIC_ZERO = 0x0660;
const PERSIAN_ZERO = 0x06f0;
const EASTERN_DIGIT = /[٠-٩۰-۹]/g;
const ARABIC_DECIMAL_SEPARATOR = /٫/g;

/**
 * Turns Arabic-Indic and Persian digits (and the Arabic decimal separator)
 * into ASCII, so a number typed on an Arabic keyboard is accepted as is.
 */
export function normalizeDigits(value: string): string {
  return value
    .replace(EASTERN_DIGIT, (digit) => {
      const code = digit.charCodeAt(0);
      return String(code >= PERSIAN_ZERO ? code - PERSIAN_ZERO : code - ARABIC_INDIC_ZERO);
    })
    .replace(ARABIC_DECIMAL_SEPARATOR, '.');
}

/** A non-negative QAR amount with at most two decimals, as typed. */
export function isAmount(value: string): boolean {
  return AMOUNT_PATTERN.test(value);
}

/** Compares a validated amount with a whole-number limit without float maths. */
function exceeds(value: string, max: number): boolean {
  const [whole, fraction = ''] = value.split('.');
  const wholeNumber = Number(whole);
  return wholeNumber > max || (wholeNumber === max && /[1-9]/.test(fraction));
}

/**
 * Converts a validated amount for the JSON body. The API takes a number; an
 * amount with two decimals survives JSON exactly and nothing is computed on it.
 */
function amountForApi(value: string): number {
  return Number(value);
}

export function adFormFromAd(ad: Ad): AdFormValues {
  return {
    adType: ad.ad_type,
    title: ad.title,
    // The ad detail carries the loaded category and location, not their ids.
    categoryId: ad.category_id ?? ad.category?.id ?? null,
    description: ad.description,
    condition: ad.condition,
    customFields: customFieldsFromAd(ad.custom_fields),
    shipping: ad.shipping,
    shippingFee: ad.shipping_fee ?? '',
    price: ad.price === null ? '' : String(ad.price),
    priceType: ad.price_type,
    postalCode: ad.postal_code ?? '',
    locationId: ad.location_id ?? ad.location?.id ?? null,
    street: ad.street ?? '',
    showFullAddress: ad.show_full_address,
  };
}

function customFieldsFromAd(stored: Record<string, unknown> | null | undefined): Record<string, CustomFieldValue> {
  const values: Record<string, CustomFieldValue> = {};
  for (const [key, value] of Object.entries(stored ?? {})) {
    if (typeof value === 'boolean') values[key] = value;
    else if (value !== null && value !== undefined) values[key] = String(value);
  }
  return values;
}

export interface ValidateOptions {
  fields: readonly CategoryField[];
  photoCount: number;
  /** Publishing (or saving a live ad) needs a photo; a draft does not. */
  requirePhoto: boolean;
}

export function validateAdForm(values: AdFormValues, { fields, photoCount, requirePhoto }: ValidateOptions): AdFormErrors {
  const errors: AdFormErrors = {};
  const title = values.title.trim();
  const description = values.description.trim();

  if (!title) errors.title = 'post_ad.errors.title_required';
  else if (title.length < AD_LIMITS.titleMin) errors.title = 'post_ad.errors.title_min';
  else if (title.length > AD_LIMITS.titleMax) errors.title = 'post_ad.errors.title_max';

  if (!values.categoryId) errors.categoryId = 'post_ad.errors.category_required';

  if (requirePhoto && photoCount === 0) errors.photos = 'post_ad.errors.photos_required';

  if (!description) errors.description = 'post_ad.errors.description_required';
  else if (description.length < AD_LIMITS.descriptionMin) errors.description = 'post_ad.errors.description_min';
  else if (description.length > AD_LIMITS.descriptionMax) errors.description = 'post_ad.errors.description_max';

  if (hasPrice(values.priceType)) {
    const price = values.price.trim();
    if (!price) errors.price = 'post_ad.errors.price_required';
    else if (!isAmount(price)) errors.price = 'post_ad.errors.price_invalid';
    else if (exceeds(price, AD_LIMITS.priceMax)) errors.price = 'post_ad.errors.price_max';
  }

  const fee = values.shippingFee.trim();
  if (values.shipping === 'delivery' && fee) {
    if (!isAmount(fee)) errors.shippingFee = 'post_ad.errors.fee_invalid';
    else if (exceeds(fee, AD_LIMITS.shippingFeeMax)) errors.shippingFee = 'post_ad.errors.fee_max';
  }

  const postalCode = values.postalCode.trim();
  if (postalCode.length > AD_LIMITS.postalCodeMax) errors.postalCode = 'post_ad.errors.postal_code_max';
  else if (postalCode && !POSTAL_CODE_PATTERN.test(postalCode)) errors.postalCode = 'post_ad.errors.postal_code_invalid';

  if (!values.locationId) errors.locationId = 'post_ad.errors.location_required';

  if (values.street.trim().length > AD_LIMITS.streetMax) errors.street = 'post_ad.errors.street_max';

  for (const field of fields) {
    const message = customFieldError(field, values.customFields[field.key]);
    if (message) errors[customFieldName(field.key)] = message;
  }

  return errors;
}

function customFieldError(field: CategoryField, value: CustomFieldValue | undefined): string | null {
  if (field.type === 'boolean') return null;
  const text = typeof value === 'string' ? value.trim() : '';
  if (!text) return field.required ? 'post_ad.errors.field_required' : null;
  if (field.type === 'number' && !Number.isFinite(Number(text))) return 'post_ad.errors.field_number';
  if (field.type === 'select' && field.options && !field.options.includes(text)) return 'post_ad.errors.field_required';
  if (field.type === 'text' && text.length > AD_LIMITS.customTextMax) return 'post_ad.errors.field_too_long';
  return null;
}

/** Custom field options come as raw values ("like_new", "petrol"); show them as words. */
export function optionLabel(option: string): string {
  const words = option.replace(/_/g, ' ');
  return words.charAt(0).toUpperCase() + words.slice(1);
}

/** First field with an error, in page order (custom fields after the details). */
export function firstErrorField(errors: AdFormErrors, fields: readonly CategoryField[]): AdFormField | null {
  const order: AdFormField[] = [...FIELD_ORDER.slice(0, 4), ...fields.map((f) => customFieldName(f.key)), ...FIELD_ORDER.slice(4)];
  return order.find((name) => errors[name]) ?? null;
}

/**
 * The create/update body. `category_id` and `location_id` are checked by
 * `validateAdForm` first, so they are present here.
 */
export function toAdPayload(values: AdFormValues, fields: readonly CategoryField[]): CreateAdRequest {
  const priced = hasPrice(values.priceType);
  const delivery = values.shipping === 'delivery';
  const fee = values.shippingFee.trim();
  return {
    category_id: values.categoryId ?? '',
    location_id: values.locationId ?? '',
    title: values.title.trim(),
    description: values.description.trim(),
    price: priced ? amountForApi(values.price.trim()) : null,
    price_type: values.priceType,
    condition: values.condition,
    custom_fields: customFieldsForApi(values.customFields, fields),
    ad_type: values.adType,
    shipping: values.shipping,
    shipping_fee: delivery && fee ? amountForApi(fee) : null,
    postal_code: values.postalCode.trim() || null,
    street: values.street.trim() || null,
    show_full_address: values.showFullAddress,
  };
}

function customFieldsForApi(
  values: Record<string, CustomFieldValue>,
  fields: readonly CategoryField[],
): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  for (const field of fields) {
    const value = values[field.key];
    if (field.type === 'boolean') {
      payload[field.key] = value === true;
      continue;
    }
    const text = typeof value === 'string' ? value.trim() : '';
    if (!text) continue;
    payload[field.key] = field.type === 'number' ? Number(text) : text;
  }
  return payload;
}

const SERVER_FIELDS: Record<string, AdFormField> = {
  title: 'title',
  description: 'description',
  category_id: 'categoryId',
  location_id: 'locationId',
  price: 'price',
  shipping_fee: 'shippingFee',
  postal_code: 'postalCode',
  street: 'street',
};

/**
 * Maps a VALIDATION_FAILED `details` bag onto form fields. The API's own
 * message is shown for each field, since it names the exact rule.
 */
export function serverErrorsToForm(details: Record<string, string[]> | null | undefined): Partial<Record<AdFormField, string>> {
  const errors: Partial<Record<AdFormField, string>> = {};
  for (const [path, messages] of Object.entries(details ?? {})) {
    const message = messages[0];
    if (!message) continue;
    if (path.startsWith('custom_fields.')) errors[customFieldName(path.slice('custom_fields.'.length))] = message;
    else if (SERVER_FIELDS[path]) errors[SERVER_FIELDS[path]] = message;
  }
  return errors;
}
