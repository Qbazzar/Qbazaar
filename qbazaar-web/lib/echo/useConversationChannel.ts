'use client';

/**
 * Subscribe to the private `conversation.{id}` channel for as long as the
 * thread is open. The caller passes a `onMessage` callback so the chat view
 * can append incoming messages straight to its local list without a re-fetch.
 *
 * Auto-cleans on unmount and on conversation switch.
 */
import { useEffect } from 'react';
import { getEcho } from './client';
import type { Message } from '@/lib/api/types';

interface MessageSentPayload {
  message: Message;
}

export function useConversationChannel(
  conversationId: string | null | undefined,
  onMessage: (message: Message) => void,
): void {
  useEffect(() => {
    if (!conversationId) return;
    let cancelled = false;
    let cleanup: (() => void) | null = null;

    (async () => {
      const echo = await getEcho();
      if (!echo || cancelled) return;

      const channelName = `conversation.${conversationId}`;
      const channel = echo.private(channelName);

      const handler = (payload: unknown) => {
        const p = payload as MessageSentPayload | Message;
        const message = 'message' in p ? p.message : (p as Message);
        if (!message?.id) return;
        onMessage(message);
      };

      channel.listen('.message.sent', handler);
      channel.listen('message.sent', handler);

      cleanup = () => {
        try {
          channel.stopListening('.message.sent');
          channel.stopListening('message.sent');
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
  }, [conversationId, onMessage]);
}
