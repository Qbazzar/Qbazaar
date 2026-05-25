{{--
  Filament admin brand mark — Q-SVG glyph + "QBAZAAR" wordmark + Arabic subscript.
  The circle stroke and tail use `currentColor` so the icon adapts to Filament's
  light/dark themes automatically (text-ink-900 on light, text-white on dark).
  Coral bars stay coral; cream bars switch between cream and ink-900 so they
  read correctly on both backgrounds.
--}}
<a href="{{ filament()->getUrl() }}" class="inline-flex items-center gap-2.5 leading-none select-none text-gray-900 dark:text-white no-underline">
    <svg width="38" height="38" viewBox="0 0 80 80" aria-hidden="true" class="shrink-0">
        <circle cx="38" cy="38" r="32" fill="none" stroke="currentColor" stroke-width="6"/>
        <defs>
            <clipPath id="qb-admin-clip">
                <path d="M0 0 L36 0 L36 28 L18 38 L0 28 Z"/>
            </clipPath>
        </defs>
        <g transform="translate(20 24)" clip-path="url(#qb-admin-clip)">
            <rect x="0"  y="0" width="9" height="40" fill="#F37335"/>
            <rect x="9"  y="0" width="9" height="40" class="fill-white dark:fill-gray-900"/>
            <rect x="18" y="0" width="9" height="40" fill="#F37335"/>
            <rect x="27" y="0" width="9" height="40" class="fill-white dark:fill-gray-900"/>
        </g>
        <path d="M58 58 L72 72" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
    </svg>
    <span class="flex flex-col gap-[2px] leading-none">
        <span class="text-[18px] font-extrabold tracking-[0.04em]">QBAZAAR</span>
        <span class="text-[11px] tracking-[0.02em] opacity-75" dir="rtl">إدارة كيو بازار</span>
    </span>
</a>
