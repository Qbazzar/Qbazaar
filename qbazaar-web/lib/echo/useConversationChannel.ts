'use client';

/**
 * Subscribe to the private `conversation.{id}` channel for as long as the
 * thread is open. The caller passes callbacks so the chat view can react
 * to incoming messages and offer-status changes without a re-fetch.
 *
 * Sprint 9: the same channel now carries `offer.*` events (`offer.created`,
 * `offer.accepted`, `offer.rejected`, `offer.withdrawn`, `offer.expired`).
 * Each event ships an `{ offer, conversation_id }` envelope so we share the
 * one unified handler.
 *
 * Auto-cleans on unmount and on conversation switch.
 */
import { useEffect, useRef } from 'react';
import { getEcho } from './client';
import type { Message, Offer } from '@/lib/api/types';

interface MessageSentPayload {
  message: Message;
}

interface OfferEventPayload {
  offer: Offer;
  conversation_id: string;
}

export type OfferEvent =
  | 'offer.created'
  | 'offer.accepted'
  | 'offer.rejected'
  | 'offer.withdrawn'
  | 'offer.expired';

interface ConversationChannelHandlers {
  onMessage?: (message: Message) => void;
  onOfferEvent?: (event: OfferEvent, offer: Offer) => void;
}

/**
 * Subscribe to the conversation channel. Supports two call shapes for
 * backward compatibility: passing a bare `onMessage` callback (legacy),
 * or an object of named handlers (Sprint 9+).
 */
export function useConversationChannel(
  conversationId: string | null | undefined,
  handlers:
    | ((message: Message) => void)
    | ConversationChannelHandlers,
): void {
  // Stash the handlers in a ref so we can keep them up to date without
  // re-subscribing on every render (Echo channel teardown is expensive
  // because it round-trips through `/broadcasting/auth`).
  const handlersRef = useRef<ConversationChannelHandlers>({});
  handlersRef.current =
    typeof handlers === 'function' ? { onMessage: handlers } : handlers;

  useEffect(() => {
    if (!conversationId) return;
    let cancelled = false;
    let cleanup: (() => void) | null = null;

    (async () => {
      const echo = await getEcho();
      if (!echo || cancelled) return;

      const channelName = `conversation.${conversationId}`;
      const channel = echo.private(channelName);

      const messageHandler = (payload: unknown) => {
        const p = payload as MessageSentPayload | Message;
        const message = 'message' in p ? p.message : (p as Message);
        if (!message?.id) return;
        handlersRef.current.onMessage?.(message);
      };

      const offerHandlerFactory = (event: OfferEvent) => (payload: unknown) => {
        const p = payload as OfferEventPayload | Offer;
        const offer = 'offer' in p ? p.offer : (p as Offer);
        if (!offer?.id) return;
        handlersRef.current.onOfferEvent?.(event, offer);
      };

      const offerEvents: OfferEvent[] = [
        'offer.created',
        'offer.accepted',
        'offer.rejected',
        'offer.withdrawn',
        'offer.expired',
      ];

      // Laravel broadcasts events with a leading `.` when the broadcaster
      // is configured with `broadcastAs` — we listen for both shapes so the
      // client survives either backend convention.
      channel.listen('.message.sent', messageHandler);
      channel.listen('message.sent', messageHandler);

      const offerListeners: Array<{ name: string; handler: (p: unknown) => void }> = [];
      for (const event of offerEvents) {
        const handler = offerHandlerFactory(event);
        const dotted = `.${event}`;
        channel.listen(dotted, handler);
        channel.listen(event, handler);
        offerListeners.push({ name: dotted, handler }, { name: event, handler });
      }

      cleanup = () => {
        try {
          channel.stopListening('.message.sent');
          channel.stopListening('message.sent');
          for (const { name } of offerListeners) {
            channel.stopListening(name);
          }
          echo.leave(channelName);
        } catch {
          // ignore — socket may already be torn down
        }
      };
    })();

    return () => {
      cancelled = true;
      cleanup?.();
    };
  }, [conversationId]);
}
