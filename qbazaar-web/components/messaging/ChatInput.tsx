'use client';

/**
 * Auto-growing chat input.
 *
 * - Enter sends, Shift+Enter inserts a newline.
 * - The textarea auto-resizes up to 6 rows then scrolls internally.
 * - The send button is disabled while a message is in-flight to prevent
 *   accidental duplicates (the optimistic insert already shows on screen).
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2, Send } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';
import { useSendMessageMutation } from '@/lib/queries/messaging';
import { t } from '@/lib/i18n/messages';
import { toast } from 'sonner';
import { translateMaybeKey } from '@/lib/i18n/messages';
import { OfferComposer } from './OfferComposer';
import { PhoneVerificationNotice } from '@/components/auth/PhoneVerificationNotice';
import { useAuth } from '@/hooks/useAuth';
import { isPhoneNotVerifiedError } from '@/lib/auth/phone-gate';

interface Props {
  conversationId: string;
  /** Buyers see the "Make offer" affordance; sellers don't. */
  viewerRole?: 'buyer' | 'seller';
  /** Fired on every keystroke so the parent can whisper a typing event. */
  onTyping?: () => void;
}

const MAX_ROWS = 6;
const LINE_HEIGHT_PX = 21; // matches the 14 px text's normal leading

export function ChatInput({ conversationId, viewerRole, onTyping }: Props) {
  const [body, setBody] = useState('');
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  const mutation = useSendMessageMutation();
  const { user } = useAuth();

  // Auto-grow the textarea up to MAX_ROWS lines, then enable internal scroll.
  const resize = useCallback(() => {
    const el = textareaRef.current;
    if (!el) return;
    el.style.height = 'auto';
    const max = MAX_ROWS * LINE_HEIGHT_PX + 16;
    el.style.height = `${Math.min(el.scrollHeight, max)}px`;
  }, []);

  useEffect(() => {
    resize();
  }, [body, resize]);

  const submit = async () => {
    const trimmed = body.trim();
    if (!trimmed || mutation.isPending) return;
    setBody('');
    try {
      await mutation.mutateAsync({ conversationId, body: trimmed });
    } catch (err) {
      // Restore the user's draft so they don't lose what they typed.
      setBody(trimmed);
      if (isPhoneNotVerifiedError(err)) return;
      const fallback = t('messaging.errors.send_failed', 'تعذّر إرسال الرسالة');
      const message =
        err && typeof err === 'object' && 'messageKey' in err
          ? translateMaybeKey((err as { messageKey?: string }).messageKey) || fallback
          : fallback;
      toast.error(message);
    }
  };

  if (user && !user.phone_verified) {
    return <PhoneVerificationNotice context="messaging" compact />;
  }

  const onKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      void submit();
    }
  };

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        void submit();
      }}
      className="flex items-end gap-2.5 border-t border-qb-line bg-qb-surface px-[21px] py-3 qb-tablet:border-t-0 qb-tablet:px-6 qb-tablet:pb-6 qb-desktop:px-6 qb-desktop:pb-8"
    >
      <div className="flex min-w-0 flex-1 items-end gap-2 rounded-qb-md border border-qb-line bg-qb-surface py-1.5 ps-4 pe-2 shadow-qb-soft focus-within:border-qb-brand qb-desktop:rounded-qb-sm">
        <textarea
          ref={textareaRef}
          value={body}
          onChange={(e) => {
            setBody(e.target.value);
            onTyping?.();
          }}
          onKeyDown={onKeyDown}
          placeholder={t('messaging.placeholder', 'اكتب رسالتك…')}
          rows={1}
          className="max-h-[148px] min-h-9 flex-1 resize-none bg-transparent py-2 font-qb text-qb-caption text-qb-ink outline-none placeholder:text-qb-placeholder"
          aria-label={t('messaging.placeholder', 'اكتب رسالتك…')}
        />
        <button
          type="submit"
          disabled={mutation.isPending || body.trim().length === 0}
          className={cn(
            'mb-0.5 inline-flex h-[29px] w-[31px] shrink-0 items-center justify-center rounded-qb-xs bg-qb-brand text-white shadow-qb-soft transition-colors hover:bg-qb-brand-hover',
            'disabled:cursor-not-allowed disabled:bg-qb-line disabled:text-qb-surface',
            focusRing,
          )}
          aria-label={t('messaging.send', 'إرسال')}
        >
          {mutation.isPending ? (
            <Loader2 className="size-4 animate-spin" aria-hidden="true" />
          ) : (
            <Send className="size-4 rtl:-scale-x-100" aria-hidden="true" />
          )}
        </button>
      </div>
      {viewerRole === 'buyer' ? (
        <OfferComposer conversationId={conversationId} />
      ) : null}
    </form>
  );
}
