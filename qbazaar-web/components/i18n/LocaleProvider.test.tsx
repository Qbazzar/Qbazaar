// @vitest-environment node
import { Suspense, use } from 'react';
import { renderToReadableStream } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

import { LocaleProvider } from './LocaleProvider';

function deferred() {
  let resolve: () => void = () => {};
  const promise = new Promise<void>((done) => {
    resolve = done;
  });
  return { promise, resolve };
}

/** Renders after its data arrives, like a streamed section of a page. */
function Resumed({ data }: { data: Promise<void> }) {
  use(data);
  return <p>{t('common.loading')}</p>;
}

function page(locale: Locale, data: Promise<void>) {
  return (
    <LocaleProvider locale={locale}>
      <Suspense fallback={null}>
        <Resumed data={data} />
      </Suspense>
    </LocaleProvider>
  );
}

const html = (stream: ReadableStream) => new Response(stream).text();

describe('LocaleProvider on the server', () => {
  it('keeps a streamed render in its own locale while another request renders', async () => {
    const arabicData = deferred();
    const arabic = await renderToReadableStream(page('ar', arabicData.promise));

    // An English request renders in full while the Arabic one waits for its data.
    const english = await html(await renderToReadableStream(page('en', Promise.resolve())));
    arabicData.resolve();
    const arabicHtml = await html(arabic);

    expect(english).toContain('Loading…');
    expect(arabicHtml).toContain('جاري التحميل…');
    expect(arabicHtml).not.toContain('Loading…');
  });
});
