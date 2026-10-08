import Image from 'next/image';
import Link from 'next/link';
import { MapPin, type LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

import { initialsOf } from './Avatar';
import { Badge } from './Badge';
import { Icon } from './Icon';

/** The design gives every company without a logo one of these tinted tiles. */
const LOGO_TONES = [
  'border-qb-brand bg-qb-brand-soft text-qb-brand-on-soft',
  'border-qb-success bg-qb-success-soft text-qb-success',
  'border-qb-info bg-qb-info-soft text-qb-info',
  'border-qb-danger bg-qb-danger-soft text-qb-danger',
] as const;

/** Same key, same colour: a company keeps its tile across pages and visits. */
export function logoToneFor(key: string): string {
  let hash = 0;
  for (const character of key) hash = (hash * 31 + character.charCodeAt(0)) >>> 0;
  return LOGO_TONES[hash % LOGO_TONES.length];
}

export interface CompanyCardProps {
  href: string;
  name: string;
  /** The company logo; its initials on a tinted tile stand in without one. */
  logoUrl?: string | null;
  /** Picks the tile colour, usually the company id. */
  toneKey: string;
  /** Already formatted line under the name, e.g. the city ("Al Wakrah"). */
  meta?: string;
  /** Icon of the meta line; the design's map pin by default. */
  metaIcon?: LucideIcon;
  /** Already formatted, e.g. "12 Ads". */
  count: string;
  headingLevel?: 'h2' | 'h3';
  className?: string;
}

/**
 * A company tile of the companies directory (179:4492 / 532:31445 /
 * 620:28373): logo, name, one meta line and the ads count. The name link is
 * stretched over the card, so the whole card is one tab stop.
 */
export function CompanyCard({
  href,
  name,
  logoUrl,
  toneKey,
  meta,
  metaIcon = MapPin,
  count,
  headingLevel: Heading = 'h3',
  className,
}: CompanyCardProps) {
  return (
    <article
      className={cn(
        'relative flex flex-col items-center rounded-qb-2xl border border-qb-line bg-qb-surface px-3 pt-[17px] pb-[15px] text-center font-qb shadow-qb-card',
        'transition-[box-shadow,translate] duration-200 hover:-translate-y-[3px] hover:shadow-qb-hover motion-reduce:transition-none motion-reduce:hover:translate-y-0',
        'has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-qb-brand-active',
        'qb-tablet:px-4 qb-tablet:pt-[25px] qb-tablet:pb-[23px]',
        className,
      )}
    >
      <CompanyLogo name={name} logoUrl={logoUrl} toneKey={toneKey} />
      <Heading className="mt-2 w-full truncate text-qb-caption font-semibold tracking-normal text-qb-ink-title qb-tablet:mt-4 qb-tablet:text-qb-body qb-tablet:font-medium">
        <Link href={href} className="outline-none after:absolute after:inset-0">
          {name}
        </Link>
      </Heading>
      {meta ? (
        <p className="mt-[11px] flex max-w-full items-center gap-1 text-qb-micro text-qb-ink-subtle qb-tablet:mt-2.5 qb-tablet:text-qb-caption">
          <Icon icon={metaIcon} size="sm" className="size-3 qb-tablet:size-3.5" />
          <span className="truncate">{meta}</span>
        </p>
      ) : null}
      <Badge
        tone="solid"
        className="mt-[11px] h-6 rounded-[4px] px-[9px] py-0 text-qb-micro qb-tablet:mt-3 qb-tablet:h-[25px] qb-tablet:text-qb-caption"
      >
        {count}
      </Badge>
    </article>
  );
}

function CompanyLogo({ name, logoUrl, toneKey }: Pick<CompanyCardProps, 'name' | 'logoUrl' | 'toneKey'>) {
  const tile = 'relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-[14px] border qb-tablet:size-[66px] qb-tablet:rounded-qb-xl';
  if (logoUrl) {
    return (
      <span className={cn(tile, 'border-qb-line bg-qb-surface')}>
        <Image src={logoUrl} alt="" fill sizes="66px" className="object-cover" />
      </span>
    );
  }
  return (
    <span aria-hidden="true" className={cn(tile, 'text-qb-body-lg font-semibold uppercase qb-tablet:text-qb-h4', logoToneFor(toneKey))}>
      {initialsOf(name)}
    </span>
  );
}
