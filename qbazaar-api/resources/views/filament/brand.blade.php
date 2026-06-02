{{--
  Filament admin brand mark — Q glyph + wordmark + Arabic subscript.

  Behaviour:
   - Logo (SVG): always visible, fixed 36×36 so it fits the brand-logo-height
     reserved by the panel (2.25rem) without overflowing.
   - Wordmark + subscript: hidden when the sidebar is collapsed (via CSS hook
     in resources/views/filament/admin/head.blade.php) and truncated when the
     sidebar is narrower than the natural label width so it never bleeds past
     the sidebar gutter.
   - Coral bars stay coral; cream bars switch fill so they read on both
     light and dark backgrounds.
--}}
<a href="{{ filament()->getUrl() }}"
   class="qb-brand inline-flex items-center gap-2.5 leading-none select-none text-gray-900 dark:text-white no-underline overflow-hidden">
    <svg width="36" height="36" viewBox="0 0 80 80" aria-hidden="true" class="qb-brand__glyph shrink-0">
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
    <span class="qb-brand__wordmark flex min-w-0 flex-col gap-[2px] leading-none">
        <span class="text-[15px] font-extrabold tracking-[0.04em] truncate">QBAZAAR</span>
        <span class="text-[10px] tracking-[0.02em] opacity-70 truncate" dir="rtl">إدارة كيو بازار</span>
    </span>
</a>
