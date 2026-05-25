# QBazaar — Production Deploy

This deploys the Laravel API to the **Miete VPS** under the existing
CloudPanel-managed site `www.miete.site`.

- VPS: `147.79.115.44`
- SSH user (deploy tenant): `qb-user`
- Repo clone on VPS: `/home/qb-user/qbazaar` (this monorepo)
- Webroot: `/home/qb-user/htdocs/www.miete.site` → symlink to `qbazaar-api/public`
- Public URL: `https://www.miete.site/api/v1/...`

> **Note:** the original plan used a self-managed Ubuntu with the `sanad` user
> and a separate `api.qbazzar.miete.site` subdomain. The VPS we got is
> CloudPanel-managed (no sudo, panel-managed nginx + php-fpm), so we deploy
> directly into the existing `www.miete.site` tenant. The
> `vps-bootstrap.sh` and `nginx/*.conf` files here are kept for reference but
> aren't used by this deploy.

---

## 🌿 Branch model

| Branch | Purpose | Triggers |
|--------|---------|----------|
| `main` | Dev — every feature commit lands here first | CI runs Pint + PHPStan + tests + Next.js build |
| `production` | What's live on the VPS | Push → GitHub Actions auto-deploys |

Promote with `git switch production && git merge main && git push origin production`.

---

## 🚀 First deploy (one-time bootstrap)

The very first deploy is run from your dev box via
`.deploy-keys/bootstrap_qbuser.py` (paramiko). It:

1. Logs into `qb-user@147.79.115.44` with the panel password
2. Installs our deploy SSH public key into `~/.ssh/authorized_keys` so future
   runs use key auth
3. Clones the repo to `~/qbazaar`, checks out `production`
4. Uploads `deploy/.env.production.local` to `~/qbazaar/qbazaar-api/.env`
5. Runs `composer install --no-dev`, `key:generate`, `migrate --force`
6. Symlinks `~/htdocs/www.miete.site` → `~/qbazaar/qbazaar-api/public`
7. Rebuilds config/route/view caches
8. Smoke-tests `https://www.miete.site/api/v1/health`

### Run it

```powershell
# 1) Copy the env template and fill in __FILL_ME__ for DB/Twilio/Mail/Meilisearch:
Copy-Item deploy\env.production.template deploy\.env.production.local
# Edit deploy\.env.production.local in your editor

# 2) Run the bootstrap (PowerShell)
$env:VPS_HOST = '147.79.115.44'
$env:VPS_USER = 'qb-user'
$env:VPS_PASS = '<panel password>'
$env:DEPLOY_PUBKEY_PATH = '.deploy-keys\qbazaar_deploy.pub'
python .deploy-keys\bootstrap_qbuser.py
```

After it completes successfully, **rotate the qb-user panel password** —
it was shared once in chat for bootstrap and should never be used again.

---

## 🤖 Auto-deploy (GitHub Actions)

After the first deploy, subsequent deploys are automatic:

| Workflow | Triggers when | Does |
|----------|---------------|------|
| `deploy-api.yml` | Push to `production` AND `qbazaar-api/**` changed | SSH → `deploy/scripts/deploy-api.sh` on the VPS |

A push that only touches `qbazaar-contracts/` or docs **doesn't trigger a deploy** (path filters).

### Required GitHub Secrets

Add in **`Settings → Secrets and variables → Actions`** for `Qbazzar/Qbazaar`:

| Secret | Value |
|--------|-------|
| `DEPLOY_HOST` | `147.79.115.44` |
| `DEPLOY_USER` | `qb-user` |
| `DEPLOY_PORT` | `22` |
| `DEPLOY_SSH_KEY` | full contents of `.deploy-keys/qbazaar_deploy` (private key) |

`DEPLOY_PASSWORD` is also accepted by the workflow as a fallback during bootstrap, but **remove it after key auth is confirmed working** so the password isn't ambient in CI.

---

## 🔐 The deploy SSH keypair

The private key lives at `.deploy-keys/qbazaar_deploy` (gitignored). The public
key at `.deploy-keys/qbazaar_deploy.pub` is added to `qb-user`'s
`authorized_keys` by the bootstrap script.

To re-fetch the private key locally for the GitHub Secret:

```powershell
Get-Content .\.deploy-keys\qbazaar_deploy
```

---

## Manual deploy (if Actions is down)

After the SSH key is authorized you can run the deploy script directly:

```bash
ssh -i .deploy-keys/qbazaar_deploy qb-user@147.79.115.44 \
    "cd ~/qbazaar && git pull && bash deploy/scripts/deploy-api.sh"
```

---

## Folder layout

```
deploy/
├── README.md                                ← you are here
├── env.production.template                  ← copy → .env.production.local, fill, deploy
├── .env.production.local                    ← gitignored, holds real secrets
├── scripts/
│   └── deploy-api.sh                        ← run by GitHub Actions on the VPS
├── keys/
│   └── github-actions.pub                   ← public key for CI
├── nginx/                                   ← reference only; CloudPanel owns nginx
│   ├── api.qbazzar.miete.site.conf
│   └── qbazzar.miete.site.conf
├── supervisor/                              ← reference only; queue/reverb workers
│   ├── qbazaar-queue.conf
│   └── qbazaar-reverb.conf
└── vps-bootstrap.sh                         ← legacy, for self-managed Ubuntu only
```

---

## Frontend deploy

Next.js is **not** deployed to this VPS — it lives on Vercel
(`qbazaar-web` repo, auto-deploys from `main`). Only the API runs on the VPS.
