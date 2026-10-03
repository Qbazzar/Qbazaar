import { describe, expect, it } from 'vitest';

import { ApiClientError } from '@/lib/api/auth';
import { createTokenQueue, turnstileFailedError } from './turnstile';

describe('createTokenQueue', () => {
  it('returns a ready token immediately', async () => {
    const queue = createTokenQueue();
    queue.set('tok');
    await expect(queue.next()).resolves.toBe('tok');
  });

  it('waits for the widget when no token is ready yet', async () => {
    const queue = createTokenQueue();
    const pending = queue.next();
    queue.set('late');
    await expect(pending).resolves.toBe('late');
  });

  it('waits for a fresh token after clear (reset or expiry)', async () => {
    const queue = createTokenQueue();
    queue.set('used');
    queue.clear();

    let settled = false;
    const pending = queue.next().then((t) => {
      settled = true;
      return t;
    });
    await Promise.resolve();
    expect(settled).toBe(false);

    queue.set('fresh');
    await expect(pending).resolves.toBe('fresh');
  });

  it('rejects waiting and later callers on failure until a new token arrives', async () => {
    const queue = createTokenQueue();
    const pending = queue.next();
    const err = turnstileFailedError();

    queue.fail(err);

    await expect(pending).rejects.toBe(err);
    await expect(queue.next()).rejects.toBe(err);
    queue.set('recovered');
    await expect(queue.next()).resolves.toBe('recovered');
  });
});

describe('turnstileFailedError', () => {
  it('uses the API error code so forms share one handling path', () => {
    const err = turnstileFailedError();
    expect(err).toBeInstanceOf(ApiClientError);
    expect(err.code).toBe('TURNSTILE_001');
  });
});
