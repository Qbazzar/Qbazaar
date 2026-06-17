# QBazaar — نشر الباك + الفرونت على cPanel VPS (fleeteye.de)

> خطة/runbook لنشر **الـ API و الفرونت على نفس السيرفر** (WHM/cPanel، root)،
> الاثنين من **GitHub Actions**. القرارات المعتمدة:
> - الفرونت: **systemd `next start` + Apache reverse proxy**
> - التوزيع: **فرونت على `qbazaar.fleeteye.de`** + **API على `api.qbazaar.fleeteye.de`**
> - البناء: **على السيرفر مباشرة**
>
> ⚠️ هذا يستبدل إعداد CloudPanel/miete.site القديم في `deploy/README.md`.

## 0) المتغيّرات (مؤكّدة)

| المتغير | القيمة |
|---------|--------|
| `CPANEL_USER` / `DEPLOY_USER` | **`fleeteye`** |
| `HOME` | `/home/fleeteye` |
| `REPO_DIR` | `/home/fleeteye/qbazaar` (git clone، بديل رفع الـ zip) |
| دومين الفرونت | `qbazaar.fleeteye.de` |
| دومين الـ API | `api.qbazaar.fleeteye.de` |
| Node | `/usr/bin/node` · v20.20.2 · npm 10.8.2 ✅ |
| PHP CLI | `/opt/cpanel/ea-php84/root/usr/bin/php` (أكّد: `… -v` = 8.4، + ext-pcntl/posix) |
| IP السيرفر | `__FILL__` |

---

## 1) ما يلزمك تعمله أنت (لا أقدر أعمله من الكود)

### أ. DNS + الدومينات في WHM/cPanel
1. سجّلات A: `qbazaar.fleeteye.de` و `api.qbazaar.fleeteye.de` → IP السيرفر.
2. في cPanel للحساب `space`:
   - **Subdomain** `api.qbazaar.fleeteye.de` → docroot = `/home/space/qbazaar/qbazaar-api/public`.
   - الدومين الرئيسي/addon `qbazaar.fleeteye.de` → أنشئه (الـ docroot ما رح يُستخدم مباشرة لأن Apache يعمل proxy لـ Node، بس cPanel يحتاج الـ vhost موجود عشان SSL).
3. **SSL**: شغّل AutoSSL (WHM → Manage AutoSSL) أو Let's Encrypt للدومينين.

