import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import { JsonLd, serializeJsonLd } from '@/components/seo/JsonLd';

const hostileTitle = '</script><script>alert(1)</script><!--';

describe('JsonLd', () => {
  it('cannot be closed early by a "</script>" inside the data', () => {
    const html = renderToStaticMarkup(<JsonLd data={{ name: hostileTitle }} />);

    expect(html.match(/<\/script>/gi)).toHaveLength(1);
    expect(html).not.toContain('<script>alert');
    expect(html).not.toContain('<!--');
  });

  it('keeps the payload identical once parsed', () => {
    const data = { name: hostileTitle, note: 'a & b > c\u2028d' };

    expect(JSON.parse(serializeJsonLd(data))).toEqual(data);
  });
});
