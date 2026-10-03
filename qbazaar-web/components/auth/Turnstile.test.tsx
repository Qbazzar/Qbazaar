import { createRef } from 'react';
import { act, render, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { Turnstile, type TurnstileHandle } from './Turnstile';
import type { TurnstileApi, TurnstileRenderOptions } from '@/lib/turnstile';

let options: TurnstileRenderOptions;
let turnstileApi: { [K in keyof TurnstileApi]: ReturnType<typeof vi.fn> };

beforeEach(() => {
  turnstileApi = {
    render: vi.fn((_el: HTMLElement, opts: TurnstileRenderOptions) => {
      options = opts;
      return 'widget-1';
    }),
    reset: vi.fn(),
    remove: vi.fn(),
  };
  window.turnstile = turnstileApi as unknown as TurnstileApi;
});

afterEach(() => {
  delete window.turnstile;
});

async function renderWidget() {
  const ref = createRef<TurnstileHandle>();
  const view = render(<Turnstile ref={ref} siteKey="site-key" />);
  await waitFor(() => expect(turnstileApi.render).toHaveBeenCalledTimes(1));
  return { ref, ...view };
}

describe('Turnstile', () => {
  it('renders nothing and yields no token without a site key', async () => {
    const ref = createRef<TurnstileHandle>();
    const { container } = render(<Turnstile ref={ref} siteKey="" />);

    expect(container).toBeEmptyDOMElement();
    expect(turnstileApi.render).not.toHaveBeenCalled();
    await expect(ref.current!.getToken()).resolves.toBeUndefined();
  });

  it('renders an interaction-only light widget with the site key', async () => {
    await renderWidget();
    expect(options).toMatchObject({
      sitekey: 'site-key',
      appearance: 'interaction-only',
      theme: 'light',
    });
  });

  it('hands out the token the widget produced', async () => {
    const { ref } = await renderWidget();
    const pending = ref.current!.getToken();
    act(() => options.callback('tok-1'));
    await expect(pending).resolves.toBe('tok-1');
  });

  it('reset drops the used token and asks Cloudflare for a new one', async () => {
    const { ref } = await renderWidget();
    act(() => options.callback('tok-1'));

    act(() => ref.current!.reset());
    expect(turnstileApi.reset).toHaveBeenCalledWith('widget-1');

    const pending = ref.current!.getToken();
    act(() => options.callback('tok-2'));
    await expect(pending).resolves.toBe('tok-2');
  });

  it('rejects with TURNSTILE_001 when the challenge errors', async () => {
    const { ref } = await renderWidget();
    const pending = ref.current!.getToken();
    act(() => options['error-callback']());
    await expect(pending).rejects.toMatchObject({ code: 'TURNSTILE_001' });
  });

  it('does not reuse a token after it expires', async () => {
    const { ref } = await renderWidget();
    act(() => options.callback('tok-1'));
    act(() => options['expired-callback']());

    const pending = ref.current!.getToken();
    act(() => options.callback('tok-2'));
    await expect(pending).resolves.toBe('tok-2');
  });

  it('removes the widget on unmount', async () => {
    const { unmount } = await renderWidget();
    unmount();
    expect(turnstileApi.remove).toHaveBeenCalledWith('widget-1');
  });
});
