# QBazaar — خطة العمل التنفيذية (Solo Dev Edition)

## Context

**QBazaar** منصة marketplace قطرية على ستاك Laravel 13 (API) + Next.js 15 (Web) + Flutter (Phase 2). الوثائق المعمارية موجودة في [DOCS/](DOCS/) وفيها:
- المخطط المعماري الشامل (architecture decisions، API contract، RBAC، i18n)
- خطة الـ Backend التنفيذية (13 sprints مع endpoints، migrations، policies، actions)
- PRD كامل (PDF)

**الهدف من هذه الخطة:** تكييف الوثائق الموجودة مع قيود الواقع الحالي (مطور واحد، Laragon، MySQL، polyrepo) + تعريف workflow لتتبع التقدم عبر المايلستونس والسبرنتات.

**لماذا هذه الخطة:** الوثائق فيها التفاصيل التقنية، لكنها تفترض فريق (3 devs) + بيئة Docker + PostgreSQL. أنت تشتغل solo على Laragon Windows مع MySQL — الخطة هنا تترجم الـ sprints لخطوات منفذة بهذه القيود، وتحدد كيف نسلسلها بحيث لا يكون فيه frontend معطّل ينتظر backend.

---

## القرارات المعتمدة (Locked Decisions)

| القرار | الاختيار | الانحراف عن الوثيقة |
|--------|-----------|----------------------|
| Database | **MySQL 8** | الوثيقة بتقول PostgreSQL 16 — راجع `Risk #1` |
| بيئة التطوير | **Laragon (no Docker)** | الوثيقة بتفترض Sail — راجع `Risk #2` |
| استراتيجية الـ repos | **Polyrepo** (qbazaar-api / qbazaar-web / qbazaar-contracts) | مطابق |
| النطاق | **Full MVP** (Sprint 0–12) بدون Mobile و بدون Phase 2 | مطابق |
| نقطة البدء | **Sprint 0 — Infrastructure & Foundation** | مطابق |
| Cache/Queue/Sessions | **Redis كامل** (Memurai على Windows) | مطابق |
| Real-time | **Reverb من Sprint 8** | مطابق |
| طريقة العمل | **Multi-agent parallel** — backend + frontend بالتوازي عبر Mock Server | تكييف ذكي لـ solo dev |
| Mock Server | **Prism** على OpenAPI contract — من Sprint 0 | جديد (لازم للتوازي) |
| Task tracking | **GitHub Issues + Milestones + ROADMAP.md** في contracts repo | جديد (مش في الوثيقة) |

---

## High-Level Roadmap (المايلستونس)

كل **Milestone = مرحلة** كبيرة، وداخلها **عدة Sprints**، وكل sprint فيه **Issues** صغيرة.

```
Milestone 1 — Backend Foundation (أسبوعين)
  ├── Sprint 0: Infrastructure (1 أسبوع)
  ├── Sprint 1: Auth (3 أيام)
  ├── Sprint 2: Users (3 أيام)
  └── Sprint 3: Categories & Locations (2 أيام)

Milestone 2 — Marketplace Core (3 أسابيع)
  ├── Sprint 4: Uploads (2 أيام)
  ├── Sprint 5: Ads (أسبوعين)
  └── Sprint 6: Search (3 أيام)
  → Frontend setup يبدأ بالتوازي هنا

Milestone 3 — Engagement (3 أسابيع)
  ├── Sprint 7: Favorites & Recently Viewed (1 يوم)
  ├── Sprint 8: Messaging via Reverb (أسبوعين)
  └── Sprint 9: Offers (1 يوم)

Milestone 4 — Trust & Admin (أسبوعين)
  ├── Sprint 10: Reports & Notifications (1 أسبوع)
  └── Sprint 11: Filament Admin Panel (1 أسبوع)

Milestone 5 — Content & Polish (أسبوع)
  └── Sprint 12: CMS, Help, Support (2 أيام)
  └── QA + Buffer (3 أيام)

Milestone 6 — Web Frontend (يتم بالتوازي من Milestone 2)
  راجع قسم "Frontend Parallel Track" أدناه

Milestone 7 — Launch Prep (أسبوع)
  └── Staging deploy + UAT + Production deploy
```

**الإجمالي:** ~14 أسبوع للـ MVP الكامل (Backend + Web).

---

## Design System (من mockup الجاهز في `DOCS/bazzar/`)

التصميم موجود ومتكامل كـ React + inline-styles mockup. مهمتنا **ترجمته** لـ Next.js + Tailwind + shadcn، **مش إعادة تصميم**.

### الـ Visual Identity

| العنصر | القيمة |
|---------|--------|
| اسم البراند | **QBazaar** (الـ mockup يقول "Bazzar" — يُستبدل عند الترجمة لـ Next.js) |
| Tagline | EN: "Qatar's friendly classifieds marketplace" / AR: "سوق قطر الودود للإعلانات المبوبة" |
| Vibe | Warm, friendly, Mediterranean, premium-but-approachable |

### Color Palette (Light Mode)

