# QBazaar Web

> Next.js frontend for **QBazaar** — Qatar's friendly classifieds marketplace.

[![Sprint](https://img.shields.io/badge/sprint-0-blue)](../qbazaar-contracts/ROADMAP.md)
[![Status](https://img.shields.io/badge/status-Day%201%20done%20·%20Day%206%20pending-orange)](../qbazaar-contracts/ROADMAP.md)
[![Next.js](https://img.shields.io/badge/Next.js-15-black)](https://nextjs.org)
[![Tailwind](https://img.shields.io/badge/Tailwind-4-38BDF8)](https://tailwindcss.com)

---

## 📊 Current Progress

**Sprint:** 0 — Infrastructure & Foundation
**Days touched on this repo:**

| Sprint Day | Status | Task IDs |
|------------|--------|----------|
| **Day 1** | ✅ | `FE-0.1` repo init + README + .gitignore |
| **Day 6** | ⚪ | `FE-0.2` → `FE-0.14` — `create-next-app`, Tailwind 4 + shadcn/ui, Bazzar palette + dark mode, fonts (DM Sans + Instrument Serif + Cairo), i18n routing, axios client, brand assets |
| **Day 7** | ⚪ | `FE-0.15` `.env.local` pointing at Prism mock (`http://localhost:4010`) |

> The frontend will rev up in **Sprint 0 Day 6** once the backend has a stable shape. Until then this repo holds only the README + .gitignore so the commit history doesn't drift.

### What's next (Day 6)

```bash
cd c:\laragon\www\QB\qbazaar-web
npx create-next-app@latest . --typescript --tailwind --app --src-dir=false --import-alias="@/*"
npx shadcn@latest init
npm install @tanstack/react-query zustand axios next-intl react-hook-form zod next-themes lucide-react embla-carousel-react laravel-echo pusher-js nuqs
```

Followed by porting the Bazzar mockup tokens to `tailwind.config.ts`, fonts to `app/layout.tsx`, RTL-aware locale layout, and a placeholder home page that proves the design system loads. See [PLAN.md → Day 6](../qbazaar-contracts/PLAN.md) for the full step list.

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

## 🏗️ Planned Stack

- **Framework:** Next.js 15 (App Router, Server Components)
- **Language:** TypeScript 5 (strict)
- **Styling:** Tailwind CSS 4 + shadcn/ui
- **Data:** TanStack Query v5 + axios
- **State:** Zustand (client state)
- **Forms:** React Hook Form + Zod (matches backend validation rules)
- **i18n:** next-intl (AR/EN + RTL)
- **Theme:** next-themes (light/dark)
- **Real-time:** Laravel Echo + Pusher.js (against Reverb)
- **Icons:** Lucide React
- **Fonts:** DM Sans · Instrument Serif · Cairo (via `next/font/google`)
- **Carousel:** Embla
- **Images:** `next/image` + `sharp` + BlurHash placeholders

---

## 🚀 Local Setup (after Day 6 scaffolding)

```bash
npm install
cp .env.example .env.local
npm run dev   # http://localhost:3000
```

Development pattern:

1. **Day 6 onwards:** the app talks to the **Prism mock server** in `qbazaar-contracts` on `http://localhost:4010` via `NEXT_PUBLIC_API_URL`.
2. **Once a real backend endpoint exists** for a path: swap to `http://localhost:8000` in `.env.local` and you get the real shape.

The mock and the real server speak the same OpenAPI 3 contract, so the swap is transparent.

---

## ✅ Quality Gates (will be wired Day 6)

```bash
npm run lint        # ESLint
npm run typecheck   # tsc --noEmit
npm test            # Vitest / Playwright (later)
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

- **[`qbazaar-api`](../qbazaar-api)** — Laravel 12 backend (Sprint 0 — Days 1–4 done, Day 5 in progress)
- **[`qbazaar-contracts`](../qbazaar-contracts)** — OpenAPI spec, error catalogue, WebSocket events spec, planning docs (Sprint 0 — Day 1 done)
