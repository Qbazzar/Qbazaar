import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { ProductMeta } from './ProductMeta';

describe('ProductMeta', () => {
  it('writes og:type and the price as property tags', () => {
    const html = renderToStaticMarkup(<ProductMeta ad={{ price: 52000, price_type: 'fixed', currency: 'QAR' }} />);

    expect(html).toContain('<meta property="og:type" content="product"/>');
    expect(html).toContain('<meta property="product:price:amount" content="52000"/>');
    expect(html).toContain('<meta property="product:price:currency" content="QAR"/>');
  });

  it('skips the price when it is on request', () => {
    const html = renderToStaticMarkup(<ProductMeta ad={{ price: null, price_type: 'contact', currency: 'QAR' }} />);

    expect(html).not.toContain('product:price');
  });
});
