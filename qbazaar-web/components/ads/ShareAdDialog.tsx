'use client';

import { toast } from 'sonner';

import { CardDialog } from '@/components/design-system/CardDialog';
import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

interface ShareChannel {
  key: 'whatsapp' | 'facebook' | 'x' | 'email';
  /** The letter the reference draws in the round button. */
  glyph: string;
  tone: string;
  href: (url: string, title: string) => string;
}

/** WhatsApp, Facebook, X and Email, in the reference's order and colours. */
const CHANNELS: readonly ShareChannel[] = [
  {
    key: 'whatsapp',
    glyph: 'W',
    tone: 'bg-qb-whatsapp',
    href: (url, title) => `https://wa.me/?text=${encodeURIComponent(`${title} ${url}`)}`,
  },
  {
    key: 'facebook',
    glyph: 'f',
    tone: 'bg-qb-facebook',
    href: (url) => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
  },
  {
    key: 'x',
    glyph: '𝕏',
    tone: 'bg-qb-x',
    href: (url, title) => `https://x.com/intent/post?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`,
  },
  {
    key: 'email',
    glyph: '@',
    tone: 'bg-qb-brand',
    href: (url, title) => `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}`,
  },
];

interface ShareAdDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  /** The ad's address; built by the caller from the current page. */
  url: string;
}

/** "Share this ad" (product.html): four channel buttons, then the link with "Copy". */
export function ShareAdDialog({ open, onOpenChange, title, url }: ShareAdDialogProps) {
  const copy = () => {
    void navigator.clipboard
      ?.writeText(url)
      .then(() => toast.success(t('ads.share.copied')))
      .catch(() => toast.error(t('common.error')));
  };

  return (
    <CardDialog open={open} onOpenChange={onOpenChange} title={t('ads.share.title')}>
      <ul className="mt-5 mb-[22px] flex flex-wrap gap-3.5">
        {CHANNELS.map((channel) => {
          const external = channel.key !== 'email';
          return (
            <li key={channel.key} className="flex-[1_1_90px]">
              <a
                href={channel.href(url, title)}
                target={external ? '_blank' : undefined}
                rel={external ? 'noopener noreferrer' : undefined}
                className={cn('flex flex-col items-center gap-2 rounded-qb-md', focusRing)}
              >
                <span
                  aria-hidden="true"
                  className={cn('flex size-[52px] items-center justify-center rounded-full text-qb-body-lg font-bold text-qb-on-brand', channel.tone)}
                >
                  {channel.glyph}
                </span>
                <span className="text-qb-label leading-[1.15] text-qb-ink-body">{t(`ads.share.channels.${channel.key}`)}</span>
              </a>
            </li>
          );
        })}
      </ul>
      <div className="flex items-center gap-2.5 rounded-qb-lg border border-qb-line px-3.5 py-3">
        <span className="min-w-0 flex-1 truncate text-qb-caption text-qb-ink-faint">
          <bdi dir="ltr">{url.replace(/^https?:\/\//, '')}</bdi>
        </span>
        <button
          type="button"
          onClick={copy}
          className={cn(
            'shrink-0 cursor-pointer rounded-qb-sm bg-qb-brand px-[18px] py-[9px] text-qb-caption font-semibold text-qb-on-brand transition-colors hover:bg-qb-brand-hover',
            focusRing,
          )}
        >
          {t('ads.share.copy')}
        </button>
      </div>
    </CardDialog>
  );
}