```css
--coral:        #EE8765   /* Primary action */
--terracotta:   #B85A45   /* Hover/emphasis */
--cream-50:     #FAF6F1   /* Page background */
--cream-100:    #FFFFFF   /* Cards/surfaces */
--cream-200:    #F1EBE2   /* Subtle backgrounds */
--ink-900:      #2A2622   /* Primary text */
--ink-700:      #4A4540   /* Secondary text */
--ink-500:      #8A847C   /* Muted text */
--ink-300:      #D8CFC0   /* Strong borders */
--ink-200:      #E8E2D8   /* Borders */
--sage:         #6B8E6B   /* Success/Eco accent */
```

**Coral فقط للـ MVP.** Saffron/Rose/Forest في الـ mockup ما نلتزم فيهم.

### Color Palette (Dark Mode — مشتقة)

سنشتق Dark mode من الـ light palette مع الحفاظ على الـ warm vibe:

```css
--coral:        #FA9C7E   /* أفتح شوية ليبان على الـ dark */
--terracotta:   #D87560
--cream-50:     #1A1714   /* Dark page background */
--cream-100:    #221E1A   /* Cards */
--cream-200:    #2B2622   /* Subtle backgrounds */
--ink-900:      #FAF6F1   /* Primary text — reversed */
--ink-700:      #E8E2D8
--ink-500:      #A8A29B
--ink-300:      #5C5650
--ink-200:      #3D3833
--sage:         #8FAC8F
```

### Typography

```css
--font-ui:       'DM Sans', system-ui, sans-serif;    /* UI body/labels */
--font-display:  'Instrument Serif', Georgia, serif;  /* Headlines + accents (italic) */
```

**العربي:** نضيف **Cairo** أو **IBM Plex Sans Arabic** عبر `next/font/google`. للـ headings العربية: **Tajawal** أو نبقي على نفس DM Sans لو يدعم العربي بشكل جيد.

### Spacing & Radius

| العنصر | القيمة |
|---------|--------|
| Container max-width | `1240px` |
| Padding sections | `64px–80px` vertical |
| Border radius (cards) | `12–14px` |
| Border radius (chips/pills) | `999px` |
| Shadows (cards) | `0 10px 32px rgba(42,38,34,0.06)` |
| Shadows (floating/elevated) | `0 14px 40px rgba(42,38,34,0.10)` |

### الصفحات الموجودة في الـ Mockup

| الصفحة | ملف | حالة |
|--------|------|------|
| Home | `home.jsx` | 3 variants (Hero/Mag/Util) |
| Search | `search.jsx` | جاهز |
| Ad Detail | `detail.jsx` | جاهز |
| Post Ad | `post.jsx` | جاهز |
| Messages | `messages.jsx` | جاهز |
| Profile | `profile.jsx` | جاهز |
| Saved (Favorites) | `saved.jsx` | جاهز |
| Auth (signin/signup) | `auth.jsx` | جاهز |
| Help / FAQ | `help.jsx` | جاهز |

> **القاعدة:** كل صفحة في Next.js لها mockup مرجعي في `DOCS/bazzar/src/pages/*.jsx`. الـ Frontend Agent يقرأها كـ design reference.

### Assets

| ملف | استخدام |
|------|----------|
| `DOCS/uploads/logo_color_upload-1779264340443.png` | اللوغو الرئيسي |
| `DOCS/uploads/Asset 1-6.svg` | أيقونات/illustrations مساعدة (نتحقق من كل واحد) |

ننقلها إلى `qbazaar-web/public/brand/` في Sprint 0.

### Frontend Stack النهائي للـ UI

| الطبقة | الأداة | لماذا |
|--------|--------|---------|
| Styling | **Tailwind CSS 4** | يدعم RTL via `rtl:` modifier + custom CSS vars |
| Components | **shadcn/ui** | primitives نملكها بالكامل، نلوّنها بـ palette الـ Bazzar |
| Theme | **next-themes** | dark mode toggle + system preference |
| Icons | **Lucide React** | متوافق مع الـ icons في الـ mockup |
| Fonts | **next/font/google** | DM Sans + Instrument Serif + Cairo |
| i18n | **next-intl** | AR/EN + RTL |
| Animations | **Framer Motion** (اختياري Sprint 11+) | floating cards animations |
| Forms | **React Hook Form + Zod** | matches backend validation |
| Image | **next/image + Sharp** | optimization تلقائي |
| Carousel/Gallery | **Embla Carousel** | gallery للـ ad details |

### Tailwind Config (Sprint 0 Day 6)

```ts
// tailwind.config.ts
export default {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        coral: { DEFAULT: 'rgb(var(--coral) / <alpha-value>)' },
        terracotta: { DEFAULT: 'rgb(var(--terracotta) / <alpha-value>)' },
        cream: { 50: 'rgb(var(--cream-50))', 100: 'rgb(var(--cream-100))', 200: 'rgb(var(--cream-200))' },
        ink: { 200: 'rgb(var(--ink-200))', 300: 'rgb(var(--ink-300))', 500: 'rgb(var(--ink-500))', 700: 'rgb(var(--ink-700))', 900: 'rgb(var(--ink-900))' },
        sage: { DEFAULT: 'rgb(var(--sage))' },
      },
      fontFamily: {
        ui: ['var(--font-dm-sans)', 'system-ui', 'sans-serif'],
        display: ['var(--font-instrument-serif)', 'Georgia', 'serif'],
        arabic: ['var(--font-cairo)', 'system-ui', 'sans-serif'],
      },
      borderRadius: { card: '14px' },
      maxWidth: { container: '1240px' },
    },
  },
};
```

