'use client';

import { useState } from 'react';
import { Info } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { Input, Select, Textarea } from '@/components/design-system/Input';
import type { CategoryField, CategoryNode, Location } from '@/lib/api/types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import {
  AD_LIMITS,
  AD_TYPES,
  CONDITIONS,
  PRICE_TYPES,
  SHIPPING_OPTIONS,
  customFieldName,
  hasPrice,
  normalizeDigits,
  selectOptions,
  type AdFormField,
  type AdFormValues,
} from '@/lib/post-ad/form';
import { findPath } from '@/lib/post-ad/tree';
import { cn } from '@/lib/utils';
import { usePostAdStore } from '@/store/post-ad';

import { CategoryPicker } from './CategoryPicker';
import {
  AmountInput,
  ChoiceGroup,
  FieldError,
  FieldLabel,
  FormSection,
  controlSize,
  describedBy,
  errorId,
  fieldId,
  selectSize,
} from './FormParts';
import { PhotoUploader } from './PhotoUploader';

/** Store access shared by the sections: values, errors and an edit that clears the field's error. */
function useFormField() {
  const values = usePostAdStore((state) => state.values);
  const errors = usePostAdStore((state) => state.errors);
  const setValues = usePostAdStore((state) => state.setValues);
  const clearError = usePostAdStore((state) => state.clearError);
  const update = (patch: Partial<AdFormValues>, field?: AdFormField) => {
    setValues(patch);
    if (field) clearError(field);
  };
  return { values, errors, update };
}

const fieldGap = 'mb-[22px]';

export function BasicInfoSection({ tree }: { tree: readonly CategoryNode[] }) {
  const { values, errors, update } = useFormField();
  const [pickerOpen, setPickerOpen] = useState(false);
  const categoryPath = findPath(tree, values.categoryId)
    .map((node) => localized(node.name))
    .join(' > ');
  const categoryValueId = `${fieldId('categoryId')}-value`;
  const titleHintId = `${fieldId('title')}-hint`;
  const descriptionHintId = `${fieldId('description')}-hint`;

  return (
    <FormSection id="post-ad-section-basic" title={t('post_ad.basic.title')}>
      <div className={fieldGap}>
        <ChoiceGroup
          legend={t('post_ad.basic.ad_type')}
          name="ad_type"
          size="lg"
          value={values.adType}
          options={AD_TYPES.map((value) => ({ value, label: t(`post_ad.ad_type.${value}`) }))}
          onChange={(adType) => update({ adType })}
        />
      </div>

      <div className={fieldGap}>
        <FieldLabel htmlFor={fieldId('title')}>{t('post_ad.basic.ad_title')}</FieldLabel>
        <Input
          id={fieldId('title')}
          value={values.title}
          maxLength={AD_LIMITS.titleMax}
          placeholder={t('post_ad.basic.ad_title_placeholder')}
          autoComplete="off"
          aria-required
          onChange={(event) => update({ title: event.target.value }, 'title')}
          className="h-[57px] rounded-qb-lg text-qb-body-sm"
          {...describedBy('title', errors.title, titleHintId)}
        />
        <p id={titleHintId} className="mt-2 flex items-center gap-1.5 text-qb-label text-qb-brand">
          <Icon icon={Info} size="sm" className="size-3.5" />
          {t('post_ad.basic.ad_title_hint')}
        </p>
        <FieldError name="title" message={errors.title} />
      </div>

      <div className={fieldGap}>
        <FieldLabel id={`${fieldId('categoryId')}-label`}>{t('post_ad.basic.category')}</FieldLabel>
        <div className="flex items-center gap-3 qb-tablet:items-stretch">
          <div
            id={categoryValueId}
            onClick={() => setPickerOpen(true)}
            className={cn(
              'min-h-[57px] flex-1 cursor-pointer rounded-qb-lg border p-4 text-qb-body-sm',
              errors.categoryId ? 'border-qb-danger' : 'border-qb-line',
              categoryPath ? 'text-qb-ink' : 'text-qb-ink-subtle',
            )}
          >
            {categoryPath || t('post_ad.basic.category_placeholder')}
          </div>
          <Button
            id={fieldId('categoryId')}
            variant="soft"
            aria-haspopup="dialog"
            aria-labelledby={`${fieldId('categoryId')}-label ${fieldId('categoryId')}`}
            aria-describedby={[categoryValueId, errors.categoryId ? errorId('categoryId') : null].filter(Boolean).join(' ')}
            onClick={() => setPickerOpen(true)}
            className={cn(
              'h-auto shrink-0 px-[26px] text-qb-body-sm font-medium shadow-qb-brand',
              'max-qb-tablet:rounded-qb-pill max-qb-tablet:border max-qb-tablet:border-qb-brand max-qb-tablet:px-4 max-qb-tablet:py-[9px] max-qb-tablet:text-qb-micro',
            )}
          >
            {t('post_ad.basic.category_select')}
          </Button>
        </div>
        <FieldError name="categoryId" message={errors.categoryId} />
        <CategoryPicker
          open={pickerOpen}
          onOpenChange={setPickerOpen}
          tree={tree}
          value={values.categoryId}
          onSelect={(categoryId) => {
            update({ categoryId }, 'categoryId');
            setPickerOpen(false);
          }}
        />
      </div>

      <div className={fieldGap}>
        <PhotoUploader error={errors.photos} />
      </div>

      <div>
        <FieldLabel htmlFor={fieldId('description')}>{t('post_ad.basic.description')}</FieldLabel>
        <Textarea
          id={fieldId('description')}
          rows={4}
          value={values.description}
          maxLength={AD_LIMITS.descriptionMax}
          placeholder={t('post_ad.basic.description_placeholder')}
          aria-required
          onChange={(event) => update({ description: event.target.value }, 'description')}
          className="min-h-[122px] resize-y px-3.5 text-qb-body-sm"
          {...describedBy('description', errors.description, descriptionHintId)}
        />
        <p id={descriptionHintId} className="mt-2 font-qb-label text-qb-label text-qb-brand">
          {t('post_ad.basic.description_hint')}
        </p>
        <FieldError name="description" message={errors.description} />
      </div>
    </FormSection>
  );
}

