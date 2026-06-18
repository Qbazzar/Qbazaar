{{--
  Loaded via panels::head.end hook in AdminPanelProvider.

  Scope is deliberately narrow: fonts + a near-black dark palette + a couple of
  brand/contrast touch-ups. We do NOT override Filament's topbar / sidebar-header
  layout geometry — past attempts to force heights/widths fought Filament's own
  layout and broke the header, so the structural CSS is left to Filament.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    :root {
        --qb-font-cairo: 'Cairo', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --qb-coral: 243 115 53;
        /* Layered near-black dark palette (no navy). */
        --qb-dark-bg: #141414;      /* deepest — page / main */
        --qb-dark-surface: #1a1a1a; /* sidebar + topbar (rgb 26,26,26) */
        --qb-dark-card: #1e1e1e;    /* sections / tables / cards */
        --qb-dark-input: #242424;   /* inputs */
        --qb-dark-border: rgb(255 255 255 / 0.06);
    }

    /* Cairo across the whole admin shell. */
    html, body,
    .fi-layout, .fi-sidebar, .fi-topbar, .fi-main, .fi-page,
    .fi-input, .fi-btn, .fi-ta-text, .fi-fo-field-wrp-label, .fi-section-header-heading,
    h1, h2, h3, h4, h5, h6, p, span, div, button, input, textarea, select, a, label {
        font-family: var(--qb-font-cairo) !important;
    }

    /* ── Dark mode: neutral near-black surfaces (replaces the navy/slate tint) ── */
    .dark body,
    .dark .fi-body,
    .dark .fi-main,
    .dark .fi-main-ctn,
    .dark .fi-layout { background-color: var(--qb-dark-bg) !important; }

    .dark .fi-sidebar,
    .dark .fi-sidebar-header,
    .dark .fi-topbar,
    .dark .fi-topbar > nav { background-color: var(--qb-dark-surface) !important; }

    .dark .fi-section,
    .dark .fi-section-content-ctn,
    .dark .fi-wi-stats-overview-stat,
    .dark .fi-wi-chart,
    .dark .fi-ta,
    .dark .fi-ta-ctn,
    .dark .fi-dropdown-list,
    .dark .fi-modal-window { background-color: var(--qb-dark-card) !important; border-color: var(--qb-dark-border) !important; }

    .dark .fi-input-wrp,
    .dark .fi-input,
    .dark .fi-select-input,
    .dark .fi-ta-search-field-input { background-color: var(--qb-dark-input) !important; }

    /* Single brand: it now lives in the topbar (injected via TOPBAR_START).
       Hide the sidebar-header brand row and any brand Filament renders
       natively in the topbar so the only logo is our .qb-topbar-logo. */
    .fi-sidebar-header { display: none !important; }
    .fi-topbar .qb-brand,
    .fi-topbar a.qb-brand { display: none !important; }

    .qb-topbar-logo {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        height: 100%;
        text-decoration: none;
        color: #1a1a1a;
        white-space: nowrap;
    }
    .dark .qb-topbar-logo { color: #fff; }
    .qb-topbar-logo__glyph { width: 1.85rem; height: 1.85rem; flex: none; }
    .qb-topbar-logo__wordmark {
        font-family: var(--qb-font-cairo);
        font-weight: 800;
        font-size: 15px;
        letter-spacing: 0.04em;
        line-height: 1;
    }

    /* Hide the wordmark when the sidebar is collapsed — only the Q glyph remains. */
    .fi-sidebar.fi-sidebar-collapsed .qb-brand__wordmark,
    .fi-sidebar-collapsed .qb-brand__wordmark { display: none !important; }

    /* ── Sidebar active item — coral accent ─────────────────────────────── */
    .fi-sidebar-item-active .fi-sidebar-item-button {
        background-color: rgb(var(--qb-coral) / 0.12) !important;
        color: rgb(var(--qb-coral)) !important;
        font-weight: 600 !important;
    }
    .fi-sidebar-item-active .fi-sidebar-item-icon {
        color: rgb(var(--qb-coral)) !important;
    }

    /* Stat / widget cards: subtle hover lift. */
    .fi-wi-stats-overview-stat,
    .fi-wi-chart,
    .fi-section {
        transition: box-shadow 150ms ease, transform 150ms ease;
    }
    .fi-wi-stats-overview-stat:hover {
        box-shadow: 0 8px 24px -8px rgb(0 0 0 / 0.08);
    }

    /* ── Primary button contrast — forces white text on Coral ───────────── */
    .fi-btn[data-color="primary"],
    .fi-btn.fi-color-primary,
    .fi-btn-color-primary,
    .fi-ac-btn[data-color="primary"],
    .fi-ac-action[data-color="primary"] {
        color: #fff !important;
    }
    .fi-btn[data-color="primary"] svg,
    .fi-btn.fi-color-primary svg,
    .fi-btn-color-primary svg,
    .fi-ac-btn[data-color="primary"] svg,
    .fi-ac-action[data-color="primary"] svg {
        color: #fff !important;
    }
</style>
