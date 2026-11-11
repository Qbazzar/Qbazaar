# Load tests (OPS-18.17)

[k6](https://k6.io) scripts that find the capacity of the stack before launch: the p95 latency of each critical path and the load at which it breaks. **Staging only.** The scripts refuse the production hosts unless `ALLOW_PRODUCTION=1` is set on purpose.

| Script | Path under test | Default load |
|---|---|---|
| `browse.js` | `GET /home`, a feed page (`GET /ads`), an ad (`GET /ads/{id}`) | ramps 10 → 150 visitor steps/s |
| `search.js` | `GET /search/suggestions` + `GET /search` (Meilisearch), Arabic and English, typos included | ramps 10 → 150 searches/s |
| `chat.js` | 2000 open Reverb sockets + 100 messages/s, with end-to-end delivery time | `SOCKETS`, `MSG_RATE` |
| `upload.js` | a batch of 5 photos to an ad, then deleting them | ramps 1 → 10 batches/s |

Each prints k6's summary; the thresholds (p95 targets) in each file mark a stage red when it is crossed. `PROFILE=smoke` runs any script once with one user, to check the setup before a real run.

## 1. Staging

A copy of the production box, never production itself:

1. Snapshot the production VPS in the Hostinger panel and create a new VPS of the **same plan** from it (same CPU, RAM and disk, or the numbers mean nothing). Give it its own hostnames on a domain that is not `qbazaar.qa` (or `staging.` names), its own AutoSSL certificates, and `APP_ENV=staging`.
2. On the copy, before anything else: remove the production deploy key from `~qbazaar/.ssh/authorized_keys` (GitHub Actions must never deploy there), empty every integration secret (SMS, mail, push: the test sends thousands of messages), point `MEDIA_DISK`/R2 at a staging bucket or the local disk, and turn off the backup cron.
3. Route the test the way users come: through Cloudflare for one run (a staging hostname, proxied) and straight to the box for the others (k6 `--hosts` or a hosts-file entry), so Cloudflare's share and the origin's share can be told apart. Tell Cloudflare nothing; the rate-limiting rule only covers `/api/v1/auth/*`, which the scripts do not call.

## 2. Data: 100k ads and 1M media rows

The point is index and table sizes like a busy launch year, so query plans, Meilisearch and the caches behave as they will.

1. Real-looking base: `php artisan qbazaar:demo --fresh --force --users=2000 --ads=20000 --sync-media` (the demo command's upper limits). This gives realistic categories, places, prices, chats and photos.
2. Scale to 100k ads × ~10 media each: a one-off `qbazaar:load-fixtures` command (follow-up, not in this PR) that clones the demo ads in chunks of 1000 inside transactions with new ULIDs, slugs and timestamps spread over a year, and clones their `media` rows pointing at the same files (no new uploads). Afterwards: `ANALYZE TABLE ads, media, messages, users;`, `php artisan scout:import "App\Models\Ad"`, and a wait for the catalog warmer.
3. Check: `SELECT COUNT(*) FROM ads` ≈ 100 000, `SELECT COUNT(*) FROM media` ≈ 1 000 000, and Meilisearch `numberOfDocuments` matches the live ads.

## 3. Test users

The API limits each user (120 requests/min, 60 searches/min, 30 messages/min, 20 uploads/min), so the load must come from many users, as in real life. Create them on staging only and write their tokens to a file that never leaves the test machine:

```bash
qbazaar$ php artisan tinker --execute='
  $rows = App\Models\User::query()->limit(2100)->get()->reject(fn ($u) => $u->isStaff())->take(2000)->map(fn ($u) => [
      "token" => $u->createToken("k6")->plainTextToken,
      "user_id" => $u->id,
      "conversation_id" => DB::table("conversation_participants")->where("user_id", $u->id)->value("conversation_id"),
      "ad_id" => $u->ads()->where("status", "draft")->value("id"),
  ])->values();
  file_put_contents(storage_path("app/k6-users.json"), $rows->toJson());'
```

Adjust the column and relation names to the models (this is a sketch, not a tested command). Users need a conversation for `chat.js` and a draft ad of their own for `upload.js`; the demo data has both for many users. Copy `k6-users.json` to the test machine, then delete it from the server; revoke the tokens after the run (`DELETE FROM personal_access_tokens WHERE name = 'k6'`).

Rule of thumb: at least twice the peak arrival rate in users for `search.js`, 4 × `MSG_RATE` users with a conversation for `chat.js`.

## 4. Running

Run k6 from a machine with a good uplink close to the server (another VPS in Mumbai), not from the server itself:

```bash
export BASE_URL=https://api.staging.example USERS_FILE=./k6-users.json
k6 run -e PROFILE=smoke browse.js                         # setup check
k6 run --summary-export=browse.json browse.js
k6 run --summary-export=search.json search.js
k6 run --summary-export=chat.json -e REVERB_KEY=<staging REVERB_APP_KEY> -e SOCKETS=2000 -e MSG_RATE=100 chat.js
k6 run --summary-export=upload.json -e IMAGE_FILE=./photo.jpg upload.js
RATE_STAGES=50:2m,200:5m k6 run search.js                 # a custom ramp
```

During each run, on the server: `htop` (CPU, memory, swap), `mysqladmin processlist`, Horizon's dashboard (queue wait times, especially `media` during uploads and `realtime` during chat), `redis-cli info stats`, `/var/log/php-fpm` slowlog, and `curl -s …/api/v1/health/ready`.

## 5. Report

One table per script in the PR that closes OPS-18.17:

| Path | p50 | p95 | p99 | errors | breaking point (rate where p95 or errors first fail) | what gave out first |
|---|---|---|---|---|---|---|
| home | | | | | | |
| feed | | | | | | |
| ad | | | | | | |
| search | | | | | | |
| chat send / delivery | | | | | | |
| sockets held | | — | — | | | |
| upload | | | | | | |

Plus: the peak `pm_max_children` in use, MariaDB threads and buffer-pool hit rate, Redis memory, Meilisearch CPU, and the `media` queue's longest wait. The numbers feed back into `deploy/php/api.php-fpm.yaml`, `deploy/mariadb/zz-qbazaar.cnf` and the Reverb decision in [DOCS/M5-LAUNCH-RUNBOOK.md](../../../DOCS/M5-LAUNCH-RUNBOOK.md) step 8.
