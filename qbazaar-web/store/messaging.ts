/**
 * Messaging store — Zustand.
 *
 * Holds:
 * - `activeConversationId` — drives the right pane of the inbox without
 *   forcing a remount when the URL flips between `?c=...` values.
 * - `unreadCount` — mirror of the server-side total so the header badge can
 *   read it without subscribing to TanStack's query cache (cheaper for any
 *   component that only needs the number).
 *
 * The store is intentionally non-persistent — both fields are recomputed on
 * each session from the server.
 */
import { create } from 'zustand';

export interface MessagingState {
  activeConversationId: string | null;
  unreadCount: number;
  setActiveConversation: (id: string | null) => void;
  setUnreadCount: (n: number) => void;
  reset: () => void;
}

export const useMessagingStore = create<MessagingState>((set) => ({
  activeConversationId: null,
  unreadCount: 0,
  setActiveConversation: (id) => set({ activeConversationId: id }),
  setUnreadCount: (n) => set({ unreadCount: n }),
  reset: () => set({ activeConversationId: null, unreadCount: 0 }),
}));

/** Non-reactive accessor for use outside React (e.g. Echo callbacks). */
export function setUnreadCountNonReactive(n: number): void {
  useMessagingStore.getState().setUnreadCount(n);
}
