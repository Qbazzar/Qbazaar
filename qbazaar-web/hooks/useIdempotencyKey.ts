'use client';

import { useCallback, useState } from 'react';

function newKey(): string {
  return typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

/**
 * One idempotency key per user action. The key stays the same across
 * retries of a failed attempt (the API only replays successes), and
 * `renew()` after a success makes the next action a new one.
 */
export function useIdempotencyKey(): { key: string; renew: () => void } {
  const [key, setKey] = useState(newKey);
  const renew = useCallback(() => setKey(newKey()), []);
  return { key, renew };
}
