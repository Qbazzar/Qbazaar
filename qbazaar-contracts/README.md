# QBazaar Contracts

> **Source of truth** for the QBazaar API, error catalogue, WebSocket events, roadmap and planning docs.

[![Sprint](https://img.shields.io/badge/sprint-0-blue)](ROADMAP.md)
[![Status](https://img.shields.io/badge/status-Day%201%20done%20·%20Day%207%20pending-orange)](ROADMAP.md)
[![Schema](https://img.shields.io/badge/OpenAPI-3.1-green)](openapi/v1.yaml)

---

## 📊 Current Progress

**Sprint:** 0 — Infrastructure & Foundation

| Sprint Day | Status | Task IDs |
|------------|--------|----------|
| **Day 1** | ✅ | `CT-0.1` repo init · `CT-0.2` import PLAN/ROADMAP/MILESTONES · `CT-0.3` openapi v1 skeleton + error-codes.md + events/messages.yaml |
| **Day 7** | ⚪ | `CT-0.4` `package.json` with Prism · `CT-0.5/6` openapi auth schemas + examples · GitHub Project + Milestones + Labels + Issues for Sprint 1 |

### What's in the repo now

| File | Purpose | State |
|------|---------|-------|
| [`openapi/v1.yaml`](openapi/v1.yaml) | The API spec | Skeleton + `/health` endpoint; auth endpoints added Day 7 |
| [`error-codes.md`](error-codes.md) | Stable error catalogue | 48 codes across all sprints |
| [`events/messages.yaml`](events/messages.yaml) | WebSocket events spec | Channels + payload schemas for messaging, offers, notifications |
| [`ROADMAP.md`](ROADMAP.md) | Live status + retros | Days 1–4 closed, Day 5 in progress |
| [`MILESTONES.md`](MILESTONES.md) | Every user story + flow + task ID | ~475 tasks across 13 sprints |
| [`PLAN.md`](PLAN.md) | Architectural plan (snapshot) | Decisions, design system, execution protocol |
| [`package.json`](package.json) | Prism mock + Redocly validate scripts | Scripts present; `npm install` runs Day 7 |

---

## 🧭 How the three repos relate

```
                            ┌───────────────────┐
                            │ qbazaar-contracts │  \xe2\x86\x90 you are here
                            │                   │
                            │  openapi/v1.yaml  │  source of truth
                            │  error-codes.md   │
                            │  events/*.yaml    │
                            └─────────┬─────────┘
                                      │ defines the shape
                  ┌───────────────────┼───────────────────┐
                  \xe2\x86\x93                                       \xe2\x86\x93
       ┌───────────────────┐                   ┌───────────────────┐
       │   qbazaar-api     │  serves real      │   qbazaar-web     │
       │   Laravel 12      │  data \xe2\x86\x90\xe2\x86\x90\xe2\x86\x90\xe2\x86\x90\xe2\x86\x90\xe2\x86\x90    │   Next.js 15      │
       │  /api/v1/*        │                   │  consumes mock    │
       │                   │                   │  on :4010 then    │
       │  Pest tests prove │                   │  real api on :8000│
       │  conformance      │                   │  once endpoint    │
       └───────────────────┘                   │  lands            │
                                               └───────────────────┘
```

---

## 🚀 Quick Start

### Run the Prism mock server (frontend dev)

```bash
npm install
npm run mock      # http://localhost:4010
```

`qbazaar-web` reads `NEXT_PUBLIC_API_URL` and points at the mock by default. Once a real backend endpoint exists for a given path, swap the env var to `http://localhost:8000`.

### Validate the spec

```bash
npm run validate   # Redocly lints openapi/v1.yaml
```

---

## 🔁 Contract-First Workflow

For **every new endpoint**:

1. Edit `openapi/v1.yaml` (add path + schemas + examples).
2. Commit: `docs(contract): add <name> endpoints [CT-X.Y]`.
3. Frontend agent uses the mock — no waiting on backend.
4. Backend agent implements until Pest tests + Scribe annotations match the spec.
5. Integration: swap the frontend env URL → run E2E → fix any drift.

This is the only way our solo dev / multi-agent workflow keeps backend and frontend in sync without blocking each other. Cf. [PLAN.md → Multi-Agent Parallel Workflow](PLAN.md).

---

## 📚 Documentation Index

| Doc | What it answers |
|-----|----------------|
| [PLAN.md](PLAN.md) | What decisions are locked? What's the design system? How do agents work in parallel? |
| [ROADMAP.md](ROADMAP.md) | Where are we right now? What's blocking? What did last sprint produce? |
| [MILESTONES.md](MILESTONES.md) | What are the user stories, flows, and per-track tasks for sprint N? |
| [error-codes.md](error-codes.md) | What does AUTH_005 mean? Which HTTP status? |
| [openapi/v1.yaml](openapi/v1.yaml) | What's the exact shape of every request and response? |
| [events/messages.yaml](events/messages.yaml) | What WebSocket channels exist and what payloads do they carry? |

---

## 🔗 Related Repositories

- **[`../qbazaar-api`](../qbazaar-api)** — Laravel 12 backend implementation (Sprint 0 — Days 1–4 done, Day 5 in progress)
- **[`../qbazaar-web`](../qbazaar-web)** — Next.js 15 frontend (Sprint 0 — Day 1 done, Day 6 pending)
- **[`../DOCS/`](../DOCS/)** — Original architecture + backend plan + Bazzar mockup (reference only, frozen)
