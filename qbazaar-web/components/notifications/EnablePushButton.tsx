'use client';

/**
 * Enable-push CTA for the notifications page.
 *
 * Renders nothing until mounted (isPushConfigured() reads `window`, so
 * deciding on the server would cause a hydration mismatch) and nothing at
 * all when push is unconfigured/unsupported — the feature stays invisible on
 * today's credential-less deployments.
 *
 * While enabled, foreground FCM messages (page visible — the service worker
 * only covers background delivery) are wired to the same code path the Echo
 * `notification.created` handler uses in `useUserChannel`: optimistically
 * bump the Zustand unread badge, then invalidate the notifications queries
 * so the next refetch reconciles with the server.
 */
import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { BellOffIcon, BellRingIcon, Loader2Icon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  enablePush,
  getLastPushError,
  getStoredPushToken,
  isPushConfigured,
  onForegroundMessage,
} from '@/lib/push/fcm';
import { notificationsKeys } from '@/lib/queries/notifications';
import { useNotificationsStore } from '@/store/notifications';
import { t } from '@/lib/i18n/messages';

type PushUiState = 'hidden' | 'idle' | 'pending' | 'enabled' | 'denied' | 'error';

function initialState(): PushUiState {
  if (!isPushConfigured()) return 'hidden';
  if (Notification.permission === 'denied') return 'denied';
  if (Notification.permission === 'granted' && getStoredPushToken()) {
    return 'enabled';
  }
  return 'idle';
}

export function EnablePushButton() {
  const qc = useQueryClient();
  // 'hidden' until the mount effect runs: the server (and the hydration
  // pass) must render the same empty markup regardless of browser state.
  const [state, setState] = useState<PushUiState>('hidden');

  useEffect(() => {
    setState(initialState());
  }, []);

  useEffect(() => {
    if (state !== 'enabled') return;
    let unsubscribe: (() => void) | null = null;
    let cancelled = false;

    void onForegroundMessage(() => {
      // Same strategy as the Echo handler: instant badge bump, then let the
      // invalidated queries re-sync the canonical counts from the server.
      useNotificationsStore.getState().incrementUnreadCount();
      qc.invalidateQueries({ queryKey: notificationsKeys.lists() });
      qc.invalidateQueries({ queryKey: notificationsKeys.unread() });
    }).then((unsub) => {
      if (cancelled) {
        unsub();
      } else {
        unsubscribe = unsub;
      }
    });

    return () => {
      cancelled = true;
      unsubscribe?.();
    };
  }, [state, qc]);

  if (state === 'hidden') return null;

  if (state === 'enabled') {
    return (
      <p
        role="status"
        className="text-muted-foreground inline-flex items-center gap-1.5 text-sm"
      >
        <BellRingIcon className="size-3.5" aria-hidden />
        {t('notifications.push.enabled', 'إشعارات المتصفح مفعّلة')}
      </p>
    );
  }

  if (state === 'denied') {
    return (
      <p
        role="status"
        className="text-muted-foreground inline-flex items-center gap-1.5 text-sm"
      >
        <BellOffIcon className="size-3.5" aria-hidden />
        {t('notifications.push.denied', 'الإشعارات محظورة من إعدادات المتصفح')}
      </p>
    );
  }

  const handleEnable = async () => {
    setState('pending');
    const result = await enablePush();
    if (result === 'enabled') setState('enabled');
    else if (result === 'denied') setState('denied');
    else if (result === 'unsupported') setState('hidden');
    else setState('error');
  };

  return (
    <div className="inline-flex flex-col items-start gap-1">
      <Button
        type="button"
        variant="outline"
        size="default"
        className="rounded-full"
        disabled={state === 'pending'}
        onClick={() => void handleEnable()}
      >
        {state === 'pending' ? (
          <Loader2Icon className="size-3.5 animate-spin" aria-hidden />
        ) : (
          <BellRingIcon className="size-3.5" aria-hidden />
        )}
        {t('notifications.push.enable', 'فعّل إشعارات المتصفح')}
      </Button>
      {state === 'error' ? (
        <p role="alert" className="text-destructive max-w-xs text-xs">
          {t(
            'notifications.push.error',
            'تعذّر تفعيل إشعارات المتصفح، حاول مرة أخرى',
          )}
          {getLastPushError() ? (
            <span className="text-ink-400 mt-0.5 block break-words font-mono text-[10px]">
              {getLastPushError()}
            </span>
          ) : null}
        </p>
      ) : null}
    </div>
  );
}
