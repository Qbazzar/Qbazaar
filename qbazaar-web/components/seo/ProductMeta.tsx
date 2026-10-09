import { adOfferPrice } from '@/lib/ads/format';
import type { Ad } from '@/lib/api/types';

/**
 * `og:type` and `product:price:*` as `property` tags, the form Facebook and
 * WhatsApp read. React hoists `<meta>` into the document head; Next's metadata
 * API can only write the `name` form for these.
 */
export function ProductMeta({ ad }: { ad: Pick<Ad, 'price' | 'price_type' | 'currency'> }) {
  const price = adOfferPrice(ad);

  return (
    <>
      <meta property="og:type" content="product" />
      {price != null ? (
        <>
          <meta property="product:price:amount" content={String(price)} />
          <meta property="product:price:currency" content={ad.currency} />
        </>
      ) : null}
    </>
  );
}