### Decisions حول التصميم (محسومة)

| القرار | الاختيار |
|---------|---------|
| اسم البراند | **QBazaar** (في كل الـ UI، الـ domain، الـ codename) |
| Home variant | **A — Hero** فقط. نحذف B/C من الـ codebase |
| Palette | **Coral** فقط. Saffron/Rose/Forest محذوفة من الـ MVP |
| Tagline | "Qatar's friendly classifieds marketplace" (نترجمها للعربي: "سوق قطر الودود للإعلانات المبوبة") |
| Brand mark | اللوغو الموجود في `DOCS/uploads/logo_color_upload-*.png` |

**استبدال نصوص الـ mockup:**
- في كل الـ JSX mockups فيه استخدام لكلمة "Bazzar" — نستبدلها بـ "QBazaar" أثناء الترجمة للـ Next.js.
- Trust strip: "Verified sellers · Free to post · 4.8 community rating" نراجعها (الـ 4.8 rating لازم يكون فعلي لاحقاً، نخفيها في الـ MVP).

---

## Multi-Agent Parallel Workflow

**الفلسفة:** بكل sprint، أنا (Claude) أشغّل **agents بالتوازي** عبر Agent tool:
- **Backend Agent** — يشتغل في `qbazaar-api/`
- **Frontend Agent** — يشتغل في `qbazaar-web/`
- **Contract Owner** (أنا) — يحدّث OpenAPI spec قبل ما الـ agents يبدأوا

**كيف يشتغل التوازي بدون ما الـ frontend يستنى الـ backend:**

```
                  ┌──────────────────────────────────┐
                  │  1. Contract First (أنا)         │
                  │     - أعرّف endpoints في v1.yaml │
                  │     - أمثلة JSON responses        │
                  │     - error codes                 │
                  └────────────┬─────────────────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
    ┌──────────────────┐              ┌──────────────────┐
    │ Backend Agent    │              │ Frontend Agent   │
    │ - Migrations     │   parallel   │ - يستهلك Prism   │
    │ - Form Requests  │   ←--------→ │ - يبني UI        │
    │ - Controllers    │              │ - TanStack Query │
    │ - Pest tests     │              │ - Mock data من   │
    │ - Scribe         │              │   OpenAPI        │
    └────────┬─────────┘              └────────┬─────────┘
             │                                 │
             └────────────┬────────────────────┘
                          ▼
              ┌─────────────────────────┐
              │ Integration (أنا)        │
              │ - بدّل Prism URL بالـ    │
              │   actual API URL         │
              │ - E2E test               │
              │ - bug fixes              │
              └─────────────────────────┘
```

**Prism Mock Server:**
- يقرأ `qbazaar-contracts/openapi/v1.yaml` ويولّد responses تلقائياً من الـ examples
- يشتغل على `http://localhost:4010`
- Frontend يستخدمه عبر env: `NEXT_PUBLIC_API_URL=http://localhost:4010` في dev
- لما الـ backend يجهز للـ endpoint، بدّل الـ URL

**التوازي per Sprint:**

| الأسبوع | Backend Track | Frontend Track | Mock Status |
|--------|---------------|----------------|-------------|
| 1 (Sprint 0) | Laravel + DB + Redis + Pint/PHPStan/Pest | Next.js + Tailwind + shadcn + Echo client setup | Prism setup + skeleton spec |
| 1.5 (Sprint 1) | Auth endpoints + Sanctum + OTP | Auth pages (Login/Register/OTP) | Auth endpoints في spec ✓ |
| 2 (Sprint 2-3) | Users + Profiles + Categories + Locations | Account dashboard + Category browse | spec كامل لهذه الموديولز |
| 3-5 (Sprint 4-5) | Uploads + Ads + Auto-moderation | Home + Ad Details (SSR) + Post Ad flow | Ads endpoints |
| 5-6 (Sprint 6-7) | Search (Scout) + Favorites | Search results + Filters + Favorites UI | Search endpoints |
| 6-8 (Sprint 8-9) | Messaging (Reverb) + Offers | Chat UI + Echo connection + Offers | Messages + Offers |
| 9-10 (Sprint 10) | Reports + Notifications | Notifications + Reports UI | Notifications |
| 10-11 (Sprint 11) | Filament Admin | Polish + QA + responsive fixes | — |
| 11-12 (Sprint 12) | CMS + Support | CMS pages + Help center | CMS endpoints |
| 13 | Integration polish | Integration polish | — |
| 14 | Staging + Production deploy | Vercel/Forge deploy | — |

> **القاعدة الذهبية:** OpenAPI spec يُكتب **قبل** أي كود في الـ sprint. الـ Frontend Agent يبدأ على Mock فوراً، والـ Backend Agent يلحقه. الـ integration يصير لما الـ backend endpoint يمر Pest tests + Scribe documented.

### Agent Spawning Pattern

في كل sprint، بدورة العمل العامة:

```
1. Contract Update (أنا)
   - حدّث openapi/v1.yaml لـ endpoints هذا الـ sprint
   - حدّث ROADMAP.md
   - أنشئ GitHub Issues

2. Spawn Backend Agent (Agent tool)
   - Task: "نفّذ Sprint N backend tasks من ROADMAP"
   - مع context: paths، Form Requests examples، DoD checklist

3. Spawn Frontend Agent بالتوازي (Agent tool — نفس الرسالة)
   - Task: "نفّذ Sprint N frontend tasks من ROADMAP"
   - مع context: استهلك Mock من localhost:4010

4. Integration phase (أنا)
   - بدّل API URL في .env.local من Mock لـ Actual
   - اختبر E2E
   - عدّل أي مخالفات OpenAPI ↔ Implementation
```

