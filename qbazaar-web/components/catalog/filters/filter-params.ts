import type { SearchQueryParams } from '@/lib/api/types';

import type { FilterValues } from './filter-values';

type CatalogFilterParams = Pick<SearchQueryParams, 'q' | 'condition' | 'ad_type' | 'shipping' | 'seller_type' | 'buy_now' | 'custom_fields'>;

/**
 * The filters `GET /ads` and `GET /search` share, as request parameters: only
 * the ones that are set, so the request (and its cache key) stays minimal.
 * `buy_now` is sent as `true` or not at all, as `false` means no filter.
 */
export function catalogFilterParams(values: FilterValues): CatalogFilterParams {
  const params: CatalogFilterParams = {};
  if (values.keyword.trim()) params.q = values.keyword.trim();
  if (values.condition) params.condition = values.condition;
  if (values.adType) params.ad_type = values.adType;
  if (values.shipping) params.shipping = values.shipping;
  if (values.sellerType) params.seller_type = values.sellerType;
  if (values.buyNow) params.buy_now = true;
  if (Object.keys(values.customFields).length > 0) params.custom_fields = values.customFields;
  return params;
}