/** Condition and the chosen category's own fields (make, model, year...). */
export function DetailsSection({ fields }: { fields: readonly CategoryField[] }) {
  const { values, errors, update } = useFormField();

  return (
    <FormSection id="post-ad-section-details" title={t('post_ad.details.title')}>
      <div className="[display:grid] grid-cols-1 gap-x-4 gap-y-[22px] qb-tablet:grid-cols-2">
        <div>
          <FieldLabel htmlFor="post-ad-condition" optional={t('post_ad.optional')}>
            {t('post_ad.details.condition')}
          </FieldLabel>
          <Select
            id="post-ad-condition"
            value={values.condition ?? ''}
            onChange={(event) => {
              const condition = CONDITIONS.find((item) => item === event.target.value) ?? null;
              update({ condition });
            }}
            className={cn(selectSize, values.condition ? 'text-qb-ink-body' : 'text-qb-ink-subtle')}
          >
            <option value="">{t('post_ad.details.choose')}</option>
            {CONDITIONS.map((condition) => (
              <option key={condition} value={condition}>
                {t(`ads.condition.${condition}`)}
              </option>
            ))}
          </Select>
        </div>

        {fields.map((field) => (
          <CustomField
            key={field.key}
            field={field}
            value={values.customFields[field.key]}
            error={errors[customFieldName(field.key)]}
            onChange={(value) =>
              update({ customFields: { ...values.customFields, [field.key]: value } }, customFieldName(field.key))
            }
          />
        ))}
      </div>
    </FormSection>
  );
}

