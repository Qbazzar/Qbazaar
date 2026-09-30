# QBazaar V2: completing the system and connecting the mobile app, the web and the backend

> **Date:** 2026-09-30 · **Status:** approved as a direction; the tasks are in [`MILESTONES-V2.md`](MILESTONES-V2.md), progress in [`ROADMAP.md`](ROADMAP.md) and the gaps in [`GAP-ANALYSIS-MOBILE.md`](GAP-ANALYSIS-MOBILE.md). M0's audit fixes are merged (#147–#153).

## 1. Where things stand now

| Part | Place | State |
|---|---|---|
| Requirements document | `DOCS/QBazaar_Full_Page_Specs_Backend_PRD_SRS.pdf` | 31 pages: pages, user stories, endpoints. Qatar only, QAR, Arabic + English |
| Backend | `qbazaar-api` | Laravel 12 · about 100 routes under `/api/v1` · Sanctum + refresh tokens · Reverb · Meilisearch · FCM · Twilio · 400+ Pest tests |
| Admin panel | `qbazaar-api` → `/admin` | Custom Blade, 17 resources (ads, users, reports, categories, locations, pages, support…) |
| Web | `qbazaar-web` | Next.js 16, connected to the API, but on the **old design** |
| Contract | `qbazaar-contracts/openapi/v1.yaml` | 79 paths, matching the implementation (except reviews) |
| Mobile app | `Qbazzar/Qbazaar-mobile` (`D:\Doc\qbazaar-mobile`) | Expo SDK 57 · about 45 screens on the new design · **mock data** behind a repository layer · bottom tab bar |
| Pixel reference for the new design | `Qbazzar/Qbazaar-front` (`D:\Doc\q-bazaar`) | Static prototype of the whole Figma (3 sizes), deployed as a demo |

The system was scoped as **classifieds** (contact and meet up, no payments). The new Figma design (July) adds Buy Now, checkout, payment, a wallet and paid promotion.

## 2. Decisions (2026-09-30)

1. **The backend stays Laravel.** We complete the existing one; no rewrite in Next.js.
2. **Orders and payments from the first launch, without an online payment gateway (updated 2026-09-30):**
   - Payment is **cash on delivery / at handover**.
   - The **QBazaar commission** is a percentage the admin sets in the admin settings.
   - A **wallet** for the seller: earnings, commission owed, settlements.
   - Withdrawals and settlements are **approved by the admin**.
   - Everything goes through a `PaymentGateway` interface (only `CashGateway` today), so an electronic gateway can be added later without changing the system. **Stripe is excluded.**
3. **The web is reskinned on the new design.** It keeps its structure and its connection to the API, and uses `Qbazaar-front` as the pixel reference.
4. **Admin:** we build on the existing `/admin` panel. No Filament and no new design.
5. **Deployment:** native on the new cPanel VPS (`srv1977263.hstgr.cloud`), no Docker. We use the runbook in `deploy/`.
6. **Domain:** not decided yet. We work on the server name, and the domain must be settled before the M5 milestone ends.
7. **Contract first:** every new endpoint goes into OpenAPI v1.1 before it's implemented.
8. **Business limits are admin settings (owner decision 2026-09-30):** the commission debt ceiling, the settlement deadline and the number of days before expiry that an ad owner is warned are edited by the admin in the `/admin` settings (AD-17.9), not hard-coded. The config values are only the defaults; the expiry warning defaults to **3 days**.

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
- **Realtime from mobile:** channel auth is at `POST /api/v1/broadcasting/auth` with the Bearer token (done in M0, BE-13.2 / #149).
- **Push:** the backend sends to FCM directly, so the app registers a native token (`getDevicePushTokenAsync`), not an Expo token.

## 4. Product decisions (settled 2026-09-30)

| # | Question | Decision |
|---|---|---|
| 1 | Does an ad need approval before it's published? | ✅ **Yes**: it stays "Under review" until the admin publishes it, and **a notification goes to the admins** for every ad waiting for review |
| 2 | How long does an ad live? | 30 days + a warning 3 days before; the warning days are an admin setting (default 3, AD-17.9) |
| 3 | Counter-offer in chat? | ✅ **Yes**, one round from each side |
| 4 | How does login work? | ✅ **Passwordless**: a code to the **email** on every login, plus an **SMS code to the phone** from a new device |
| 5 | Languages at launch | **Arabic + English** (from the PRD) |
| 6 | Featured companies | ✅ **Chosen by the admin**. Recommended = newest + most viewed |
| 7 | Maximum photos per ad | ✅ **20** |
| 8 | Seller type at sign-up | `private` / `business` |
| 9 | Payments | ✅ Orders + cash + admin-set commission + wallet + admin-approved withdrawals/settlements. **No electronic gateway now** (a ready interface for later) |
| 10 | Domain | ⏳ **Not decided yet** |
| 11 | Commission debt ceiling and settlement deadline | ✅ **Admin settings** (AD-17.9, used by BE-14.33 and BE-14.39); the admin sets and changes the values |

## 4.1 Notifications: Reverb + push together
- **Reverb** (WebSocket) delivers events **while the app or site is open**: a new message, an offer update, the notification counter.
- **When the app is in the background or closed**, iOS/Android cut the connection, so **push notifications via Firebase (FCM)** are required: FCM directly on Android, and via an APNs key uploaded to Firebase on iOS.
- The same event in the backend goes out to both channels (broadcast + FCM), and push is only sent when it's needed (a user who isn't in the conversation right now).

