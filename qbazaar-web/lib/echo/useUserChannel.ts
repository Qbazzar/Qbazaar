'use client';

/**
 * Subscribe to the private `user.{id}` channel so the badge + inbox previews
 * stay live across the whole app.
 *
 * Events handled:
 *  - `message.sent`        — a new message landed in a conversation I'm in
 *  - `conversation.read`   — someone else read messages, refresh unread count
 *
 * Strategy is to invalidate the relevant TanStack queries on every event;
 * the existing query hooks then re-fetch their own minimal payloads. This
 * keeps the socket layer dumb and reuses every caching/staleness rule we
 * already wrote for REST.
 */
import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import {
  appendIncomingMessageToCache,
  messagingKeys,
} from '@/lib/queries/messaging';
import { getEcho } from './client';
import type { Message } from '@/lib/api/types';

interface MessageSentPayload {
  message: Message;
}

interface ConversationReadPayload {
  conversation_id: string;
  read_at: string;
  reader_id: string;
}

export function useUserChannel(userId: string | null | undefined): void {
  const qc = useQueryClient();

  useEffect(() => {
    if (!userId) return;
    let cancelled = false;
    let cleanup: (() => void) | null = null;

    (async () => {
      const echo = await getEcho();
      if (!echo || cancelled) return;

      const channelName = `user.${userId}`;
      const channel = echo.private(channelName);

      const onMessageSent = (payload: unknown) => {
        const p = payload as MessageSentPayload | Message;
        // Backend may emit the raw Message OR wrap it in `{ message }`.
        const message = 'message' in p ? p.message : (p as Message);
        if (!message?.conversation_id) return;
        appendIncomingMessageToCache(qc, message);
        qc.invalidateQueries({ queryKey: messagingKeys.lists() });
        qc.invalidateQueries({ queryKey: messagingKeys.unread() });
      };

      const onConversationRead = (payload: unknown) => {
        const p = payload as ConversationReadPayload;
        if (!p?.conversation_id) return;
        qc.invalidateQueries({ queryKey: messagingKeys.unread() });
        qc.invalidateQueries({ queryKey: messagingKeys.lists() });
        qc.invalidateQueries({
          queryKey: messagingKeys.messages(p.conversation_id),
        });
      };

      channel.listen('.message.sent', onMessageSent);
      channel.listen('message.sent', onMessageSent);
      channel.listen('.conversation.read', onConversationRead);
      channel.listen('conversation.read', onConversationRead);

      cleanup = () => {
        try {
          channel.stopListening('.message.sent');
          channel.stopListening('message.sent');
          channel.stopListening('.conversation.read');
          channel.stopListening('conversation.read');
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
  }, [userId, qc]);
}
