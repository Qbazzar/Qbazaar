import * as React from 'react';
import { cn } from '@/lib/utils';

interface LogoProps {
  /** Render the BAZAAR wordmark + Arabic subscript next to the glyph (default: true). */
  withWordmark?: boolean;
  /** Extra classes applied to the wrapping span. */
  className?: string;
  /** Pixel size of the SVG glyph. */
  size?: number;
  /** Use inverted (white) wordmark colors for placement over coral backgrounds. */
  inverted?: boolean;
}

/**
 * QBazaar brand mark — Q-shaped SVG (coral + cream barcode fill inside a
 * circle, with the Q-tail crossbar) paired with the "BAZAAR" Latin wordmark
 * and the "كيو بازار" Arabic subscript. Source: QBFront/index.html.
 */
export function Logo({
  withWordmark = true,
  className,
  size = 42,
  inverted = false,
}: LogoProps) {
  const clipId = React.useId();
  return (
    <span
      className={cn(
        'inline-flex items-center gap-2.5 leading-none select-none',
        inverted ? 'text-white' : 'text-ink-900',
        className,
      )}
    >
      <svg
        className="shrink-0"
        width={size}
        height={size}
        viewBox="0 0 80 80"
        aria-label="QBazaar"
      >
        <circle
          cx="38"
          cy="38"
          r="32"
          fill="none"
          stroke="currentColor"
          strokeWidth="6"
        />
        <defs>
          <clipPath id={clipId}>
            <path d="M0 0 L36 0 L36 28 L18 38 L0 28 Z" />
          </clipPath>
        </defs>
        <g transform="translate(20 24)" clipPath={`url(#${clipId})`}>
          <rect x="0" y="0" width="9" height="40" fill="#F37335" />
          <rect
            x="9"
            y="0"
            width="9"
            height="40"
            fill={inverted ? '#FFFFFF' : '#FFFFFF'}
          />
          <rect x="18" y="0" width="9" height="40" fill="#F37335" />
          <rect
            x="27"
            y="0"
            width="9"
            height="40"
            fill={inverted ? '#FFFFFF' : '#FFFFFF'}
          />
        </g>
        <path
          d="M58 58 L72 72"
          stroke="currentColor"
          strokeWidth="6"
          strokeLinecap="round"
        />
      </svg>
      {withWordmark ? (
        <span className="flex flex-col gap-[2px] leading-none">
          <span
            className={cn(
              'text-[22px] font-extrabold tracking-[0.04em]',
              inverted ? 'text-white' : 'text-ink-900',
            )}
          >
            BAZAAR
          </span>
          <span
            className={cn(
              'font-arabic text-[13px] tracking-[0.02em] opacity-75',
              inverted ? 'text-white/85' : 'text-ink-700',
            )}
            dir="rtl"
          >
            كيو بازار
          </span>
        </span>
      ) : null}
    </span>
  );
}

