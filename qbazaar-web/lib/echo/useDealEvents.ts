'use client';

import { useEffect, useRef } from 'react';

import { getEcho } from './client';

/** Events the conversation channel carries for the chat cards beyond the ones the thread already handles. */
export const DEAL_EVENTS = [
  'purchase_request.updated',
  'purchase_request.accepted',
  'purchase_request.rejected',
  'purchase_request.cancelled',
  'purchase_request.paid',
  'offer.countered',
] as const;

export type DealEvent = (typeof DEAL_EVENTS)[number];

interface DealChannel {
  listen: (event: string, callback: (payload: unknown) => void) => unknown;
  stopListening: (event: string, callback?: (payload: unknown) => void) => unknown;
}

/**
 * Listens on the open conversation's channel for card changes made by the
 * other side. The thread view owns the subscription (and leaves it); this
 * only adds and removes its own callbacks, so it never tears it down.
 */
export function useDealEvents(
  conversationId: string | null,
  onEvent: (event: DealEvent, payload: unknown) => void,
  enabled = true,
): void {
  const handlerRef = useRef(onEvent);
  useEffect(() => {
    handlerRef.current = onEvent;
  });

  useEffect(() => {
    if (!conversationId || !enabled) return;
    let cancelled = false;
    let detach: (() => void) | null = null;

    void getEcho().then((echo) => {
      if (!echo || cancelled) return;
      const channel: DealChannel = echo.private(`conversation.${conversationId}`);
      // Laravel sends `broadcastAs` names with a leading dot; listen for both forms, as the thread does.
      const bindings = DEAL_EVENTS.flatMap((event) => {
        const callback = (payload: unknown) => handlerRef.current(event, payload);
        return [`.${event}`, event].map((name) => ({ name, callback }));
      });
      for (const { name, callback } of bindings) channel.listen(name, callback);
      detach = () => {
        for (const { name, callback } of bindings) {
          try {
            channel.stopListening(name, callback);
          } catch {
            // The thread may already have left the channel.
          }
        }
      };
    });

    return () => {
      cancelled = true;
      detach?.();
    };
  }, [conversationId, enabled]);
}

/** A string field of the entity a realtime frame carries under `key`, e.g. `{ purchase_request: { id } }`. */
export function payloadField(payload: unknown, key: string, field = 'id'): string | null {
  if (!payload || typeof payload !== 'object') return null;
  const entity = (payload as Record<string, unknown>)[key];
  if (!entity || typeof entity !== 'object') return null;
  const value = (entity as Record<string, unknown>)[field];
  return typeof value === 'string' ? value : null;
}
