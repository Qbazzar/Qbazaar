# QBazaar Web

> The Next.js web client for QBazaar, running against the Laravel API in `../qbazaar-api`.

**Status (2026-09-30):** every MVP page works against the live API, on the **old QBFront design**. In V2 the app keeps its routes and data layer and is reskinned to the new Figma design, using [`Qbazzar/Qbazaar-front`](https://github.com/Qbazzar/Qbazaar-front) as the pixel reference (M3, tasks `FE-16.x` in [MILESTONES-V2.md](../qbazaar-contracts/MILESTONES-V2.md)).

## Pages today

- Home, categories (`/c/...`, `/categories`), ad list and detail (`/ads`), search with facets and saved searches
- Post-an-ad wizard (`/post-ad`) and ad editing
- Account (`/account/...`): profile, security, sessions, verification, privacy, blocked users, data export, my ads, messages (live chat and offers, typing indicator), notifications, favorites, recently viewed, saved searches, support
- Public seller profiles (`/u/...`), CMS pages (`/p/...`), help center, support
- Auth: register, login, OTP, password reset, email verification
- Arabic/English switch (cookie based), SEO/PWA (sitemap, robots, manifest, Open Graph, JSON-LD), web push when the FCM variables are set

Not there yet (V2): passwordless login, orders and wallet, follows, companies, Turnstile, the new design.

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

- **Tokens** in `styles/design-tokens.css`, all prefixed `qb-`: colours (`bg-qb-brand`, `text-qb-ink`, `border-qb-line`...), type scale (`text-qb-h1` ... `text-qb-tiny`), radii (`rounded-qb-md`...), shadows (`shadow-qb-card`...), gutters (`px-qb-gutter`), content width (`max-w-qb-content`) and the reference breakpoints (`qb-tablet:` from 601 px, `qb-desktop:` from 1001 px).
- **Fonts**: `font-qb` is Poppins, or IBM Plex Sans Arabic first under `dir="rtl"`.
- **Components** in `components/design-system/`: `Button` (+ `buttonVariants` for links), `Field`, `Input`, `Select`, `Textarea`, `Badge`, `Chip`, `Card`, `AdCard`, `SectionHeader`, `Modal`, `Sheet`, `Tabs`, `Pagination`, `Breadcrumb`, `Avatar`, `Icon`, `EmptyState`. Import each from its own file. Only `Modal`/`Sheet` and `Tabs` are client components.
- `styles/qbfront.css` is the old look and is being removed in stages; don't add to it. While it is loaded, its generic class names (`grid`, `container`, `card`, `chip`, `field`...) still apply, so new markup should not use them.

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

There is no CI job for the web yet; run `typecheck`, `test` and `build` before merging.

## Deploy

Production runs `next start` under systemd on the same cPanel server as the API, behind an Apache reverse proxy (`qbazaar.fleeteye.de`). Pushing to the `production` branch runs `.github/workflows/deploy-web.yml`, which builds on the server with `deploy/scripts/deploy-web.sh`. See [deploy/README.md](../deploy/README.md).
