'use client';

/**
 * Auto-growing chat input.
 *
 * - Enter sends, Shift+Enter inserts a newline.
 * - The textarea auto-resizes up to 6 rows then scrolls internally.
 * - The send button is disabled while a message is in-flight to prevent
 *   accidental duplicates (the optimistic insert already shows on screen).
 * - The camera tile before the field sends a photo (the composer of messages.html).
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { Camera, Loader2, Send } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';
import { useSendImageMessageMutation, useSendMessageMutation } from '@/lib/queries/messaging';
import { apiErrorMessage } from '@/components/account/api-error-message';
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
const LINE_HEIGHT_PX = 22; // matches the 15 px text's normal leading
/** Same limits as the API's chat photos: JPEG, PNG or WebP up to 5 MB. */
const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

export function ChatInput({ conversationId, viewerRole, onTyping }: Props) {
  const [body, setBody] = useState('');
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  const mutation = useSendMessageMutation();
  const photoMutation = useSendImageMessageMutation();
  const { user } = useAuth();

  // Auto-grow the textarea up to MAX_ROWS lines, then enable internal scroll.
  const resize = useCallback(() => {
    const el = textareaRef.current;
    if (!el) return;
    el.style.height = 'auto';
    // Chrome counts a wrapped placeholder in scrollHeight; an empty field stays one line.
    if (!el.value) return;
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

  const onPickPhoto = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    if (!PHOTO_TYPES.includes(file.type)) return void toast.error(t('account.avatar.errors.type'));
    if (file.size > MAX_PHOTO_BYTES) return void toast.error(t('messaging.photo_too_large'));
    try {
      await photoMutation.mutateAsync({ conversationId, image: file });
    } catch (err) {
      if (!isPhoneNotVerifiedError(err)) toast.error(apiErrorMessage(err));
    }
  };

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        void submit();
      }}
      className="flex items-end gap-3 border-t border-qb-line bg-qb-surface px-4 py-4 qb-tablet:px-7 qb-tablet:py-5"
    >
      <label
        className={cn(
          'flex size-12 shrink-0 cursor-pointer items-center justify-center rounded-qb-lg bg-qb-fill-strong text-qb-ink-subtle has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-qb-brand-active',
          photoMutation.isPending && 'pointer-events-none opacity-60',
        )}
      >
        <input
          type="file"
          accept={PHOTO_TYPES.join(',')}
          onChange={(event) => void onPickPhoto(event)}
          disabled={photoMutation.isPending}
          aria-label={t('messaging.attach_photo')}
          className="sr-only"
        />
        {photoMutation.isPending ? (
          <Loader2 className="size-5 animate-spin" aria-hidden="true" />
        ) : (
          <Camera className="size-5" strokeWidth={1.6} aria-hidden="true" />
        )}
      </label>
      <div className="flex min-w-0 flex-1 items-center rounded-[14px] border border-qb-line bg-qb-surface px-[18px] py-[13px] focus-within:border-qb-brand">
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
          className="max-h-[148px] min-h-[26px] flex-1 resize-none bg-transparent py-0.5 font-qb text-qb-body-sm text-qb-ink outline-none placeholder:text-qb-placeholder"
          aria-label={t('messaging.placeholder', 'اكتب رسالتك…')}
        />
      </div>
      <button
        type="submit"
        disabled={mutation.isPending || body.trim().length === 0}
        className={cn(
          'inline-flex size-12 shrink-0 cursor-pointer items-center justify-center rounded-qb-lg bg-qb-brand text-qb-on-brand transition-colors hover:bg-qb-brand-hover',
          'disabled:cursor-not-allowed',
          focusRing,
        )}
        aria-label={t('messaging.send', 'إرسال')}
      >
        {mutation.isPending ? (
          <Loader2 className="size-5 animate-spin" aria-hidden="true" />
        ) : (
          <Send className="size-5 rtl:-scale-x-100" aria-hidden="true" />
        )}
      </button>
      {viewerRole === 'buyer' ? (
        <OfferComposer conversationId={conversationId} />
      ) : null}
    </form>
  );
}
