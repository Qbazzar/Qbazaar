/**
 * Empty state shown on the favorites + (optionally) recently-viewed pages.
 *
 * Friendly coral heart + a single CTA back to the browse experience. Pure
 * presentational — the parent decides when to render it.
 */
import Link from 'next/link';
import { HeartIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n/messages';

interface Props {
  title?: string;
  description?: string;
  ctaLabel?: string;
  ctaHref?: string;
}

export function FavoritesEmptyState({
  title,
  description,
  ctaLabel,
  ctaHref = '/ads',
}: Props) {
  return (
    <div className="border-ink-200 bg-card flex flex-col items-center gap-3 rounded-2xl border border-dashed px-6 py-12 text-center">
      <div className="bg-coral/10 text-coral grid size-14 place-items-center rounded-full">
        <HeartIcon className="size-6" aria-hidden="true" />
      </div>
      <h2 className="font-display text-ink-900 text-xl">
        {title ?? t('favorites.empty.title', 'لم تحفظ أي إعلان بعد')}
      </h2>
      <p className="text-ink-500 max-w-sm text-sm">
        {description ??
          t(
            'favorites.empty.description',
            'احفظ الإعلانات التي تثير اهتمامك لتجدها بسهولة لاحقاً.',
          )}
      </p>
      <Button
        asChild
        className="bg-coral hover:bg-coral/90 mt-2 rounded-full text-white"
      >
        <Link href={ctaHref}>
          {ctaLabel ?? t('favorites.empty.cta', 'تصفّح الإعلانات')}
        </Link>
      </Button>
    </div>
  );
}