## 4.2 Cloudflare (decision 2026-09-30)
| Use | What it gives us | When | Tasks |
|---|---|---|---|
| **R2** to store ad images (up to 20 per ad), avatars and chat images | No viewing/egress fees, doesn't fill the server's disk (99GB), fast delivery | M1 (backend completion) | BE-14.42, OPS-18.10 |
| **Turnstile** on registration and code requests | Protection from bots and from the cost of SMS spam, especially now that login is passwordless | M1 + connecting the app and the web | BE-14.43, MB-15.14, FE-16.9 |
| **DNS + proxy + WAF + SSL + caching** | Protection from attacks, hiding the server's address, speed | M5 (deployment), after the domain is decided | OPS-18.9 |

> Note: the Cloudflare registrar doesn't sell `.qa` domains. The domain is bought from a Qatari registrar and its DNS is moved to Cloudflare.

## 5. Milestones (details in MILESTONES-V2.md)

| Milestone | Sprint | Goal | Depends on |
|---|---|---|---|
| **M0** Preparation and alignment | 13 | Finish the mobile polish, security, one category tree, OpenAPI v1.1 | — |
| **M1** Closing the backend gaps | 14 | Everything the app needs | M0 |
| **M1b** Orders and payments (no gateway) | 14 | Orders, cash, commission, wallet, settlements, promotion | M0 |
| **M2** Connecting the mobile app | 15 | The app on the real API + realtime + push + Arabic | M1 |
| **M3** Web on the new design | 16 | Reskin Next.js to match `Qbazaar-front` | M1 (partly) |
| **M4** Admin additions | 17 | Companies, home sections, taxonomy, verification | M1 |
| **M5** Deployment | 18 | Full system on the new VPS with a domain and SSL | M1–M4 |
| **M6** Releasing the app | 19 | TestFlight / Play, then the stores | M2, M5 |
| **M7** Electronic payment (later phase) | 20+ | Connect a gateway, Safe Pay (escrow), premium subscription, reviews | M5 + a gateway contract |

## 6. Risks
- **Payment gateway in Qatar** (QNB or others): the contract and approvals take time, so the first launch is cash only with a ready gateway interface. (Stripe doesn't support Qatar as a company country, so it's excluded.)
- **Collecting the commission on cash orders:** the seller collects the money directly, so the commission becomes a debt in their wallet. We need a settlement policy (a debt ceiling that stops new sales, and a settlement deadline).
- **Apple/Google rules:** subscriptions and promotions for digital services may require in-app purchase.
- **Unstable network in the development environment:** big uploads keep dropping. The deploy script sends in batches with retries.
- **Two category trees** in the app today (browse / sell): must be unified in M0 before connecting (MB-13.2).
- **Leaked secrets:** a Firebase key lies loose in `Downloads`, and a Figma token is in local settings. Both must be rotated in M0 (OPS-13.1, still open).
