# Cloudflare: cache, rate-limit and WAF rules (OPS-18.18, OPS-18.9)

The zone `qbazaar.qa` is on Cloudflare with `@`, `www` and `api` proxied, SSL mode Full (strict), and `cdn.qbazaar.qa` as the R2 custom domain. This folder holds the rules that are still open, as reviewed config:

| File | What |
|---|---|
| [`rules.json`](rules.json) | Every rule, grouped by Rulesets API phase. The source of truth: change it here first, then in Cloudflare. |
| [`cache-hit-ratio.sh`](cache-hit-ratio.sh) | Edge cache hit ratio for a host over the last 24 h (GraphQL Analytics API). |
| [`../apache/cloudflare-only.conf`](../apache/cloudflare-only.conf) | The origin side: Apache answers only Cloudflare's addresses. |

The order to apply them, with rollback for each step, is in [DOCS/M5-LAUNCH-RUNBOOK.md](../../DOCS/M5-LAUNCH-RUNBOOK.md), steps 12 and 13.

## Plan limits

The rules are written to fit the **Free** plan; the Pro-only rules live apart in the `pro_only` section of `rules.json`, which the `jq` commands below never read, so the Free-plan `PUT` body stays valid.

| | Free | Pro |
|---|---|---|
| WAF custom rules | 5 | 20 |
| Rate limiting rules | 1 (period 10 s, block 10 s, counted per IP; the expression may use only the path) | 2 (period up to 1 min; host, path and query, not the method) |
| Cache rules | 10 | 25 |
| Managed rules | Cloudflare Free Managed Ruleset | Cloudflare Managed Ruleset + OWASP |

Check the current numbers on the zone's *Security → WAF* page before applying: Cloudflare changes them from time to time.

## The rules

### WAF custom rules (*Security → WAF → Custom rules*)

1. **Block probes** for paths this stack never serves: any `.php` other than Laravel's `/index.php`, `/.env*`, `/.git*`, `/vendor/`, `/wp-*`, `/xmlrpc*`, on all three hosts. Bots try these by the thousand; blocking them at the edge keeps them out of PHP-FPM and the logs.
2. **Media host is read-only:** anything but `GET`, `HEAD` and `OPTIONS` on `cdn.` is blocked.
3. **Admin panel challenge (optional, off):** a managed challenge on `/admin` from outside the listed countries. Start it in *Log* mode for a week; a challenge in the middle of an admin session can break the panel's background requests until the page reloads.

Also on this page:

- **Managed rules:** make sure the managed ruleset is deployed (the PHP2Shell follow-up). On Free it is on by default.
- **Bot Fight Mode: leave off.** It cannot be limited to the web host on Free, and its JavaScript challenges break the mobile app's API calls.

### Rate limiting rule (*Security → WAF → Rate limiting rules*)

Free allows one rule, so it guards the endpoints whose abuse costs money: `/api/v1/auth/*` (sign-in, sign-up, password reset and the SMS and e-mail codes), 40 requests per 10 seconds per IP, blocked for 10 seconds. On Free the expression may only test the path (not the host or the method); that is enough here, because only the API serves `/api/v1/auth/` and every route under it but the two e-mail link checks is a `POST`. Laravel's own limits (`throttle:auth`, `throttle:otp`, per account and per IP) stay the precise layer; this one only stops floods before they reach PHP. Mobile carriers in Qatar put many users behind one address (CGNAT), so the edge threshold is deliberately loose. On launch day set the rule's action to *Log* first and look at the *Security events* for a day before switching it to *Block*; a threshold that hits real users is worse than none.

With Pro, take the rule from the `pro_only.http_ratelimit` section of `rules.json`, add it to the `http_ratelimit` array (a `PUT` replaces the whole phase, so send both rules together) and enable it: search and image uploads, 100 requests per minute per IP.

### Cache rules (*Caching → Cache Rules*)

| Rule | Match | Setting | Why |
|---|---|---|---|
| Media | host `cdn.qbazaar.qa` | Eligible for cache; edge and browser TTL respect origin | R2 objects carry their own `Cache-Control`: conversions `public, max-age=31536000, immutable`, older uploads a week |
| Next.js assets | host `qbazaar.qa`, path starts with `/_next/static/` | Eligible; edge TTL 1 year | File names are content hashes |
| API | host `api.qbazaar.qa`, path not under `/storage/` | Bypass cache | Answers depend on the token and `Accept-Language`, and the edge ignores `Vary` |

Also turn on **Tiered Cache → Smart Tiered Cache** (free): edge locations ask a regional tier before R2, which raises the media hit ratio and cuts R2 reads. If the plan offers it, set the media rule's cache key to ignore the query string, so `?anything` cannot bypass the cache and bill R2 reads.

### Reporting the hit ratio

```bash
CF_API_TOKEN=... CF_ZONE_ID=... bash deploy/cloudflare/cache-hit-ratio.sh            # cdn.qbazaar.qa
CF_API_TOKEN=... CF_ZONE_ID=... bash deploy/cloudflare/cache-hit-ratio.sh qbazaar.qa
```

Create the token under *My Profile → API Tokens* with only *Zone → Analytics → Read* on `qbazaar.qa`, and pass it in the environment; never save it in the repo or in shell history files. The dashboard shows the same number under *Caching → Overview*.

## Applying with the API instead of the dashboard

A `PUT` on a phase entrypoint replaces **every** rule in that phase, including ones added by hand. Save the current state first, and keep the file outside the repo:

```bash
for phase in http_request_firewall_custom http_ratelimit http_request_cache_settings; do
  curl -fsS -H "Authorization: Bearer $CF_API_TOKEN" \
    "https://api.cloudflare.com/client/v4/zones/$CF_ZONE_ID/rulesets/phases/$phase/entrypoint" > "cf-$phase-before.json" || echo "$phase: no rules yet"
done

phase=http_request_cache_settings
jq --arg p "$phase" '{ rules: [.[$p][] | del(._comment)] }' deploy/cloudflare/rules.json |
  curl -fsS -X PUT -H "Authorization: Bearer $CF_API_TOKEN" -H 'Content-Type: application/json' \
    --data @- "https://api.cloudflare.com/client/v4/zones/$CF_ZONE_ID/rulesets/phases/$phase/entrypoint"
```

This token needs *Zone WAF Edit* and *Cache Rules Edit*; revoke it afterwards. Rollback: `PUT` the saved `rules` array back.
