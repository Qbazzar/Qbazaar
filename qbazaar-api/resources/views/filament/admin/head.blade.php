{{--
  Loaded via panels::head.end hook in AdminPanelProvider. Two purposes:
   1. Pull Cairo from Google Fonts so the Filament admin matches the public
      app's Arabic typography.
   2. Override Filament's default fonts + tighten the sidebar visuals.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    :root {
        --qb-font-cairo: 'Cairo', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    /* Cairo across the whole admin shell. */
    html, body,
    .fi-layout, .fi-sidebar, .fi-topbar, .fi-main, .fi-page,
    .fi-input, .fi-btn, .fi-ta-text, .fi-fo-field-wrp-label, .fi-section-header-heading,
    h1, h2, h3, h4, h5, h6, p, span, div, button, input, textarea, select, a, label {
        font-family: var(--qb-font-cairo) !important;
    }

    /* Slightly tighter sidebar — bigger group labels, condensed item padding. */
    .fi-sidebar-nav-groups {
        gap: 0.25rem;
    }
    .fi-sidebar-group-label {
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        opacity: 0.7;
        padding-inline: 0.5rem;
    }
    .fi-sidebar-item-button {
        padding-block: 0.55rem !important;
        border-radius: 0.5rem !important;
    }
    .fi-sidebar-item-active .fi-sidebar-item-button {
        background-color: rgb(243 115 53 / 0.12) !important;
        color: rgb(243 115 53) !important;
        font-weight: 600 !important;
    }
    .fi-sidebar-item-active .fi-sidebar-item-icon {
        color: rgb(243 115 53) !important;
    }

    /* Brand panel — comfortable padding around the logo + wordmark. */
    .fi-sidebar-header {
        padding-block: 1rem !important;
        border-bottom: 1px solid rgb(0 0 0 / 0.06);
    }
    .dark .fi-sidebar-header {
        border-bottom-color: rgb(255 255 255 / 0.08);
    }

    /* Topbar gains a subtle bottom border so its actions are visually anchored. */
    .fi-topbar {
        border-bottom: 1px solid rgb(0 0 0 / 0.06);
    }
    .dark .fi-topbar {
        border-bottom-color: rgb(255 255 255 / 0.08);
    }

    /* Coral primary buttons keep white text everywhere — Filament's default
       primary contrast can drift on certain widget pills. */
    .fi-btn-color-primary {
        color: #fff !important;
    }
</style>
