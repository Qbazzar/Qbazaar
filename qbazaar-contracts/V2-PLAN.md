# QBazaar V2: completing the system and connecting the mobile app, the web and the backend

> **Date:** 2026-09-30 · **Status:** approved as a direction; the tasks are in [`MILESTONES-V2.md`](MILESTONES-V2.md) and the gaps in [`GAP-ANALYSIS-MOBILE.md`](GAP-ANALYSIS-MOBILE.md)

## 1. Where things stand now

| Part | Place | State |
|---|---|---|
| Requirements document | `DOCS/QBazaar_Full_Page_Specs_Backend_PRD_SRS.pdf` | 31 pages: pages, user stories, endpoints. Qatar only, QAR, Arabic + English |
| Backend | `qbazaar-api` | Laravel 12 · 96 routes under `/api/v1` · Sanctum + refresh tokens · Reverb · Meilisearch · FCM · Twilio · 330 Pest tests |
| Admin panel | `qbazaar-api` → `/admin` | Custom Blade, 17 resources (ads, users, reports, categories, locations, pages, support…) |
| Web | `qbazaar-web` | Next.js 16, connected to the API, but on the **old design** |
| Contract | `qbazaar-contracts/openapi/v1.yaml` | 79 paths, matching the implementation (except reviews) |
| Mobile app | `Qbazzar/Qbazaar-mobile` (`D:\Doc\qbazaar-mobile`) | Expo SDK 57 · about 45 screens on the new design · **mock data** behind a repository layer · bottom tab bar |
| Pixel reference for the new design | `Qbazzar/Qbazaar-front` (`D:\Doc\q-bazaar`) | Static prototype of the whole Figma (3 sizes), deployed as a demo |

The system was scoped as **classifieds** (contact and meet up, no payments). The new Figma design (July) adds Buy Now, checkout, payment, a wallet and paid promotion.

## 2. Decisions (2026-09-30)

1. **The backend stays Laravel.** We complete the existing one; no rewrite in Next.js.
2. **The first launch has no payments.** Buy Now/checkout/wallet/billing/paid promotion move to M7. Their screens stay built in the app and are switched off with a feature flag.
3. **The web is reskinned on the new design.** It keeps its structure and its connection to the API, and uses `Qbazaar-front` as the pixel reference.
4. **Admin:** we build on the existing `/admin` panel. No Filament and no new design.
5. **Deployment:** native on the new cPanel VPS (`srv1977263.hstgr.cloud`), no Docker. We use the runbook in `deploy/`.
6. **Domain:** not decided yet. We work on the server name, and the domain must be settled before the M5 milestone ends.
7. **Contract first:** every new endpoint goes into OpenAPI v1.1 before it's implemented.

## 3. Architecture

```
          ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
          │ Expo mobile  │     │  Next.js web │     │ /admin Blade │
          └──────┬───────┘     └──────┬───────┘     └──────┬───────┘
                 │  HTTPS /api/v1 (Bearer, snake_case, {success,data,meta})
                 └──────────────┬──────┴────────────────────┘
                        ┌───────▼────────┐   Reverb (WebSocket)  ── private user.{id} / conversation.{id}
                        │  Laravel 12 API │── Horizon (Redis queues) ── FCM push · Twilio SMS
                        └──┬───────┬─────┘
                        MySQL   Meilisearch (search + _geo)   Media (Spatie)
```

- **Mobile ↔ API:** the app keeps its screens and services exactly as they are. We add an `ApiRepository` for each domain inside `src/repositories/api/*` that:
  - unwraps the envelope and converts snake_case → camelCase;
  - maps `error.code` → `RepositoryError`;
  - keeps tokens in `expo-secure-store` and refreshes them automatically.

  The switch is in `src/repositories/index.ts`.
- **Realtime from mobile:** needs `Broadcast::routes` under `api/v1` with `auth:sanctum` (today it's web only).
- **Push:** the backend sends to FCM directly, so the app registers a native token (`getDevicePushTokenAsync`), not an Expo token.

## 4. Open questions and their defaults
Until the client decides otherwise, we build on the default.

| # | Question | Default |
|---|---|---|
| 1 | Does an ad need approval before it's published? | **Yes**, the way the backend works now (`pending` → admin). The app shows "Under review" |
| 2 | How long does an ad live? | 30 days + a warning 3 days before (`config/qbazaar.php`) |
| 3 | Counter-offer in chat? | **Yes**, one round from each side |
| 4 | Code on every login? | **New device only** (config switch) |
| 5 | Languages at launch | **Arabic + English** (from the PRD) |
| 6 | Featured companies / recommended | Companies **chosen by the admin**. Recommended = **newest + most viewed** |
| 7 | Maximum photos per ad | **20** (the design); the current setting is 10 |
| 8 | Seller type at sign-up | `private` / `business` (the app currently sends `commercial`, so it gets aligned) |
| 9 | Domain | `qbazaar.qa`, to be confirmed |

## 5. Milestones (details in MILESTONES-V2.md)

| Milestone | Sprint | Goal | Depends on |
|---|---|---|---|
| **M0** Preparation and alignment | 13 | Finish the mobile polish, security, one category tree, OpenAPI v1.1 | — |
| **M1** Closing the backend gaps | 14 | Everything the app needs, except payments | M0 |
| **M2** Connecting the mobile app | 15 | The app on the real API + realtime + push + Arabic | M1 |
| **M3** Web on the new design | 16 | Reskin Next.js to match `Qbazaar-front` | M1 (partly) |
| **M4** Admin additions | 17 | Companies, home sections, taxonomy, verification | M1 |
| **M5** Deployment | 18 | Full system on the new VPS with a domain and SSL | M1–M4 |
| **M6** Releasing the app | 19 | TestFlight / Play, then the stores | M2, M5 |
| **M7** Payments (later phase) | 20+ | Gateway, orders, escrow, wallet, promotion, subscription | M5 + a gateway contract |

## 6. Risks
- **Payment gateway in Qatar** (QNB or others): the contract and approvals take time, which is why it's in M7.
- **Apple/Google rules:** subscriptions and promotions for digital services may require in-app purchase.
- **Unstable network in the development environment:** big uploads keep dropping. The deploy script sends in batches with retries.
- **Two category trees** in the app today (browse / sell): must be unified in M0 before connecting.
- **Leaked secrets:** a Firebase key lies loose in `Downloads`, and a Figma token is in local settings. Both must be rotated in M0.