> **ملاحظة:** الـ Agent tool ما يخزن state بين الـ calls، فلازم أعطي كل agent context كامل وملف الـ ROADMAP الحالي.

---

## Sprint 0 — تفاصيل التنفيذ (الأسبوع الأول)

> **هذا هو السبرنت الوحيد المكتمل التخطيط هنا.** كل sprint بعده يتم تخطيطه بالتفصيل في بداية أسبوعه (sprint planning session داخل ROADMAP.md).

### Day 1 — Repo & Workspace Setup

**Goal:** 3 repos منشأة وجاهزة.

1. أنشئ على GitHub (private repos):
   - `qbazaar-api` — Laravel backend
   - `qbazaar-web` — Next.js frontend
   - `qbazaar-contracts` — OpenAPI + ROADMAP + error codes

2. Clone الـ 3 repos تحت `c:\laragon\www\`:
   ```
   c:\laragon\www\
     qbazaar-api\
     qbazaar-web\
     qbazaar-contracts\
     QB\              ← الوثائق الحالية (احتفظ به للمرجعية)
   ```

3. في كل repo، أضف `README.md` + `.gitignore` مناسب + `LICENSE` لو بدك.

4. في `qbazaar-contracts`:
   - أنشئ `ROADMAP.md` (نموذج تحت)
   - أنشئ `openapi/v1.yaml` (skeleton فاضي)
   - أنشئ `error-codes.md`
   - أنشئ `events/` (للـ WebSocket events)
   - Commit أولي

### Day 2 — Laravel Backend Bootstrap

**Goal:** Laravel جاهز مع كل الحزم.

```bash
cd c:\laragon\www\qbazaar-api
composer create-project laravel/laravel . "^13.0"
# إذا Laravel 13 ما طلع لسا (راجع laravel.com)، استخدم آخر إصدار stable

# Core packages
composer require laravel/sanctum laravel/scout laravel/reverb laravel/horizon laravel/pulse

# Spatie ecosystem
composer require spatie/laravel-permission
composer require spatie/laravel-medialibrary
composer require spatie/laravel-activitylog
composer require spatie/laravel-translatable
composer require spatie/laravel-query-builder
composer require spatie/laravel-data

# Search + Image + Filament + Docs
composer require meilisearch/meilisearch-php
composer require intervention/image
composer require filament/filament:"^5.0"
composer require knuckleswtf/scribe

# Notifications channels (نسجل الحسابات لاحقاً)
composer require twilio/sdk
composer require laravel-notification-channels/fcm

# Dev dependencies
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
composer require --dev laravel/telescope
composer require --dev larastan/larastan
composer require --dev laravel/pint
```

تشغيل المنشورات (publishes):
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Laravel\Telescope\TelescopeServiceProvider"
php artisan filament:install --panels
php artisan scribe:install
```

### Day 3 — Local Services (Windows / Laragon)

**Goal:** Redis + Meilisearch شغّالين محلياً.

1. **MySQL** — موجود بـ Laragon. أنشئ DB:
   ```sql
   CREATE DATABASE qbazaar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Redis على Windows** — خيارات:
   - **Memurai** (موصى به، أداء عالي): https://www.memurai.com/get-memurai (مجاني للتطوير)
   - **Redis Windows port** من Microsoft archive (قديم)
   - **WSL2** (إذا متاح): `apt install redis-server`

3. **Meilisearch** — تحميل binary من https://github.com/meilisearch/meilisearch/releases:
   - حمّل `meilisearch-windows-amd64.exe`
   - حطه في `c:\meilisearch\` وأنشئ `start.bat` لتشغيله على port 7700

4. اضبط `.env`:
   ```
   APP_NAME=QBazaar
   APP_URL=http://qbazaar-api.test    # Laragon auto-host
   DB_CONNECTION=mysql
   DB_DATABASE=qbazaar
   DB_USERNAME=root
   DB_PASSWORD=
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=redis
   SCOUT_DRIVER=meilisearch
   MEILISEARCH_HOST=http://localhost:7700
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=qbazaar
   REVERB_APP_KEY=local-dev-key
   REVERB_APP_SECRET=local-dev-secret
   REVERB_HOST=localhost
   REVERB_PORT=8080
   ```

### Day 4 — Project Structure (Foundation Code)

أنشئ الهيكل من قسم 4 في الوثيقة. الأولويات:

1. **Config:** `config/qbazaar.php` (constants: `max_ad_images`, `ad_lifetime_days`, `otp_ttl_minutes`، إلخ).

2. **Enums** (`app/Enums/`):
   - `AdStatus`, `UserStatus`, `AccountType`, `PriceType`, `Condition`, `Language`, `MessageType`, `OfferStatus`, `ReportTarget`.

3. **Error codes** (`app/Exceptions/ErrorCode.php`) — راجع Sprint 1 في الوثيقة.

4. **Middleware:**
   - `LocaleMiddleware` — يقرأ `Accept-Language`
   - `ApiResponseWrapper` — يلف كل response في `{success, data, meta}` أو `{success, error}`
   - `TrackClient` — يكتشف web/mobile/unknown

5. **Global Exception Handler** في `bootstrap/app.php` (Laravel 11+) — JSON moveدة لكل exceptions.

6. **Routes:**
   - `routes/api.php` يحمّل `routes/api_v1.php` تحت prefix `/api/v1`
   - `routes/api_v1.php` فاضي حالياً، نملاه مع كل sprint

7. **Health check endpoint** — Laravel 11+ فيه `/up` تلقائياً.

### Day 5 — Tooling & Quality Gates

1. **Laravel Pint** — `pint.json` بإعدادات Laravel preset.

2. **PHPStan + Larastan level 8:**
   ```bash
   ./vendor/bin/phpstan analyse --memory-limit=1G
   ```
   `phpstan.neon` (level 8، paths: app/).

3. **Pest:**
   ```bash
   php artisan pest:install   # أو من Pest CLI
   ```
   `tests/Pest.php` + sample test يمر.

4. **Scribe:** `php artisan scribe:generate` — `/api/docs` متاح.

5. **GitHub Actions CI** في `.github/workflows/ci.yml`:
   - Lint (Pint)
   - Static analysis (PHPStan)
   - Tests (Pest)
   - يشتغل على push/PR

6. **Rate Limiters** — `bootstrap/app.php`:
   - `auth`, `otp`, `search`, `publish`, `messages`, `api` كما في قسم 7.3 من الوثيقة.

7. **Sentry SDK installation** (مع `SENTRY_DSN=` فاضي، نملاه لما نسجل حساب لاحقاً).

### Day 6 — Next.js Skeleton + Design System

**Goal:** Next.js يفتح، الـ theme جاهز (Bazzar palette + fonts + dark mode)، ما فيه features.

```bash
cd c:\laragon\www\qbazaar-web
npx create-next-app@latest . --typescript --tailwind --app --src-dir=false --import-alias="@/*"

