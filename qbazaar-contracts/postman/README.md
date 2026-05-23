# QBazaar — Postman Workspace

Two files you import into Postman:

| File | Type | What it carries |
|------|------|-----------------|
| `qbazaar.local.postman_environment.json` | Environment | `base_url`, `api_prefix`, captured `access_token` / `refresh_token` / `user_id`, test credentials, OTP / reset / email-verify slots |
| `qbazaar.postman_collection.json` | Collection | One folder per domain (`Auth`, `Users`, `Ads`, …). Each request has a `tests` script that auto-captures tokens into env vars after Register / Login / Refresh. |

## How to use

1. **Boot the API** — `php artisan serve` in `qbazaar-api/` (or set `base_url` to `http://localhost:4010` to talk to the Prism mock).
2. **Import** both JSON files into Postman.
3. **Select** the `QBazaar — Local` environment from the picker.
4. Run **`Auth › POST /auth/register`** — tokens land in env vars automatically.
5. From that point on, the collection's bearer auth uses `{{access_token}}` against every authenticated endpoint.
6. When the access token expires (15 min), run **`Auth › POST /auth/refresh`** to swap it.

For OTP / password-reset / email-verify flows the env has dedicated slots (`otp_code`, `reset_token`, `verify_email_id` etc.) you fill manually from `storage/logs/laravel.log` (since Twilio + Mail are faked in dev).

## How it stays in sync

Per [PLAN.md → Workflow Rules → After every endpoint](../PLAN.md):

1. New endpoint lands in `openapi/v1.yaml` (contract-first).
2. Backend implements + Swagger UI at `/swagger` (or `/docs`) auto-reflects it (the Blade view loads the contracts spec).
3. **A new request is added to `qbazaar.postman_collection.json`** (this file) with a stable `name` (`Domain › METHOD /path`) and a `tests` script if the response carries something we want to capture into env vars.
4. **A new env slot is added** to `qbazaar.local.postman_environment.json` only if the new endpoint needs a variable that wasn't already there.

The point is: **after pulling main, re-importing both JSON files always reflects the live API** — same env vars, same auto-capture scripts.

## Single API docs surface

We removed Scribe (May 2026). The only API docs surface is **Swagger UI** loading `qbazaar-contracts/openapi/v1.yaml`, mounted on Laravel at `/swagger` and `/docs`. Anything the spec misses, nothing else covers.