function CustomField({
  field,
  value,
  error,
  onChange,
}: {
  field: CategoryField;
  value: string | boolean | undefined;
  error: string | undefined;
  onChange: (value: string | boolean) => void;
}) {
  const name = customFieldName(field.key);
  const id = fieldId(name);
  const label = localized(field.label);
  const optional = field.required ? undefined : t('post_ad.optional');

  if (field.type === 'boolean') {
    return (
      <div className="flex items-end">
        <label className="flex min-h-[53px] cursor-pointer items-center gap-3 text-qb-body-sm text-qb-ink-body">
          <input
            id={id}
            type="checkbox"
            checked={value === true}
            onChange={(event) => onChange(event.target.checked)}
            className="size-5 accent-qb-brand"
          />
          {label}
        </label>
      </div>
    );
  }

  const text = typeof value === 'string' ? value : '';
  return (
    <div>
      <FieldLabel htmlFor={id} optional={optional}>
        {label}
      </FieldLabel>
      {field.type === 'select' && field.options ? (
        <Select
          id={id}
          value={text}
          aria-required={field.required || undefined}
          onChange={(event) => onChange(event.target.value)}
          className={cn(selectSize, text ? 'text-qb-ink-body' : 'text-qb-ink-subtle')}
          {...describedBy(name, error)}
        >
          <option value="">{t('post_ad.details.choose')}</option>
          {selectOptions(field).map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>
      ) : (
        <Input
          id={id}
          type={field.type === 'date' ? 'date' : 'text'}
          inputMode={field.type === 'number' ? 'decimal' : undefined}
          value={text}
          aria-required={field.required || undefined}
          maxLength={field.type === 'text' ? AD_LIMITS.customTextMax : undefined}
          onChange={(event) => onChange(field.type === 'number' ? normalizeDigits(event.target.value) : event.target.value)}
          className={controlSize}
          {...describedBy(name, error)}
        />
      )}
      <FieldError name={name} message={error} />
    </div>
  );
}

export function PriceSection() {
  const { values, errors, update } = useFormField();
  const priced = hasPrice(values.priceType);
  const currency = t('post_ad.price.currency');
  const feeHintId = `${fieldId('shippingFee')}-hint`;

  return (
    <FormSection id="post-ad-section-price" title={t('post_ad.price.title')}>
      <div className={fieldGap}>
        <ChoiceGroup
          legend={t('post_ad.price.shipping')}
          name="shipping"
          value={values.shipping}
          options={SHIPPING_OPTIONS.map((value) => ({ value, label: t(`post_ad.price.shipping_${value}`) }))}
          onChange={(shipping) => update({ shipping }, 'shippingFee')}
        />
      </div>

      {values.shipping === 'delivery' ? (
        <div className={fieldGap}>
          <FieldLabel htmlFor={fieldId('shippingFee')} optional={t('post_ad.optional')}>
            {t('post_ad.price.fee')}
          </FieldLabel>
          <AmountInput
            id={fieldId('shippingFee')}
            currency={currency}
            value={values.shippingFee}
            placeholder="0"
            invalid={Boolean(errors.shippingFee)}
            onValueChange={(value) => update({ shippingFee: normalizeDigits(value) }, 'shippingFee')}
            {...describedBy('shippingFee', errors.shippingFee, feeHintId)}
          />
          <p id={feeHintId} className="mt-2 text-qb-label text-qb-ink-subtle">
            {t('post_ad.price.fee_hint')}
          </p>
          <FieldError name="shippingFee" message={errors.shippingFee} />
        </div>
      ) : null}

      <div className="[display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-2">
        <div>
          <FieldLabel htmlFor={fieldId('price')}>{t('post_ad.price.price')}</FieldLabel>
          <AmountInput
            id={fieldId('price')}
            currency={currency}
            value={priced ? values.price : ''}
            placeholder="0"
            disabled={!priced}
            aria-required={priced || undefined}
            invalid={Boolean(errors.price)}
            onValueChange={(value) => update({ price: normalizeDigits(value) }, 'price')}
            {...describedBy('price', errors.price)}
          />
          <FieldError name="price" message={errors.price} />
        </div>
        <div>
          <FieldLabel htmlFor="post-ad-price-type">{t('post_ad.price.price_type')}</FieldLabel>
          <Select
            id="post-ad-price-type"
            value={values.priceType}
            onChange={(event) => {
              const priceType = PRICE_TYPES.find((type) => type === event.target.value);
              if (priceType) update({ priceType }, 'price');
            }}
            className={cn(selectSize, 'text-qb-ink-body')}
          >
            {PRICE_TYPES.map((type) => (
              <option key={type} value={type}>
                {t(`ads.price.${type}`)}
              </option>
            ))}
          </Select>
        </div>
      </div>
    </FormSection>
  );
}

export function LocationSection({ cities }: { cities: readonly Location[] }) {
  const { values, errors, update } = useFormField();
  const noteId = 'post-ad-address-note';

  return (
    <FormSection id="post-ad-section-location" title={t('post_ad.location.title')}>
      <div className="mb-[18px] [display:grid] grid-cols-1 gap-4 qb-tablet:grid-cols-2">
        <div>
          <FieldLabel htmlFor={fieldId('postalCode')} optional={t('post_ad.optional')}>
            {t('post_ad.location.postal_code')}
          </FieldLabel>
          <Input
            id={fieldId('postalCode')}
            value={values.postalCode}
            maxLength={AD_LIMITS.postalCodeMax}
            placeholder="XXXXX"
            autoComplete="postal-code"
            onChange={(event) => update({ postalCode: normalizeDigits(event.target.value) }, 'postalCode')}
            className={controlSize}
            {...describedBy('postalCode', errors.postalCode)}
          />
          <FieldError name="postalCode" message={errors.postalCode} />
        </div>
        <div>
          <FieldLabel htmlFor={fieldId('locationId')}>{t('post_ad.location.area')}</FieldLabel>
          <Select
            id={fieldId('locationId')}
            value={values.locationId ?? ''}
            aria-required
            onChange={(event) => update({ locationId: event.target.value || null }, 'locationId')}
            className={cn(selectSize, values.locationId ? 'text-qb-ink-body' : 'text-qb-ink-subtle')}
            {...describedBy('locationId', errors.locationId)}
          >
            <option value="">{t('post_ad.location.area_placeholder')}</option>
            {cities.map((city) => (
              <optgroup key={city.id} label={localized(city.name)}>
                <option value={city.id}>{t('post_ad.location.whole_city', { city: localized(city.name) })}</option>
                {city.children.map((district) => (
                  <option key={district.id} value={district.id}>
                    {localized(district.name)}
                  </option>
                ))}
              </optgroup>
            ))}
          </Select>
          <FieldError name="locationId" message={errors.locationId} />
        </div>
      </div>

      <div className="mb-3.5">
        <FieldLabel htmlFor={fieldId('street')} optional={t('post_ad.optional')}>
          {t('post_ad.location.street')}
        </FieldLabel>
        <Input
          id={fieldId('street')}
          value={values.street}
          maxLength={AD_LIMITS.streetMax}
          placeholder={t('post_ad.location.street_placeholder')}
          autoComplete="street-address"
          onChange={(event) => update({ street: event.target.value }, 'street')}
          className={controlSize}
          {...describedBy('street', errors.street)}
        />
        <FieldError name="street" message={errors.street} />
      </div>

      <label className="mb-2 flex cursor-pointer items-center gap-3 text-qb-body-sm text-qb-ink-body">
        <input
          type="checkbox"
          checked={values.showFullAddress}
          aria-describedby={noteId}
          onChange={(event) => update({ showFullAddress: event.target.checked })}
          className="size-5 shrink-0 accent-qb-brand"
        />
        {t('post_ad.location.show_full_address')}
      </label>
      <p id={noteId} className="text-qb-label leading-[1.6] text-qb-ink-subtle">
        {t('post_ad.location.note')}
      </p>
    </FormSection>
  );
}