# Core
npm install @tanstack/react-query @tanstack/react-query-devtools zustand axios
npm install next-intl
npm install react-hook-form zod @hookform/resolvers
npm install laravel-echo pusher-js
npm install nuqs
npm install lucide-react
npm install sharp
npm install next-themes
npm install embla-carousel-react
# Framer Motion نؤجله لـ Sprint 11

# shadcn/ui
npx shadcn@latest init   # نختار: TypeScript, Tailwind v4, RSC, custom theme
npx shadcn@latest add button input card label form sheet dialog dropdown-menu badge avatar tabs textarea select switch

# Dev
npm install -D @types/node
```

**خطوات إضافية لتطبيق الـ Bazzar design system:**

1. **انقل الـ assets** من `c:\laragon\www\QB\DOCS\uploads\` إلى `qbazaar-web/public/brand/`:
   - `logo_color_upload-1779264340443.png` → `public/brand/logo.png`
   - `Asset 1-6.svg` → `public/brand/asset-{1-6}.svg`

2. **اضبط الخطوط** في `app/layout.tsx`:
   ```ts
   import { DM_Sans, Instrument_Serif, Cairo } from 'next/font/google';
   const dmSans = DM_Sans({ subsets: ['latin'], variable: '--font-dm-sans' });
   const instrumentSerif = Instrument_Serif({ subsets: ['latin'], weight: '400', style: ['normal', 'italic'], variable: '--font-instrument-serif' });
   const cairo = Cairo({ subsets: ['arabic'], variable: '--font-cairo' });
   ```

3. **`app/globals.css`** — اربط الـ CSS variables بـ light/dark modes:
   ```css
   :root {
     --coral: 238 135 101;
     --terracotta: 184 90 69;
     --cream-50: 250 246 241;
     /* ... كل الـ palette */
   }
   .dark {
     --coral: 250 156 126;
     --cream-50: 26 23 20;
     /* ... dark palette */
   }
   ```

4. **`tailwind.config.ts`** — استخدم الـ config المذكور في قسم Design System.

5. **`app/[locale]/layout.tsx`** — RTL aware:
   ```tsx
   <html lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'} suppressHydrationWarning>
     <body className={`${dmSans.variable} ${instrumentSerif.variable} ${cairo.variable} bg-cream-50 text-ink-900 font-ui`}>
       <ThemeProvider attribute="class" defaultTheme="light" enableSystem>
         {children}
       </ThemeProvider>
     </body>
   </html>
   ```

6. **`middleware.ts`** — next-intl i18n routing (`/ar/*` و `/en/*`).

7. **`i18n/ar.json` و `i18n/en.json`** — فاضيات بس مع `{}` أولاً.

8. **`lib/api/client.ts`** — axios instance:
   ```ts
   import axios from 'axios';
   export const api = axios.create({
     baseURL: process.env.NEXT_PUBLIC_API_URL,
     headers: { 'Accept': 'application/json', 'Accept-Language': 'ar' },
   });
   // interceptors فاضيات الآن، نملاها في Sprint 1
   ```

9. **`components/ui/logo.tsx`** — استخدم اللوغو الجديد.

10. **`components/theme-toggle.tsx`** — toggle بسيط للـ light/dark.

11. **Home page placeholder** — صفحة فاضية بـ logo + "Coming soon" بالـ Bazzar typography (Instrument Serif italic) للتأكد إن الـ design system شغّال.

> **مهم:** لا تبني features الآن. الـ Home الكاملة (من `home.jsx`) تنبني في **Sprint 5** لما يجهز Ads endpoint.

### Day 7 — Mock Server + Workflow Bootstrap + Sprint 1 Planning

**Goal:** Prism mock شغّال + Workflow التتبع شغّال + Sprint 1 جاهز للبدء.

1. **Prism Mock Server setup** في `qbazaar-contracts/`:
   ```bash
   # نضع package.json خفيف في contracts repo
   npm init -y
   npm install --save-dev @stoplight/prism-cli
   ```
   أضف script في `package.json`:
   ```json
   "scripts": {
     "mock": "prism mock openapi/v1.yaml --port 4010 --host 0.0.0.0",
     "validate": "prism proxy openapi/v1.yaml http://localhost:8000 --port 4010 --errors"
   }
   ```
   شغّل `npm run mock` → Mock server على `http://localhost:4010`.

2. **OpenAPI Skeleton** في `qbazaar-contracts/openapi/v1.yaml`:
   - `info` + `servers` + base `components/schemas` (User, Ad, Category, Error)
   - Auth endpoints (register, login, refresh, logout, send-otp, verify-otp) — بـ examples
   - `components/responses/Error` موحّد
   - حتى لو فاضي من backend، الـ examples تخلي Prism يولد responses صحيحة

3. **Frontend env** في `qbazaar-web/.env.local`:
   ```
   NEXT_PUBLIC_API_URL=http://localhost:4010   # Prism mock في dev
   NEXT_PUBLIC_API_URL_PROD=https://api.qbazaar.qa
   ```

4. **`ROADMAP.md`** في contracts repo — املأ القالب (راجع نموذج تحت).

5. **GitHub Projects:**
   - أنشئ Project واحد (kanban): "QBazaar MVP"
   - Columns: Backlog / Sprint Current / Backend In Progress / Frontend In Progress / Review / Done
   - اربطه بكل الـ 3 repos

6. **GitHub Milestones** — أنشئ 13 milestone (Sprint 0 → Sprint 12).

7. **GitHub Labels** موحّدة في كل repo:
   - `type:feature`, `type:bug`, `type:tech-debt`, `type:docs`
   - `priority:high`, `priority:normal`, `priority:low`
   - `area:auth`, `area:ads`, `area:messaging`, إلخ
   - `track:backend`, `track:frontend`, `track:contract`
   - `blocked`, `needs-integration`

8. **أنشئ Issues لـ Sprint 1** في `qbazaar-api` و `qbazaar-web`:
   - **Backend track:** Register, Login, Logout, Refresh, OTP send/verify/resend, Forgot/Reset password, UserObserver, Pest tests, Scribe annotations
   - **Frontend track:** Login page, Register page, OTP page (4 inputs), Forgot password flow, Auth store (Zustand), axios interceptors، protected routes
   - **Contract track:** Auth schemas في openapi/v1.yaml كاملة

9. **Sprint 0 Retro** — اكتب في ROADMAP.md ما تم وما لم يتم.

---

## ROADMAP.md — القالب

ملف `qbazaar-contracts/ROADMAP.md` هو **مصدر الحقيقة الواحد** للحالة الحالية. الشكل:

```markdown
# QBazaar — Roadmap & Progress

> آخر تحديث: 2026-05-XX

## النطاق
- MVP: Sprint 0–12 (Backend + Web). بدون Mobile و بدون Phase 2.
- المدة المتوقعة: 14 أسبوع.

## الحالة الحالية
- **Active Milestone:** Sprint 1 — Auth
- **Active Issues:** [Auth] Register endpoint, [Auth] Login
- **Blockers:** لا يوجد

## Milestones

### ✅ Milestone 1: Backend Foundation
- [x] Sprint 0 — Infrastructure (✅ 2026-05-20)
- [ ] Sprint 1 — Auth
- [ ] Sprint 2 — Users
- [ ] Sprint 3 — Categories & Locations

### ⏳ Milestone 2: Marketplace Core
...

## Sprint Retros
### Sprint 0 (✅ 2026-05-20)
- ✅ كل الـ tasks تمت
- 🟡 ملاحظة: Memurai احتاج تثبيت يدوي
- 🔴 لا يوجد blockers

### Sprint 1 (TBD)
...

## Decisions Log
| التاريخ | القرار | المبرر |
|---------|--------|---------|
| 2026-05-20 | MySQL بدل PostgreSQL | توافق Laragon + بساطة solo dev |
| 2026-05-20 | Polyrepo بدون contracts shared lib | بساطة، types generated locally |

## Open Questions
- متى نسجل Twilio؟ (نحتاجه في Sprint 1)
- موفر الـ hosting النهائي؟
```

---

## Workflow Rules (قواعد سير العمل)

1. **Sprint Planning** في بداية كل sprint:
   - حدّث `ROADMAP.md` بحالة السبرنت السابق
   - أنشئ GitHub Issues لكل المهام في السبرنت الحالي تحت الـ Milestone
   - حدد deadline للـ milestone

2. **يومياً:**
   - افتح Issue واحد من Sprint Current
   - فرع branch: `feature/sprint-{N}-{short-desc}`
   - افتح PR ضد `develop` لما تخلص
   - اربط الـ PR بالـ Issue (`Closes #N`)

3. **بعد كل endpoint يخلص (مفروضة دائماً):**
   1. **Scribe annotations** على الـ Controller + Form Request (`@group`, `@bodyParam`, `@response`, `@queryParam`, `@urlParam`). شغّل `php artisan scribe:generate` — `/docs` و `/swagger` لازم يعرضوا الـ endpoint الجديد بشكل صحيح.
   2. **حدّث `openapi/v1.yaml`** في `qbazaar-contracts/` إذا الـ endpoint عُرّف هنا أولاً (contract-first). الـ Scribe-generated openapi هو snapshot من الـ code — `qbazaar-contracts/openapi/v1.yaml` هو الـ canonical authored spec.
   3. **حدّث `qbazaar-contracts/postman/qbazaar.postman_collection.json`** بإضافة request جديد تحت الـ folder المناسب (`Auth`, `Users`, `Ads` ...). اسم الـ request بصيغة `METHOD /path`. أضف `tests` script لو الـ response بيحمل شي نلتقطه في env (مثلاً tokens).
   4. **حدّث `qbazaar-contracts/postman/qbazaar.local.postman_environment.json`** فقط لو الـ endpoint بيحتاج env variable جديد.
   5. **اختبر يدوياً** بـ Postman (import المجلدين الـ JSON من `qbazaar-contracts/postman/`، اختر الـ env، أرسل الطلب).
   6. **Commit:** الخطوات 2-4 ضمن نفس الـ task commit (`feat(domain): ... [TASK-ID]`)، أو في follow-up `docs(postman): sync collection [TASK-ID]` لو الـ endpoint كان commit مسبق.

4. **نهاية كل sprint:**
   - Demo داخلي (لنفسك): اختبر الـ endpoints عبر Postman/Scribe/Swagger
   - اكتب Retro في ROADMAP.md
   - أغلق الـ milestone على GitHub
   - مرّر develop → main لما يكون كل شي مستقر

5. **Definition of Done لكل endpoint** (مفعّلة لكل task):
   - Route معرّف في `routes/api_v1.php`
   - Form Request مع validation rules كاملة
   - API Resource للـ response
   - Policy (إذا حساس)
   - Activity log (إذا حساس)
   - Localization keys (ar + en) لـ كل message_key مذكور
   - Pest Feature test (happy path + edge cases)
   - **Scribe annotations مكتملة + `php artisan scribe:generate` يمر**
   - **Postman collection + env محدّثين (راجع الخطوة 3 فوق)**
   - PHPStan level 8 يمر
   - Pint يمر
   - Migration + rollback يعملوا

6. **API Documentation surfaces (3 ports of entry):**
   - **`/docs`** — Scribe auto-derived من PHPDoc. Read-only, shows ground-truth implementation.
   - **`/swagger`** — Swagger UI loading `qbazaar-contracts/openapi/v1.yaml`. Source of truth for contract-first work.
   - **`qbazaar-contracts/postman/`** — Postman collection + env. The on-the-go testing bench.

   كل واحد بيتحدّث في خطوة محددة من البروتوكول فوق. ممنوع نشحن endpoint لـ main بدون ما الثلاثة يكونوا up-to-date.

---

## ملفات Sprint 0 الحرجة (للمرجعية)

- `qbazaar-api/.env.example`
- `qbazaar-api/config/qbazaar.php`
- `qbazaar-api/app/Enums/*.php`
- `qbazaar-api/app/Exceptions/ErrorCode.php`
- `qbazaar-api/app/Http/Middleware/{LocaleMiddleware,ApiResponseWrapper,TrackClient}.php`
- `qbazaar-api/bootstrap/app.php` (Laravel 11+ format)
- `qbazaar-api/routes/api_v1.php`
- `qbazaar-api/phpstan.neon`
- `qbazaar-api/pint.json`
- `qbazaar-api/.github/workflows/ci.yml`
- `qbazaar-web/middleware.ts`
- `qbazaar-web/app/[locale]/layout.tsx`
- `qbazaar-web/lib/api/client.ts`
- `qbazaar-contracts/ROADMAP.md`
- `qbazaar-contracts/openapi/v1.yaml`
- `qbazaar-contracts/error-codes.md`

---

## المخاطر والمعالجات

### Risk #1: MySQL بدل PostgreSQL
- **التأثير:** فقدان JSONB المتقدم (GIN indexes، query operators).
- **المعالجة:**
  - MySQL 8 يدعم JSON columns مع functional indexes — كافي لمعظم استخدامات Spatie Translatable و custom_fields في الإعلانات.
  - عند الحاجة لبحث داخل JSON بكثافة → Meilisearch هو المسؤول (مش MySQL).
  - **التحقق:** إذا في query patterns صعبة على MySQL نعيد تقييم الـ DB في Sprint 5.

### Risk #2: Laragon بدل Sail
- **التأثير:** اختلاف بين بيئة dev و production (الـ production غالباً Linux + Docker).
- **المعالجة:**
  - استخدم `.env` متطابق قدر الإمكان.
  - الـ paths لازم تكون cross-platform (لا hardcode لـ `C:\`).
  - في Sprint 11 (قبل deploy) — جرّب Sail على Linux/WSL2 للتأكد.

### Risk #3: Reverb على Windows
- **التأثير:** Reverb يحتاج terminal process مستمر — على Windows أصعب من Linux.
- **المعالجة:**
  - في dev: شغّل `php artisan reverb:start` في terminal منفصل يدوياً.
  - في production: Supervisord على Linux.
  - بديل دائم: استخدم Pusher Cloud المجاني مؤقتاً (نفس API).

### Risk #4: Solo dev — Burnout
- **التأثير:** 14 أسبوع شغل مكثف يومي يخلف burnout.
- **المعالجة:**
  - حدد cap يومي (مثلاً 4-6 ساعات تركيز).
  - يوم راحة أسبوعي ثابت.
  - إذا تأخر sprint > 50% → اعد تقييم النطاق (شطب feature ولا تكسير الـ DoD).

### Risk #5: Laravel 13 لسا ما طلع
- **الاحتمال:** متوسط (Laravel releases في فبراير عادة).
- **المعالجة:** استخدم آخر stable متاح. الفروقات بين 12 و 13 minimal.

---

## حسابات/خدمات نسجل فيها لاحقاً (مش الآن)

| Sprint | الخدمة | لماذا |
|--------|---------|---------|
| Sprint 0 | **Sentry** | Error tracking (free tier) |
| Sprint 1 | **Twilio** أو MessageBird | OTP SMS لقطر |
| Sprint 4 | **Cloudflare R2** | تخزين الصور (cheaper من S3) |
| Sprint 10 | **Firebase project** | FCM push notifications |
| Sprint 11 | Laravel Forge أو DigitalOcean | Production hosting |
| قبل الإطلاق | **qbazaar.qa domain** (إذا غير مسجل) | Branding |

في dev، استبدل كل هذه بـ stubs/mocks أو local providers.

---

## Verification (كيف نعرف Sprint 0 انتهى)

1. ✅ 3 repos على GitHub، الكل يفتح ويعمل clone محلي.
2. ✅ `cd qbazaar-api && php artisan serve` → يفتح `localhost:8000/api/docs` بدون أخطاء.
3. ✅ `php artisan tinker` → `Redis::ping()` يرجع `+PONG` (Memurai يعمل).
4. ✅ Meilisearch على `localhost:7700` بيفتح Dashboard.
5. ✅ `php artisan migrate` يمشي بدون أخطاء.
6. ✅ `php artisan filament:user` ينشئ admin، و`/admin` يفتح login.
7. ✅ `cd qbazaar-web && npm run dev` → `localhost:3000` يعرض صفحة فاضية.
8. ✅ `cd qbazaar-contracts && npm run mock` → Prism على `localhost:4010` يرد على `GET /api/v1/health` بـ JSON من spec.
9. ✅ `curl http://localhost:4010/api/v1/auth/login -X POST` يرجع mock response من OpenAPI examples.
10. ✅ GitHub Actions CI خضراء على push (linting + tests).
11. ✅ `ROADMAP.md` فيه Sprint 0 retro + Sprint 1 issues منشأة (backend + frontend tracks).
12. ✅ Pint + PHPStan + Pest يمروا محلياً بدون warnings.
13. ✅ Auth endpoints موجودة كـ schemas في `openapi/v1.yaml` (حتى لو الـ backend لسا ما نفذها).

---

## ما لا يدخل في Sprint 0 (تأجيل واعٍ)

- ❌ Auth endpoints (Sprint 1)
- ❌ Database migrations الفعلية للـ business models (Sprint 1+)
- ❌ Frontend pages بأي features (تنتظر backend)
- ❌ Twilio integration (Sprint 1)
- ❌ Production deploy (Milestone 7)
- ❌ Mobile app (خارج النطاق)

> **القاعدة الذهبية:** Sprint 0 ما يكتمل إلا بـ verification list فوق. أي عمل feature قبل ذلك = مخاطرة.

---

## الخطوة التالية بعد Approval

عند موافقتك على هذه الخطة، نبدأ من **Day 1 من Sprint 0**:
- ننشئ الـ 3 repos (محلياً أولاً، ثم نرفعها لـ GitHub)
- نضع .gitignore + README + LICENSE
- نعمل initial commits

ثم نتقدم يوم بيوم. كل ما نخلص Day، نحدّث ROADMAP.md.

**نموذج لكيف ستشتغل Sprint 1 (للتوضيح):**
1. أنا أحدّث `openapi/v1.yaml` بكل auth endpoints + examples
2. أشغّل **Backend Agent** بـ Agent tool — مهمته: تنفيذ كل auth endpoints (migrations + controllers + Form Requests + Resources + Policies + Pest tests)
3. **بالتوازي** في نفس الرسالة، أشغّل **Frontend Agent** — مهمته: بناء auth pages (Login/Register/OTP/Forgot) باستخدام Prism mock على :4010
4. لما الـ Backend Agent يخلص + tests خضراء، أبدّل `NEXT_PUBLIC_API_URL` لـ `localhost:8000` وأختبر integration
5. أصلح أي mismatch بين spec ↔ implementation
6. Close Sprint 1 milestone، أكتب retro، أبدأ Sprint 2

> **التحفظ:** الـ Agent tool ما يقدر يشتغل سيرفر طويل المدى (لازم interactive). فالـ Backend Agent يكتب الكود، أنا (الـ orchestrator) أشغّل `php artisan serve` و Prism manually في terminals منفصلة.
