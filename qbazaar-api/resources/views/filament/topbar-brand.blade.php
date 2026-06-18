{{--
  Brand mark injected into the admin TOPBAR via PanelsRenderHook::TOPBAR_START.
  Sized to sit comfortably inside the ~4rem topbar. The sidebar-header brand is
  hidden in CSS so this is the single logo in the panel.
--}}
<a href="{{ filament()->getUrl() }}"
   class="qb-topbar-logo"
   aria-label="QBazaar">
    <svg width="30" height="30" viewBox="0 0 80 80" aria-hidden="true" class="qb-topbar-logo__glyph">
        <circle cx="38" cy="38" r="32" fill="none" stroke="currentColor" stroke-width="6"/>
        <defs>
            <clipPath id="qb-topbar-clip">
                <path d="M0 0 L36 0 L36 28 L18 38 L0 28 Z"/>
            </clipPath>
        </defs>
        <g transform="translate(20 24)" clip-path="url(#qb-topbar-clip)">
            <rect x="0"  y="0" width="9" height="40" fill="#F37335"/>
            <rect x="9"  y="0" width="9" height="40" class="fill-white dark:fill-gray-900"/>
            <rect x="18" y="0" width="9" height="40" fill="#F37335"/>
            <rect x="27" y="0" width="9" height="40" class="fill-white dark:fill-gray-900"/>
        </g>
        <path d="M58 58 L72 72" stroke="currentColor" stroke-width="6" stroke-linecap="round"/>
    </svg>
    <span class="qb-topbar-logo__wordmark">QBAZAAR</span>
</a>
