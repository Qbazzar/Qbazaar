# QBazaar Contracts

The API contract and the planning docs for QBazaar: the OpenAPI spec, the error catalogue, the WebSocket events, and the V2 plan, roadmap and task list.

## What's here

| File | What it answers |
|------|-----------------|
| [`V2-PLAN.md`](V2-PLAN.md) | What are we building in V2, and which decisions are settled? |
| [`ROADMAP.md`](ROADMAP.md) | What is shipped today, which phase are we in, what is open? |
| [`MILESTONES-V2.md`](MILESTONES-V2.md) | Every V2 task by phase (each ID is a GitHub issue) |
| [`GAP-ANALYSIS-MOBILE.md`](GAP-ANALYSIS-MOBILE.md) | What the mobile app needs that the API doesn't have yet |
| [`openapi/v1.yaml`](openapi/v1.yaml) | The exact shape of every request and response (OpenAPI 3.1) |
| [`error-codes.md`](error-codes.md) | What does `AUTH_005` mean, and which HTTP status goes with it? |
| [`events/messages.yaml`](events/messages.yaml), [`events/messaging.md`](events/messaging.md), [`events/notifications.md`](events/notifications.md) | WebSocket channels, events and payloads (Reverb) |
| [`postman/`](postman/README.md) | Postman collection + local environment |
| [`MILESTONES.md`](MILESTONES.md), [`PLAN.md`](PLAN.md), [`plans/`](plans/), [`QA-REPORT-2026-06.md`](QA-REPORT-2026-06.md) | MVP-era history (archived; each file says what replaced it) |

## Status of the spec

`openapi/v1.yaml` describes the MVP API (79 paths) and matches the implementation, except that the existing `reviews` endpoints are missing (CT-13.2). None of the new V2 endpoints are in it yet; adding them, marked `x-status: planned`, is CT-13.1 and comes before any M1 endpoint is built.

The API serves this file at `GET /api/v1/openapi.yaml`, and Swagger UI renders it at `/docs` (and `/swagger`) on the Laravel app.

## Commands

```bash
npm install
npm run validate   # Redocly lint of openapi/v1.yaml
npm run mock       # Prism mock server on http://localhost:4010
npm run proxy      # Prism proxy to http://localhost:8000 that reports contract drift
```

There is no CI job for the spec today; run `npm run validate` before merging a spec change.

## Contract-first workflow

For every new or changed endpoint:

1. Edit `openapi/v1.yaml` (path, schemas, examples) and add any new error code to `error-codes.md` and `qbazaar-api/app/Exceptions/ErrorCode.php`.
2. Commit it with the task ID, e.g. `docs(contract): add follows endpoints [CT-13.1]`.
3. Clients can build against the Prism mock while the backend implements it.
4. The backend is done when its Pest tests pass and the response matches the spec (`npm run proxy` helps catch drift).

## How this fits the monorepo

```
qbazaar-contracts  ── defines the API ──►  qbazaar-api (Laravel, /api/v1, /admin)
                                             ▲
               qbazaar-web (Next.js) ────────┤  HTTPS + Reverb WebSocket
               Qbazaar-mobile (Expo, separate repo) ┘
```

See the [root README](../README.md) for setup.
