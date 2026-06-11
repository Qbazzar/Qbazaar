'use client';

/**
 * Typing indicator over Reverb client events ("whispers") on the private
 * `conversation.{id}` channel. Whispers are relayed peer-to-peer by the
 * socket server, so no backend endpoint or broadcast event is involved —
 * the channel auth already proves membership.
 *
 * Subscribing here reuses the same channel instance `useConversationChannel`
 * holds (`echo.private()` is idempotent per channel name), which is why
 * cleanup only detaches the whisper listener and never `echo.leave()`s:
 * leaving would tear down the message/offer listeners owned by that hook.
 *
 * Auto-cleans on unmount and on conversation switch.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, type EchoPrivateChannel } from './client';
import { useAuthStore } from '@/store/auth';

interface TypingWhisperPayload {
  user_id: string;
  name: string;
}

/** How long the indicator stays visible after the last incoming whisper. */
const TYPING_DECAY_MS = 3_000;

/** Minimum gap between outgoing whispers (leading edge throttle). */
const WHISPER_THROTTLE_MS = 2_000;

/**
 * Listen for the other participant's `typing` whispers and expose
 * `notifyTyping()` for the chat input to announce our own keystrokes.
 */
export function useTypingIndicator(
  conversationId: string | null | undefined,
): { typingName: string | null; notifyTyping: () => void } {
  const [typingName, setTypingName] = useState<string | null>(null);

  // Stash the auth user in a ref so whisper handlers always see the current
  // identity without re-subscribing on every render (Echo channel teardown
  // is expensive because it round-trips through `/broadcasting/auth`).
  const user = useAuthStore((s) => s.user);
  const userRef = useRef(user);
  userRef.current = user;

  const channelRef = useRef<EchoPrivateChannel | null>(null);
  const decayTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const lastWhisperAtRef = useRef(0);

  useEffect(() => {
    if (!conversationId) return;
    let cancelled = false;
    let cleanup: (() => void) | null = null;

    (async () => {
      const echo = await getEcho();
      if (!echo || cancelled) return;

      const channel = echo.private(`conversation.${conversationId}`);
      channelRef.current = channel;

      const typingHandler = (payload: unknown) => {
        const p = payload as Partial<TypingWhisperPayload> | null;
        if (!p?.user_id || !p.name) return;
        // Pusher doesn't echo client events back to the sender, but guard
        // anyway so a second tab of our own never shows "typing".
        if (p.user_id === userRef.current?.id) return;

        setTypingName(p.name);
        if (decayTimerRef.current) clearTimeout(decayTimerRef.current);
        decayTimerRef.current = setTimeout(() => {
          decayTimerRef.current = null;
          setTypingName(null);
        }, TYPING_DECAY_MS);
      };

      channel.listenForWhisper('typing', typingHandler);

      cleanup = () => {
        try {
          channel.stopListeningForWhisper('typing', typingHandler);
        } catch {
          // ignore — socket may already be torn down
        }
      };
    })();

    return () => {
      cancelled = true;
      cleanup?.();
      channelRef.current = null;
      if (decayTimerRef.current) {
        clearTimeout(decayTimerRef.current);
        decayTimerRef.current = null;
      }
      lastWhisperAtRef.current = 0;
      setTypingName(null);
    };
  }, [conversationId]);

  const notifyTyping = useCallback(() => {
    const channel = channelRef.current;
    const me = userRef.current;
    if (!channel || !me) return;

    const now = Date.now();
    if (now - lastWhisperAtRef.current < WHISPER_THROTTLE_MS) return;
    lastWhisperAtRef.current = now;

    try {
      channel.whisper('typing', { user_id: me.id, name: me.full_name });
    } catch {
      // ignore — typing announcements are best-effort
    }
  }, []);

  return { typingName, notifyTyping };
}
