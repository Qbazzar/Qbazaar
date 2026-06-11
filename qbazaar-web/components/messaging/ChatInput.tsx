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
import { SendIcon, Loader2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useSendMessageMutation } from '@/lib/queries/messaging';
import { t } from '@/lib/i18n/messages';
import { toast } from 'sonner';
import { translateMaybeKey } from '@/lib/i18n/messages';
import { OfferComposer } from './OfferComposer';

interface Props {
  conversationId: string;
  /** Buyers see the "Make offer" affordance; sellers don't. */
  viewerRole?: 'buyer' | 'seller';
  /** Fired on every keystroke so the parent can whisper a typing event. */
  onTyping?: () => void;
}

const MAX_ROWS = 6;
const LINE_HEIGHT_PX = 22; // matches text-sm leading

export function ChatInput({ conversationId, viewerRole, onTyping }: Props) {
  const [body, setBody] = useState('');
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  const mutation = useSendMessageMutation();

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
      const fallback = t('messaging.errors.send_failed', 'تعذّر إرسال الرسالة');
      const message =
        err && typeof err === 'object' && 'messageKey' in err
          ? translateMaybeKey((err as { messageKey?: string }).messageKey) || fallback
          : fallback;
      toast.error(message);
      // Restore the user's draft so they don't lose what they typed.
      setBody(trimmed);
    }
  };

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
      className="border-ink-200 bg-card flex items-end gap-2 border-t p-3"
    >
      <Textarea
        ref={textareaRef}
        value={body}
        onChange={(e) => {
          setBody(e.target.value);
          onTyping?.();
        }}
        onKeyDown={onKeyDown}
        placeholder={t('messaging.placeholder', 'اكتب رسالتك…')}
        rows={1}
        className={cn(
          'bg-cream-100 border-none flex-1 resize-none rounded-2xl px-4 py-2 text-sm',
        )}
        aria-label={t('messaging.placeholder', 'اكتب رسالتك…')}
      />
      {viewerRole === 'buyer' ? (
        <OfferComposer conversationId={conversationId} />
      ) : null}
      <Button
        type="submit"
        size="icon"
        disabled={mutation.isPending || body.trim().length === 0}
        className="bg-coral hover:bg-coral/90 shrink-0 rounded-full text-white"
        aria-label={t('messaging.send', 'إرسال')}
      >
        {mutation.isPending ? (
          <Loader2Icon className="size-4 animate-spin" aria-hidden />
        ) : (
          <SendIcon className="size-4 rtl:rotate-180" aria-hidden />
        )}
      </Button>
    </form>
  );
}
