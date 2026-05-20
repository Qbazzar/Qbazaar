# QBazaar API — Dev Setup on Windows (Laragon)

Local services needed to run the API:

| Service | How to install | Verification |
|---------|----------------|--------------|
| **MySQL 8** | Bundled with Laragon — start Laragon and click "Start All". DB `qbazaar` already created. | `php artisan migrate --pretend` lists pending migrations |
| **Memurai (Redis)** | Manual install — see below | `php artisan tinker` then `Redis::ping()` returns `+PONG` |
| **Meilisearch** | Binary already downloaded to `c:\meilisearch\meilisearch.exe` by Sprint 0 Day 3 | `c:\meilisearch\start.bat` then open http://localhost:7700 |
| **Mailpit** (optional) | Skip — we use `MAIL_MAILER=log` for now | Logs go to `storage/logs/laravel.log` |

---

## 1. Memurai (Redis for Windows)

Memurai is a drop-in Redis-compatible server for Windows (the official Redis Windows port is abandoned).

### Install steps

1. Go to **https://www.memurai.com/get-memurai**
2. Download the **Developer Edition** (free for development).
3. Run the MSI installer with defaults. It registers Memurai as a Windows service that starts on boot.
4. Verify the service is running:
   ```powershell
   Get-Service Memurai
   # Status should be "Running"
   ```
5. Test connectivity:
   ```bash
   cd c:\laragon\www\QB\qbazaar-api
   php artisan tinker --execute="echo \Illuminate\Support\Facades\Redis::ping();"
   # Should print: +PONG
   ```

### Alternative: WSL2 Redis

If you already use WSL2 and prefer it:
```bash
wsl --install -d Ubuntu      # if WSL2 not installed
sudo apt update && sudo apt install -y redis-server
sudo service redis-server start
# In .env, REDIS_HOST=127.0.0.1 still works because WSL2 forwards localhost.
```

---

## 2. Meilisearch

Binary already downloaded by `BE-0.12` to `c:\meilisearch\meilisearch.exe`. A `start.bat` should exist that launches it.

To run:
```bash
c:\meilisearch\start.bat
# OR: cd c:\meilisearch && meilisearch.exe --http-addr 0.0.0.0:7700
```

Open http://localhost:7700 → JSON status response.

In production, set `MEILISEARCH_KEY` in `.env` to a master key; in dev it's left empty.

---

## 3. Run the API

Once MySQL + Redis + Meilisearch are up:

```bash
cd c:\laragon\www\QB\qbazaar-api
php artisan migrate                  # First time only
php artisan serve                    # http://localhost:8000

# In separate terminals as needed:
php artisan queue:work               # Process jobs (Horizon won't run on Windows)
php artisan reverb:start             # WebSocket server on :8080
php artisan pail                     # Tail logs (Laravel's `tail -f`)
```

Or use Laragon's auto-host: open `http://qbazaar-api.test` (Laragon maps the folder under `www/` automatically — though we're nested under `QB/qbazaar-api`, you may need to add it manually).

---

## 4. Useful URLs (local dev)

| URL | What |
|-----|------|
| `http://localhost:8000` | API root (returns Laravel welcome until we add `/api/v1/health` in Day 5) |
| `http://localhost:8000/admin` | Filament admin panel (login required) |
| `http://localhost:8000/horizon` | Horizon dashboard (UI works; supervisor doesn't run on Windows) |
| `http://localhost:8000/telescope` | Telescope inspector (dev only) |
| `http://localhost:8000/pulse` | Pulse observability dashboard |
| `http://localhost:8000/docs` | Scribe API docs (after `php artisan scribe:generate`) |
| `http://localhost:7700` | Meilisearch dashboard |
| `http://localhost:4010` | Prism mock server (in `qbazaar-contracts` repo) |

---

## 5. Quality gates (run before pushing)

```bash
./vendor/bin/pint              # Code style
./vendor/bin/phpstan analyse   # Static analysis (level 8)
./vendor/bin/pest              # Test suite
```

CI runs all three on every push (see `.github/workflows/ci.yml`).
