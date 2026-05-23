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

Per [PLAN.md → Execution Protocol → After every endpoint](../PLAN.md):

1. New endpoint lands in `openapi/v1.yaml`.
2. Backend agent implements + adds Scribe annotations.
3. Backend agent runs `php artisan scribe:generate` — Scribe writes `qbazaar-api/storage/app/private/scribe/{collection.json, openapi.yaml}`.
4. **A new request is added to `qbazaar.postman_collection.json`** (this file) with a stable `name` (`Domain › METHOD /path`) and a `tests` script if the response carries something we want to capture into env vars.
5. **A new env slot is added** to `qbazaar.local.postman_environment.json` only if the new endpoint needs a variable that wasn't already there.

The point is: **after pulling main, re-importing both JSON files always reflects the live API** — same env vars, same auto-capture scripts.

## Scribe's auto-generated alternative

If you ever want a brute-force "everything Scribe knows" collection (no curated test scripts), run:

```bash
cd qbazaar-api
php artisan scribe:generate
# Now import: storage/app/private/scribe/collection.json
```

That collection covers every documented endpoint but doesn't capture tokens. Use it as a fallback or for QA; our hand-curated `qbazaar.postman_collection.json` is the one that lives in this folder.