### ب. أدوات على السيرفر (root عبر SSH)
4. **Node 20**: إمّا cPanel Node selector، أو nvm تحت root/`space`. ثبّت `pm2`؟ لا — اخترنا systemd. سجّل مسار `node` المطلق (مثلًا `/home/space/nodevenv/.../bin/node` أو `~/.nvm/versions/node/v20.x/bin/node`) — رح نحتاجه في وحدة systemd.
5. **وحدات Apache للـ proxy**: تأكد أن `mod_proxy`, `mod_proxy_http`, `mod_proxy_wstunnel`, `mod_rewrite` مفعّلة (WHM → EasyApache 4 → Apache Modules).
5b. **امتدادات PHP 8.4 CLI**: تأكد أن `ea-php84` مثبّت و CLI فيه `pcntl` + `posix` (يحتاجهم Horizon و Reverb): `dnf install -y ea-php84-php-pcntl ea-php84-php-posix` ثم تحقّق `/opt/cpanel/ea-php84/root/usr/bin/php -m | grep -E 'pcntl|posix'`.
6. **Redis**: عالق من بداية الجلسة — اضبط `requirepass` و `REDIS_PASSWORD` في `.env` (راجع رسالة NOAUTH).
7. **Meilisearch**: نفّذ الـ runbook في [`deploy/README.md`](README.md#-meilisearch-on-the-whm-server) (اختياري لكنه يرجّع بحث Sprint-6).
8. **Swap** (أمان البناء): لو رام السيرفر ≤ 2GB، أنشئ 2GB swap قبل أول `next build`:
   ```bash
   fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
   echo '/swapfile none swap sw 0 0' >> /etc/fstab
   ```

### ج. مفتاح النشر + git clone
9. أنشئ زوج مفاتيح SSH للنشر، ضع العام في `~space/.ssh/authorized_keys`.
10. استبدل رفع الـ zip اليدوي بـ clone:
    ```bash
    sudo -u space -i
    cd ~ && git clone https://github.com/Qbazzar/Qbazaar.git qbazaar
    cd qbazaar && git checkout production
    ```

### د. GitHub
11. ادمج الـ 5 PRs إلى `main`، ثم أنشئ فرع `production`:
    ```bash
    git switch -c production main && git push -u origin production
    ```
12. Repo Secrets (Settings → Secrets → Actions): `DEPLOY_HOST` (IP)، `DEPLOY_USER=space`، `DEPLOY_PORT=22`، `DEPLOY_SSH_KEY` (المفتاح الخاص).

### هـ. صلاحية systemctl للمستخدم `space` (عشان CI يعيد التشغيل)
13. drop-in في sudoers (root):
    ```bash
    cat > /etc/sudoers.d/qbazaar-deploy <<'EOF'
    space ALL=(root) NOPASSWD: /usr/bin/systemctl restart qbazaar-web, \
      /usr/bin/systemctl restart qbazaar-horizon, \
      /usr/bin/systemctl restart qbazaar-reverb
    EOF
    chmod 440 /etc/sudoers.d/qbazaar-deploy
    ```

---

## 2) الملفات اللي رح أولّدها في الريبو (بعد موافقتك)

### أ. وحدات systemd (`deploy/systemd/`)
- `qbazaar-web.service` — `ExecStart=<node> <repo>/qbazaar-web/node_modules/.bin/next start -p 3000`, `User=space`, `Environment=NODE_ENV=production`, `WorkingDirectory=<repo>/qbazaar-web`, `Restart=always`.
- `qbazaar-horizon.service` — `php artisan horizon` (الطوابير: صور، إشعارات، انتهاء إعلانات).
- `qbazaar-reverb.service` — `php artisan reverb:start --host=127.0.0.1 --port=8080` (الشات الفوري).

### ب. تضمينات Apache (`deploy/apache/`)
- **الفرونت** `qbazaar.fleeteye.de` (proxy لـ Node):
  ```apache
  ProxyPreserveHost On
  ProxyPass        /  http://127.0.0.1:3000/
  ProxyPassReverse /  http://127.0.0.1:3000/
  ```
  (`next start` يخدم `/_next` والأصول الثابتة بنفسه — لا حاجة لتوجيه منفصل.)
- **الـ API** `api.qbazaar.fleeteye.de` — docroot عادي لـ Laravel + توجيه websockets لـ Reverb:
  ```apache
  RewriteEngine On
  RewriteCond %{HTTP:Upgrade} =websocket [NC]
  RewriteRule ^/(app|apps)/(.*)$ ws://127.0.0.1:8080/$1/$2 [P,L]
  ProxyPassReverse /app  ws://127.0.0.1:8080/app
  ```
  (مسار التضمين على cPanel: WHM → Apache Configuration → Include Editor، أو
  `/etc/apache2/conf.d/userdata/ssl/2_4/space/<domain>/qbazaar.conf` ثم rebuild+restart.)

### ج. تكييف سكربتات النشر
- `deploy/scripts/deploy-api.sh` — تحديث المسارات/الدومين/`HEALTH_URL=https://api.qbazaar.fleeteye.de/api/v1/health`، وإضافة `sudo systemctl restart qbazaar-horizon qbazaar-reverb` بعد الـ migrate/cache.
- `deploy/scripts/deploy-web.sh` — إزالة PM2، استبداله بـ:
  ```bash
  npm ci --no-audit --no-fund
  NODE_OPTIONS=--max-old-space-size=1536 NEXT_TELEMETRY_DISABLED=1 npm run build
  sudo systemctl restart qbazaar-web
  ```
  وتحديث الـ health probe لـ `https://qbazaar.fleeteye.de/`.

### د. env — قالبان (مولّدان)
- **الباك** `deploy/env.production.template`: `APP_URL=https://api...`، `WEB_URL=https://qbazzar...` (روابط الإشعارات للفرونت)، Reverb **مفصول**: الباك ينشر محليًا `REVERB_HOST=127.0.0.1 / PORT=8080 / SCHEME=http`. (CORS الافتراضي يكفي — لا إضافات env.)
- **الفرونت** `deploy/web.env.production.template` → `qbazaar-web/.env.production`: `NEXT_PUBLIC_API_URL=https://api...`، و Reverb العام للمتصفح `NEXT_PUBLIC_REVERB_HOST=api.qbazaar.fleeteye.de / PORT=443 / SCHEME=https`، و `NEXT_PUBLIC_REVERB_APP_KEY` = نفس `REVERB_APP_KEY` بالباك، + متغيرات `NEXT_PUBLIC_FCM_*` (اختيارية).

### هـ. الـ workflows (مولّدة/مصحّحة)
- `deploy-web.yml`: المسار صار `$HOME/qbazaar`. كلا الـ workflows: trigger على push لـ `production` + path filters (جاهزة).

---

## 3) ترتيب التنفيذ (أول نشر)

1. (أنت) القسم 1 كامل: DNS، الدومينات، SSL، Node ✅، PHP 8.4+pcntl/posix، Redis، مفتاح SSH، فرع `production`، الـ secrets، sudoers.
2. (تم ✅) ملفات القسم 2 مولّدة على فرع `chore/cpanel-fleeteye-deploy` — تُدمج لـ `main` ثم `production`.
3. (أنت، مرّة وحدة على السيرفر — بعد الـ clone والدمج):

```bash
# --- root: systemd ---
cp ~fleeteye/qbazaar/deploy/systemd/qbazaar-*.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now qbazaar-horizon qbazaar-reverb qbazaar-web

# --- root: Apache includes (انظر التعليقات داخل كل ملف للمسار الدقيق) ---
#   انسخ deploy/apache/*.include.conf إلى userdata/ssl/2_4/fleeteye/<domain>/
/scripts/ensure_vhost_includes --user=fleeteye
apachectl configtest && systemctl restart httpd

# --- fleeteye: env + مفتاح + هجرة ---
cp ~/qbazaar/deploy/env.production.template     ~/qbazaar/qbazaar-api/.env       # عبّي __FILL_ME__
cp ~/qbazaar/deploy/web.env.production.template ~/qbazaar/qbazaar-web/.env.production  # عبّي المفاتيح
cd ~/qbazaar/qbazaar-api && php artisan key:generate && php artisan migrate --force
# توليد REVERB_APP_KEY/SECRET: php artisan reverb:install (أو عبّيهم يدويًا، ووحّد APP_KEY مع الفرونت)
```

4. ادفع commit على `production` (أو `workflow_dispatch`) → Actions يبني وينشر تلقائيًا.
5. تحقّق: `curl https://api.qbazaar.fleeteye.de/api/v1/health` = 200 · `https://qbazaar.fleeteye.de/` يفتح · الشات الفوري عبر wss (devtools → WS).

---

## 4) نقاط حرجة لا تُنسى
- **CORS**: ليس blocker — لا يوجد `config/cors.php` منشور، فاللارافيل يستخدم الافتراضي (`allowed_origins: *` على `api/*`)، والمشروع يعمل cross-origin أصلًا (Vercel→API). و`/api/v1/broadcasting/auth` مغطّى تحت `api/*` ويمر بـ Bearer token. **تشديد اختياري لاحقًا**: انشر `config/cors.php` وحصر `allowed_origins` على `https://qbazaar.fleeteye.de`.
- **Reverb عبر wss**: يتطلب `mod_proxy_wstunnel`؛ بدونه الشات يفشل صامتًا. اختبره بـ devtools → WS.
- **ذاكرة البناء**: `next build` + MySQL/Redis/PHP-FPM معًا على رام صغير = خطر OOM. الـ swap في 1.ب.8 هو شبكة الأمان.
- **systemd كـ `fleeteye`**: الوحدات تشتغل بمستخدم cPanel عشان صلاحيات الملفات تتطابق مع git clone؛ والـ CI يعيد التشغيل عبر sudoers المحدود.
- **Reverb مفصول**: الباك `REVERB_HOST=127.0.0.1:8080` (نشر محلي) — المتصفح فقط يستخدم `api.qbazaar.fleeteye.de:443`. لا تخلط بينهما.
- **`next build` على السيرفر**: env الفرونت (`NEXT_PUBLIC_*`) يُحقَن وقت البناء — أي تغيير فيه يحتاج إعادة بناء (النشر يعمل ذلك)، مش مجرد restart.
- **أسرار الإطلاق** (مستقلة عن النشر): Twilio (OTP حقيقي)، Sentry DSN، Firebase (للـ push). النشر يشتغل بدونها لكن الميزات المرتبطة تبقى معطّلة بأمان.

<!-- live on https://qbazaar.fleeteye.de + https://api.qbazaar.fleeteye.de since 2026-06-17 -->
