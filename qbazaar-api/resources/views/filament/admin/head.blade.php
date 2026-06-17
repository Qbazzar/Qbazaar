{{--
  Loaded via panels::head.end hook in AdminPanelProvider. Three purposes:
   1. Pull Cairo from Google Fonts so the Filament admin matches the public
      app's Arabic typography.
   2. Override Filament's default fonts.
   3. Polish: brand sizing, sidebar density, topbar gutters, content cards,
      and primary-button contrast (Filament's default coral-on-coral pill
      can render with low-contrast text in Filament v4).
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    :root {
        --qb-font-cairo: 'Cairo', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --qb-coral: 243 115 53;
        --qb-page-gutter: 1.75rem;
        /* Layered near-black dark palette (no navy). */
        --qb-dark-bg: #141414;      /* deepest — page / main */
        --qb-dark-surface: #1a1a1a; /* sidebar + topbar (rgb 26,26,26) */
        --qb-dark-card: #1e1e1e;    /* sections / tables / cards */
        --qb-dark-input: #242424;   /* inputs */
        --qb-dark-border: rgb(255 255 255 / 0.06);
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

    /* Cairo across the whole admin shell. */
    html, body,
    .fi-layout, .fi-sidebar, .fi-topbar, .fi-main, .fi-page,
    .fi-input, .fi-btn, .fi-ta-text, .fi-fo-field-wrp-label, .fi-section-header-heading,
    h1, h2, h3, h4, h5, h6, p, span, div, button, input, textarea, select, a, label {
        font-family: var(--qb-font-cairo) !important;
    }

    /* ── Brand / sidebar header ─────────────────────────────────────────── */
    .fi-sidebar-header {
        padding-block: 0 !important;
        padding-inline: 1rem !important;
        border-bottom: 1px solid rgb(0 0 0 / 0.06);
        height: 4rem !important;
        min-height: 4rem !important;
        display: flex !important;
        align-items: center !important;
    }
    .dark .fi-sidebar-header {
        border-bottom-color: rgb(255 255 255 / 0.08);
    }

    .qb-brand {
        max-width: 100%;
    }
    .qb-brand__wordmark {
        min-width: 0;
    }
    .qb-brand__wordmark > span {
        display: block;
        max-width: 12rem;
    }

    /* Hide the wordmark when the sidebar is collapsed — only the Q glyph
       remains, so the brand doesn't overflow the 4.5rem collapsed width. */
    .fi-sidebar.fi-sidebar-collapsed .qb-brand__wordmark,
    .fi-sidebar-collapsed .qb-brand__wordmark {
        display: none !important;
    }
    .fi-sidebar.fi-sidebar-collapsed .qb-brand,
    .fi-sidebar-collapsed .qb-brand {
        justify-content: center;
    }

    /* ── Sidebar navigation density ─────────────────────────────────────── */
    .fi-sidebar-nav-groups {
        gap: 0.25rem;
    }
    .fi-sidebar-group-label {
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.06em !important;
        text-transform: uppercase !important;
        opacity: 0.6;
        padding-inline: 0.75rem !important;
        padding-top: 1rem !important;
        padding-bottom: 0.35rem !important;
    }
    .fi-sidebar-item-button {
        padding-block: 0.55rem !important;
        border-radius: 0.5rem !important;
    }
    .fi-sidebar-item-active .fi-sidebar-item-button {
        background-color: rgb(var(--qb-coral) / 0.12) !important;
        color: rgb(var(--qb-coral)) !important;
        font-weight: 600 !important;
    }
    .fi-sidebar-item-active .fi-sidebar-item-icon {
        color: rgb(var(--qb-coral)) !important;
    }

    /* ── Topbar — gutter + visual anchor ────────────────────────────────── */
    .fi-topbar {
        border-bottom: 1px solid rgb(0 0 0 / 0.06);
    }
    .dark .fi-topbar {
        border-bottom-color: rgb(255 255 255 / 0.08);
    }
    .fi-topbar > nav,
    .fi-topbar-ctn {
        padding-inline: var(--qb-page-gutter) !important;
        min-height: 4rem;
    }

    /* Brand: compact, vertically centred, fits the 4rem header cleanly. */
    .qb-brand { align-items: center !important; gap: 0.5rem !important; height: 100%; }
    .qb-brand__glyph { width: 1.75rem !important; height: 1.75rem !important; flex: none; }
    .qb-brand__wordmark { gap: 0 !important; justify-content: center; }
    .qb-brand__wordmark > span:first-child { font-size: 13px !important; line-height: 1.15 !important; }
    .qb-brand__wordmark > span:last-child { font-size: 9px !important; line-height: 1.15 !important; }

    /* ── Page shell — breathing room around the title + actions ──────────── */
    .fi-page {
        padding-inline: var(--qb-page-gutter) !important;
    }
    .fi-header {
        padding-block: 1.5rem 0.25rem !important;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .fi-header-heading {
        font-weight: 700 !important;
        font-size: 1.5rem !important;
        line-height: 1.25 !important;
    }
    .fi-header-actions {
        margin-inline-start: auto;
    }

    /* ── Card container for resource list/edit/view pages ───────────────── */
    /* Wrap the main content (table / form / view) in a soft card. Widgets
       on the dashboard already render as individual cards, so scope this
       to non-dashboard pages by targeting the resource-page wrapper. */
    .fi-resource-list-records-page .fi-main-ctn > .fi-page > .fi-ta,
    .fi-resource-edit-record-page .fi-main-ctn > .fi-page > form,
    .fi-resource-create-record-page .fi-main-ctn > .fi-page > form,
    .fi-resource-view-record-page .fi-main-ctn > .fi-page > .fi-in {
        background-color: white;
        border: 1px solid rgb(0 0 0 / 0.06);
        border-radius: 0.875rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        padding: 1.25rem 1.5rem;
    }
    .dark .fi-resource-list-records-page .fi-main-ctn > .fi-page > .fi-ta,
    .dark .fi-resource-edit-record-page .fi-main-ctn > .fi-page > form,
    .dark .fi-resource-create-record-page .fi-main-ctn > .fi-page > form,
    .dark .fi-resource-view-record-page .fi-main-ctn > .fi-page > .fi-in {
        background-color: var(--qb-dark-card);
        border-color: var(--qb-dark-border);
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.3);
    }

    /* Stat / widget cards: lift on hover so the dashboard reads as
       interactive. */
    .fi-wi-stats-overview-stat,
    .fi-wi-chart,
    .fi-section {
        transition: box-shadow 150ms ease, transform 150ms ease;
    }
    .fi-wi-stats-overview-stat:hover {
        box-shadow: 0 8px 24px -8px rgb(0 0 0 / 0.08);
    }

    /* ── Primary button contrast — forces white text on Coral ───────────── */
    /* Filament v4 button class is `.fi-btn` with `data-color="primary"`,
       `.fi-color-primary`, and historically `.fi-btn-color-primary`. We
       cover all three so the text reads on both filled + outline variants. */
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
