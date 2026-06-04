# QBazaar Web

> Next.js frontend for **QBazaar** — Qatar's friendly classifieds marketplace.

[![Status](https://img.shields.io/badge/status-MVP%20feature--complete-success)](../qbazaar-contracts/ROADMAP.md)
[![Phase](https://img.shields.io/badge/phase-launch%20prep-yellow)](../qbazaar-contracts/ROADMAP.md)
[![Next.js](https://img.shields.io/badge/Next.js-16-black)](https://nextjs.org)
[![Tailwind](https://img.shields.io/badge/Tailwind-4-38BDF8)](https://tailwindcss.com)

---

## 📊 Status

**MVP feature-complete — now in launch prep.** The web client is fully built out against the live API. Surfaces shipped:

- **Home** — featured ads, city tags, category browse
- **Ads** — list + filtering, ad detail, **post-ad wizard**
- **Categories** & **search** (facets + saved searches)
- **Account dashboard** — profile, sessions, privacy, data export, messages, notifications, favorites, saved searches, support
- **Public profile** pages
- **CMS / help / support** content surfaces
- **Auth** — register, login, OTP, password reset, email verification

Plus **AR/EN cookie-based i18n** and a **Milestone-6 SEO/PWA pass** (sitemap, robots, web manifest, Open Graph, JSON-LD).

See [ROADMAP.md](../qbazaar-contracts/ROADMAP.md) and [MILESTONES.md](../qbazaar-contracts/MILESTONES.md) for the full per-sprint detail.

---

## 🎨 Design System

Visual identity translated from the React mockup in `../DOCS/bazzar/`:

| Token | Value |
|-------|-------|
| **Primary** | Coral `#EE8765` |
| **Hover/Accent** | Terracotta `#B85A45` |
| **Surfaces** | Cream `#FAF6F1 · #FFFFFF · #F1EBE2` |
| **Text** | Ink `#2A2622 → #8A847C` |
| **Success/Eco** | Sage `#6B8E6B` |
| **Headings** | Instrument Serif (italic) |
| **UI** | DM Sans |
| **Arabic** | Cairo |
| **Vibe** | Warm, friendly, Mediterranean, premium-but-approachable |

Dark mode palette is derived (warmer dark cream + reversed ink) and wired via `next-themes`. Full token spec in [PLAN.md → Design System](../qbazaar-contracts/PLAN.md).

---

## 🏗️ Stack

- **Framework:** Next.js 16 (App Router, Server Components)
- **Language:** TypeScript 5 (strict)
- **Styling:** Tailwind CSS 4 + shadcn/ui
- **Data:** TanStack Query v5 + axios
- **State:** Zustand (client state)
- **Forms:** React Hook Form + Zod (matches backend validation rules)
- **i18n:** cookie-based AR/EN switch on a synchronous `t()` shim (next-intl is a dependency; its full routing is not used)
- **Theme:** next-themes (light/dark)
- **Real-time:** Laravel Echo + Pusher.js (against Reverb)
- **Icons:** Lucide React
- **Fonts:** DM Sans · Instrument Serif · Cairo (via `next/font/google`)
- **Carousel:** Embla
- **Images:** `next/image` + `sharp` + BlurHash placeholders

---

## 🚀 Local Setup

```bash
npm install
cp .env.example .env.local
npm run dev   # http://localhost:3000
```

Point `NEXT_PUBLIC_API_URL` at the running API (`http://localhost:8000` for the real backend, or the **Prism mock server** in `qbazaar-contracts` on `http://localhost:4010`). The mock and the real server speak the same OpenAPI 3 contract, so the swap is transparent.

---

## ✅ Quality Gates

```bash
npm run dev         # next dev (local dev server)
npm run build       # next build (production build)
npm run start       # next start (serve the production build)
npm run typecheck   # tsc --noEmit
```

---

## 📚 Documentation

The full project plan, roadmap, and per-sprint task breakdown lives in the **`qbazaar-contracts`** repository:

- [PLAN.md](../qbazaar-contracts/PLAN.md) — architectural decisions, **design system tokens**, execution protocol
- [ROADMAP.md](../qbazaar-contracts/ROADMAP.md) — live status, sprint retros
- [MILESTONES.md](../qbazaar-contracts/MILESTONES.md) — every user story, flow and `FE-X.Y` task ID

The bazaar React mockup is in [`../DOCS/bazzar/`](../DOCS/bazzar/) — read the relevant `*.jsx` page **before** translating it to a Next.js route.

---

## 🔗 Related Repositories

- **[`qbazaar-api`](../qbazaar-api)** — Laravel 12 backend
- **[`qbazaar-contracts`](../qbazaar-contracts)** — OpenAPI spec, error catalogue, WebSocket events spec, planning docs
