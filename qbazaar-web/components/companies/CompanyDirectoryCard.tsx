import Image from 'next/image';
import Link from 'next/link';
import { Users } from 'lucide-react';

import { initialsOf } from '@/components/design-system/Avatar';
import { Icon } from '@/components/design-system/Icon';
import { cn } from '@/lib/utils';

/** The five tints companies.html gives the logo tiles, in its order. */
const LOGO_TONES = [
  'bg-qb-brand-soft text-qb-brand',
  'bg-qb-success-soft text-qb-success',
  'bg-qb-tile-blue-soft text-qb-info',
  'bg-qb-tile-cyan-soft text-qb-tile-cyan',
  'bg-qb-tile-red-soft text-qb-tile-red',
] as const;

/** Same key, same tint: a company keeps its tile across pages and visits. */
function logoTone(key: string): string {
  let hash = 0;
  for (const character of key) hash = (hash * 31 + character.charCodeAt(0)) >>> 0;
  return LOGO_TONES[hash % LOGO_TONES.length];
}

interface CompanyDirectoryCardProps {
  href: string;
  name: string;
  logoUrl?: string | null;
  /** Picks the tile tint, usually the company id. */
  toneKey: string;
  /** Already formatted line under the name. */
  meta: string;
  /** Already formatted, e.g. "12 Ads". */
  count: string;
  className?: string;
}

/**
 * A company of the directory as companies.html draws it: a flat r16 card
 * (24 / 22 px padding) with the 56 px r14 logo tile, the name in 16 px / 500,
 * a 13 px / 500 grey line and the peach ads-count chip. The name link covers the
 * card, so the card is one tab stop; it lifts and shades under the pointer
 * like every reference card.
 */
export function CompanyDirectoryCard({ href, name, logoUrl, toneKey, meta, count, className }: CompanyDirectoryCardProps) {
  return (
    <article
      className={cn(
        'relative flex h-full flex-col items-center rounded-qb-xl border border-qb-line bg-qb-surface px-[22px] py-6 text-center font-qb',
        'transition-[box-shadow,translate] duration-200 hover:-translate-y-[3px] hover:shadow-qb-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0',
        'has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active has-[a:focus-visible]:outline-solid',
        className,
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          'relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-[14px] text-qb-body-lg font-semibold uppercase',
          logoUrl ? 'bg-qb-surface' : logoTone(toneKey),
        )}
      >
        {logoUrl ? <Image src={logoUrl} alt="" fill sizes="56px" className="object-cover" /> : initialsOf(name)}
      </span>
      <h2 className="mt-3 w-full text-qb-body font-medium tracking-normal break-words text-qb-ink">
        <Link href={href} dir="auto" className="outline-none after:absolute after:inset-0">
          {name}
        </Link>
      </h2>
      <p className="mt-3 flex max-w-full items-center gap-[5px] text-qb-label font-medium text-qb-ink-subtle">
        <Icon icon={Users} className="size-3.5" strokeWidth={1.6} />
        <span className="truncate">{meta}</span>
      </p>
      <span className="mt-2.5 rounded-qb-sm bg-qb-brand-soft px-3.5 py-[5px] text-qb-label font-medium text-qb-brand">{count}</span>
    </article>
  );
}
