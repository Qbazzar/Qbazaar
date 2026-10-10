import { clsx, type ClassValue } from "clsx"
import { extendTailwindMerge } from "tailwind-merge"

/**
 * Names of the `qb-` design tokens (styles/design-tokens.css) whose utilities
 * tailwind-merge cannot classify on its own: without them `text-qb-body` would
 * be read as a text colour and dropped next to `text-qb-ink`, and a later
 * `px-5` would not replace `px-qb-gutter`.
 */
export const QB_TOKEN_NAMES = {
  text: [
    "qb-display", "qb-h1", "qb-h2", "qb-h3", "qb-h4", "qb-h5", "qb-body-lg",
    "qb-body", "qb-body-sm", "qb-caption", "qb-label", "qb-micro", "qb-tiny",
  ],
  radius: ["qb-xs", "qb-sm", "qb-md", "qb-lg", "qb-xl", "qb-2xl", "qb-pill"],
  shadow: [
    "qb-card", "qb-raised", "qb-soft", "qb-hover", "qb-brand", "qb-header",
    "qb-popover", "qb-float", "qb-control", "qb-lift", "qb-drawer",
    "qb-dropdown", "qb-menu", "qb-panel", "qb-langpop", "qb-toast",
    "qb-modal", "qb-logo", "qb-sheet", "qb-search", "qb-brand-tight",
  ],
  spacing: ["qb-gutter"],
} as const

const twMerge = extendTailwindMerge({
  extend: {
    theme: {
      text: [...QB_TOKEN_NAMES.text],
      radius: [...QB_TOKEN_NAMES.radius],
      shadow: [...QB_TOKEN_NAMES.shadow],
      spacing: [...QB_TOKEN_NAMES.spacing],
    },
  },
})

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

/**
 * Format a timestamp as a localised relative-time phrase (e.g. "5 minutes ago",
 * "قبل 5 دقائق"). Falls back to a short absolute date if the value is in the
 * future or older than 30 days.
 */
export function formatRelativeTime(
  isoTimestamp: string,
  locale: string = 'ar',
): string {
  const date = new Date(isoTimestamp);
  if (Number.isNaN(date.getTime())) return '';

  const now = Date.now();
  const diffMs = now - date.getTime();
  const diffSec = Math.round(diffMs / 1000);

  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

  if (Math.abs(diffSec) < 60) return rtf.format(-diffSec, 'second');
  const diffMin = Math.round(diffSec / 60);
  if (Math.abs(diffMin) < 60) return rtf.format(-diffMin, 'minute');
  const diffHr = Math.round(diffMin / 60);
  if (Math.abs(diffHr) < 24) return rtf.format(-diffHr, 'hour');
  const diffDay = Math.round(diffHr / 24);
  if (Math.abs(diffDay) < 30) return rtf.format(-diffDay, 'day');

  // Older than 30 days — show the short date so the relative phrase doesn't
  // become misleading (e.g. "12 months ago" instead of an actual date).
  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(date);
}
