# QBazaar Web

> The Next.js web client for QBazaar, running against the Laravel API in `../qbazaar-api`.

**Status (2026-09-30):** every MVP page works against the live API, on the **old QBFront design**. In V2 the app keeps its routes and data layer and is reskinned to the new Figma design, using [`Qbazzar/Qbazaar-front`](https://github.com/Qbazzar/Qbazaar-front) as the pixel reference (M3, tasks `FE-16.x` in [MILESTONES-V2.md](../qbazaar-contracts/MILESTONES-V2.md)).

## Pages today

- Home, categories (`/c/...`, `/categories`), ad list and detail (`/ads`), search with facets and saved searches
- Post-an-ad wizard (`/post-ad`) and ad editing
- Account (`/account/...`): profile, security, sessions, verification, privacy, blocked users, data export, my ads, messages (live chat and offers, typing indicator), notifications, favorites, recently viewed, saved searches, support
- Cash order cycle: Buy Now and Make an Offer pages (`/ads/{id}/buy`, `/ads/{id}/offer`), purchase-request and offer cards in the chat, checkout (`/checkout/{orderId}`), orders (`/account/orders`), wallet, commission settlements, withdrawals and payout accounts (`/account/wallet/...`), paid promotions (`/account/promotions`)
- Public seller profiles (`/u/...`), CMS pages (`/p/...`), help center, support
- Auth: register, login, OTP, password reset, email verification
- Arabic/English switch (cookie based), SEO/PWA (sitemap, robots, manifest, Open Graph, JSON-LD), web push when the FCM variables are set

Not there yet (V2): passwordless login, online payments, follows, companies, Turnstile, the new design.

## Stack

- Next.js 16 (App Router) · React 19 · TypeScript 5 (strict)
- Tailwind CSS 4 + shadcn/ui; current look from `app/globals.css` + `styles/qbfront.css` (coral `#F37335`, DM Sans / Instrument Serif / Cairo), light theme only
- TanStack Query 5 + axios · Zustand · React Hook Form + Zod · nuqs
- i18n: a synchronous `t()` over `i18n/{ar,en}.json`, locale from a cookie (`next-intl` is installed but its routing isn't used)
- Realtime: Laravel Echo + pusher-js against Reverb, authorised at `POST /api/v1/broadcasting/auth` with the Bearer token
- Web push: Firebase JS SDK, loaded only when the `NEXT_PUBLIC_FCM_*` variables are set
- Tests: Vitest + Testing Library (happy-dom)

## Design system (M3)

The new Figma design lives next to the old look until every page is reskinned. [DESIGN-MAP.md](DESIGN-MAP.md) maps each route to its reference page and Figma frames.

- **Tokens** in `styles/design-tokens.css`, all prefixed `qb-`: colours (`bg-qb-brand`, `text-qb-ink`, `border-qb-line`...), type scale (`text-qb-h1` ... `text-qb-tiny`), radii (`rounded-qb-md`...), shadows (`shadow-qb-card`...), the page gutter (`px-qb-gutter`: 40 / 30 / 16 px at 1440 / 744 / 390, fluid between), content width (`max-w-qb-content`) and the reference breakpoints (`qb-tablet:` from 601 px, `qb-desktop:` from 1001 px). Text and button colours are the Figma hues darkened just enough for WCAG AA (4.5:1), and text on brand fills is `text-qb-on-brand`. Greys that only draw controls meet 3:1 instead and are never text: `qb-ink-disabled` (unchecked rings, inactive toggles), `qb-icon-muted` (chevrons, field icons) and `border-qb-field-border` (inputs, selects, textareas). `lib/design-tokens.test.ts` checks every pair. Pages use the tokens, never raw colours (`text-white` included).
- **Fonts** follow Qbazaar-front exactly and are self-hosted with `next/font/local` (`app/fonts/`), so builds download nothing: `font-qb` is Poppins 400/500/600 (preloaded) and 700 (totals and amounts, loaded on use), `font-qb-label` Montserrat 400-600 (status chips and notes), `font-qb-script` Story Script 400 (the big "Location"), `font-qb-script-compact` Dancing Script 700 (the same word on phones), `font-qb-brand` Acme 400 (the "Q BAZAAR" wordmark). Every stack falls back to IBM Plex Sans Arabic (400 preloaded, 500/600/700 on use) for Arabic glyphs, so Latin text and digits keep the reference face in both directions. Use the `font-qb*` utilities with the usual weight utilities (`font-normal`, `font-medium`, `font-semibold`, `font-bold`); the stacks are the `--font-qb*` variables. `globals.css` still sets every `h1`-`h6` in the old face, so headings take `font-qb` themselves (the design-system headings do); inside a `font-qb` container they also inherit it.
- **Numbers and dates** use Latin digits (0-9) in both languages, as the reference and the Arabic copy write them: format numbers with `formatNumber` and give every other `Intl` formatter `intlLocale(locale)`, both from `lib/i18n/format` (`ar-QA-u-nu-latn` in Arabic). Never hard-code `ar-EG`, which writes Arabic-Indic digits.
- **Components** in `components/design-system/`: `Button` (+ `buttonVariants` for links), `Field`, `Input`, `Select`, `Textarea`, `Badge`, `Chip`, `Card`, `AdCard`, `CompanyCard`, `SectionHeader`, `Modal`, `Sheet` (bottom sheet or start-edge drawer), `Tabs`, `Pagination`, `Breadcrumb`, `Avatar`, `Icon`, `EmptyState`, `Switch`, `LinkTile`, `PageShell`, `StatePanel`, `Notice`, `RadioCard`, `StatTile`. Import each from its own file. Only `Modal`/`Sheet`, `Tabs` and `Switch` are client components. Every interactive element takes `focusRing` (`focus-ring.ts`): in Tailwind 4 `outline-hidden`/`outline-none` also zero the outline style, so a hand-written ring must name `focus-visible:outline-solid` too. `site-frame.ts` is the 1360 px column inside the site gutter (header, footer, home).
- **Shared pieces built on them**: `components/ads/AdSummaryCard` shows an API `AdSummary` as an `AdCard` (with its spec chips); `lib/ads/format` (`formatAdPrice`, `formatAdAge`, `placeLabel`) and `lib/i18n/format` (`formatNumber`) format card values; `components/account/useSignOut` signs out like the account menu. The home page reads its category tiles, ad rails and companies from one cached `GET /api/v1/home` query (`useHomeFeedQuery`); the places collage and the search suggestions share `GET /api/v1/locations/qatar`, and the recently viewed rail reads its own endpoint. The site header and footer stay off the routes in `components/layout/own-chrome.ts` (the auth shell, which draws its own bar).
- `styles/qbfront.css` is the old look and is being removed in stages; don't add to it. While it is loaded, its generic class names (`container`, `card`, `chip`, `field`...) still apply, so new markup should not use them.

## Local setup

```bash
npm install
cp .env.example .env.local
npm run dev   # http://localhost:3000
```

Environment variables (`.env.local`):

| Variable | Use |
|----------|-----|
| `NEXT_PUBLIC_API_URL` | API origin: `http://localhost:8000` for the real backend, `http://localhost:4010` for the Prism mock (`cd ../qbazaar-contracts && npm run mock`) |
| `QBAZAAR_API_URL` | Optional server-side API origin for the refresh-cookie route handlers; falls back to `NEXT_PUBLIC_API_URL` |
| `NEXT_PUBLIC_APP_URL` | Public site origin for canonical, Open Graph and sitemap URLs |
| `NEXT_PUBLIC_REVERB_APP_KEY`, `_HOST`, `_PORT`, `_SCHEME` | Reverb WebSocket for chat and live notifications |
| `NEXT_PUBLIC_FCM_API_KEY`, `_PROJECT_ID`, `_SENDER_ID`, `_APP_ID`, `_VAPID_KEY` | Web push; leave empty to turn it off |

`NEXT_PUBLIC_*` values are baked in at build time, so changing them needs a rebuild. The production values live in `../deploy/web.env.production.template`.

## Scripts

```bash
npm run dev         # dev server
npm run build       # production build
npm run start       # serve the build
npm run typecheck   # tsc --noEmit
npm test            # Vitest
```

CI (`.github/workflows/web-ci.yml`) runs `typecheck`, `test` and `build` on pull requests and on pushes to `main` and `develop` that touch this folder.

## Deploy

Production runs `next start` under systemd on the same cPanel server as the API, behind an Apache reverse proxy and Cloudflare (`qbazaar.qa`). Pushing to the `production` branch runs `.github/workflows/deploy-web.yml`, which builds on the server with `deploy/scripts/deploy-web.sh`. See [deploy/README.md](../deploy/README.md).
