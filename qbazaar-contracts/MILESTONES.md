# QBazaar — Milestones, User Stories, Flows & Tasks (تفصيلي)

> هذا الملف هو **مصدر الحقيقة الواحد** لكل user story و flow و task في الـ MVP. كل sprint له user stories مرقمة، flows، وتاسكات backend + frontend + contract.
>
> **التحديث:** 2026-05-20 (Sprint 0 Day 1)
> **مراجع:** `PLAN.md`، `ROADMAP.md`، `DOCS/QBazaar — خطة الـ Backend التنفيذية`، `DOCS/QBazaar — المخطط المعماري الشامل`

---

## 📑 الفهرس

- [Conventions (الترميز)](#-conventions-الترميز)
- [Global Definition of Done](#-global-definition-of-done)
- [Milestone 1 — Backend Foundation](#milestone-1--backend-foundation-أسبوعين)
  - [Sprint 0 — Infrastructure](#sprint-0--infrastructure--foundation-1-أسبوع)
  - [Sprint 1 — Auth](#sprint-1--auth-3-أيام)
  - [Sprint 2 — Users](#sprint-2--users-3-أيام)
  - [Sprint 3 — Categories & Locations](#sprint-3--categories--locations-2-أيام)
- [Milestone 2 — Marketplace Core](#milestone-2--marketplace-core-3-أسابيع)
  - [Sprint 4 — Uploads](#sprint-4--uploads-2-أيام)
  - [Sprint 5 — Ads](#sprint-5--ads-أسبوعين)
  - [Sprint 6 — Search](#sprint-6--search-3-أيام)
- [Milestone 3 — Engagement](#milestone-3--engagement-3-أسابيع)
  - [Sprint 7 — Favorites & Recently Viewed](#sprint-7--favorites--recently-viewed-1-يوم)
  - [Sprint 8 — Messaging (Reverb)](#sprint-8--messaging-via-reverb-أسبوعين)
  - [Sprint 9 — Offers](#sprint-9--offers-1-يوم)
- [Milestone 4 — Trust & Admin](#milestone-4--trust--admin-أسبوعين)
  - [Sprint 10 — Reports & Notifications](#sprint-10--reports--notifications-1-أسبوع)
  - [Sprint 11 — Filament Admin](#sprint-11--filament-admin-panel-1-أسبوع)
- [Milestone 5 — Content & Polish](#milestone-5--content--polish-أسبوع)
  - [Sprint 12 — CMS, Help, Support](#sprint-12--cms-help--support-2-أيام)
  - [QA & Buffer](#qa--buffer-3-أيام)
- [Milestone 6 — Web Frontend (مرافق)](#milestone-6--web-frontend-parallel)
- [Milestone 7 — Launch Prep](#milestone-7--launch-prep-1-أسبوع)

---

## 🔖 Conventions (الترميز)

| الترميز | المعنى |
|---------|---------|
| `US-X.Y` | User Story رقم Y في Sprint X |
| `F-X.Y` | Flow رقم Y في Sprint X |
| `BE-X.Y` | Backend Task رقم Y في Sprint X |
| `FE-X.Y` | Frontend Task رقم Y في Sprint X |
| `CT-X.Y` | Contract (OpenAPI) Task رقم Y في Sprint X |
| `[P0]` | أولوية حرجة — لازم في الـ MVP |
| `[P1]` | أولوية عالية — مهم بس يقدر يتأجل |
| `[P2]` | nice-to-have — phase 2 |

**Tracks:**
- 🔵 Backend Track — يشتغل في `qbazaar-api/`
- 🟣 Frontend Track — يشتغل في `qbazaar-web/`
- 🟡 Contract Track — OpenAPI في `qbazaar-contracts/`
- 🟢 Integration — أنا (Claude) أربط الـ tracks

---

## ✅ Global Definition of Done

**كل sprint ما يُعتبر مكتمل إلا لو:**

### Backend (🔵)
- [ ] Routes معرّفة في `routes/api_v1.php` تحت version `/api/v1`
- [ ] Form Requests لكل endpoint مع validation rules كاملة
- [ ] API Resources لكل Response (ممنوع `return $model` مباشرة)
- [ ] Policies على كل operation حساسة + Gate checks في Controllers
- [ ] Error Codes معرّفة في `app/Exceptions/ErrorCode.php`
- [ ] Localization keys في `lang/ar/*` و `lang/en/*`
- [ ] Pagination + Sorting على list endpoints
- [ ] Rate limiting محدد للـ tier المناسب
- [ ] Spatie Activitylog مفعّل على Models الحساسة
- [ ] Events + Listeners للإشعارات والـ side effects
- [ ] Pest Feature Tests لكل endpoint (happy path + edge cases)
- [ ] Pest Unit Tests للـ Services/Actions
- [ ] Coverage > 70% للـ Module
- [ ] Scribe annotations كاملة → `/api/docs` محدّث
- [ ] PHPStan level 8 يمر
- [ ] Laravel Pint يمر
- [ ] Migrations + rollback نظيف
- [ ] Factories + Seeders للـ Module

### Frontend (🟣)
- [ ] جميع الصفحات تعمل على Chrome + Safari + Firefox
- [ ] RTL يعمل بشكل صحيح للعربي
- [ ] Dark mode يعمل لكل الصفحات الجديدة
- [ ] جميع الـ forms تستخدم React Hook Form + Zod
- [ ] Loading + Error + Empty states موجودة لكل query
- [ ] Optimistic updates للإجراءات السريعة
- [ ] Accessibility: keyboard nav + aria labels + focus states
- [ ] Lighthouse score > 85 (mobile)
- [ ] لا errors في console
- [ ] لا hydration mismatches
- [ ] i18n strings في `i18n/ar.json` + `i18n/en.json`

### Contract (🟡)
- [ ] كل endpoint موثق في `openapi/v1.yaml`
- [ ] Examples لكل response (success + error)
- [ ] Error responses باستخدام `components/responses/Error` الموحّد
- [ ] Prism mock يرد على كل endpoint بنجاح
- [ ] Spec diff موثق في commit message

### Integration (🟢)
- [ ] Frontend بتطلب من actual backend (مش mock) بنجاح
- [ ] E2E test لـ 1 flow على الأقل
- [ ] أي mismatch بين spec و implementation معالج

---

# Milestone 1 — Backend Foundation (أسبوعين)

> الأساس. كل ما بعدها يعتمد على هذه الـ sprints.

---

## Sprint 0 — Infrastructure & Foundation (1 أسبوع)

**التاريخ:** 2026-05-20 → 2026-05-26
**Goal:** بيئة جاهزة قبل أي feature. كل verification items في `PLAN.md` تمر.
**ملاحظة:** Sprint 0 ما فيه user stories بالمعنى التقليدي — كله infrastructure.

### المهام (per Day)

#### Day 1 — Repo & Workspace Setup
- [x] `BE-0.1` 🔵 إنشاء `qbazaar-api` repo + git init + README + .gitignore ✅ commit `b44eef8`
- [x] `FE-0.1` 🟣 إنشاء `qbazaar-web` repo + git init + README + .gitignore ✅ commit `91bce3f`
- [x] `CT-0.1` 🟡 إنشاء `qbazaar-contracts` repo + git init + README ✅ commit `61a4d15`
- [x] `CT-0.2` 🟡 نقل ROADMAP.md و MILESTONES.md و PLAN.md إلى `qbazaar-contracts/` ✅ commit `2d1703d`
- [x] `CT-0.3` 🟡 إنشاء `openapi/v1.yaml` skeleton + `error-codes.md` + `events/` folder ✅ commit `898644d`
- [ ] `INT-0.1` 🟢 GitHub repos creation (private) + push initial commits — *مؤجل لآخر Sprint 0*

#### Day 2 — Laravel Bootstrap
- [x] `BE-0.2` 🔵 `composer create-project laravel/laravel . "^12.0"` (Laravel 13 لسا ما طلع) ✅ commit `5a6333e`
- [x] `BE-0.3` 🔵 تنصيب Laravel packages: Sanctum, Scout, Reverb, Horizon, Pulse ✅ commit `6c2623f`
- [x] `BE-0.4` 🔵 تنصيب Spatie ecosystem: Permission, MediaLibrary, Activitylog, Translatable, QueryBuilder, Data ✅ commit `48ab412`
- [x] `BE-0.5` 🔵 تنصيب Filament v4 + Scribe + Meilisearch SDK + Intervention Image ✅ commit `4d9675b`
- [x] `BE-0.6` 🔵 تنصيب Twilio SDK + FCM notification channel ✅ commit `dc465b7`
- [x] `BE-0.7` 🔵 Dev deps: Pest, Pest plugin, Telescope, Larastan (Pint جاء مع Laravel skeleton) ✅ commit `a53a7f4`
- [x] `BE-0.8` 🔵 `php artisan vendor:publish` لكل الـ packages المطلوبة ✅ commit `69ba307`
- [x] `BE-0.9` 🔵 install commands: Telescope + Horizon + Reverb + Pest + Filament + Scribe ✅ commit `68a4bff`

#### Day 3 — Local Services ✅
- [x] `BE-0.10` 🔵 إنشاء DB `qbazaar` على Laragon MySQL ✅ commit `ab83557`
- [x] `BE-0.11` 🔵 Redis via Laragon's bundled redis-server + Predis client (no Memurai needed) ✅ commit `941baed`
- [x] `BE-0.12` 🔵 Meilisearch 1.44.0 binary + `start.bat` + DEV-SETUP.md ✅ commit `ab83557`
- [x] `BE-0.13` 🔵 ضبط `.env` كامل (DB + Redis + Meili + Reverb + Mail) ✅ commit `ec25971`
- [x] `BE-0.14` 🔵 إنشاء `.env.example` بدون secrets ✅ commit `ec25971`
- [x] `php artisan migrate` — 9 migrations applied (users, cache, jobs, sanctum, media, permission, activitylog, pulse, telescope)

#### Day 4 — Project Structure
- [x] `BE-0.15` 🔵 `config/qbazaar.php` (constants: max_images, ad_lifetime, otp_ttl, etc.) ✅ commit `b7b319c`
- [x] `BE-0.16` 🔵 Enums: `AdStatus`, `UserStatus`, `AccountType`, `PriceType`, `Condition`, `Language`, `MessageType`, `OfferStatus`, `ReportTarget` ✅ commit `f54e059`
- [x] `BE-0.17` 🔵 `app/Exceptions/ErrorCode.php` enum (48 codes — AUTH_001 … TICKET_001) ✅ commit `7e5b0be`
- [x] `BE-0.18` 🔵 Middleware: `LocaleMiddleware`, `ApiResponseWrapper`, `TrackClient` ✅ commit `7b12c57`
- [x] `BE-0.19` 🔵 Global Exception Handler في `bootstrap/app.php` (JSON موحدة) ✅ commit `1ed6551`
- [x] `BE-0.20` 🔵 `routes/api_v1.php` + load بـ prefix `/api/v1` + 6 rate limiter tiers ✅ commit `1ed6551`
- [x] `BE-0.21` 🔵 Health checks `/up` + `/api/v1/health` يردوا 200 ✅ commit `1ed6551`

#### Day 5 — Tooling
- [x] `BE-0.22` 🔵 `pint.json` بإعدادات Laravel preset + auto-fixed 48 files ✅ commit `0ea4426`
- [x] `BE-0.23` 🔵 `phpstan.neon` level 8 + Larastan — 0 errors ✅ commit `c6f83af`
- [x] `BE-0.24` 🔵 Pest tests/Feature/HealthEndpointTest.php (Pest CLI output suppressed under this shell) ✅ commit `c6f83af`
- [x] `BE-0.25` 🔵 Scribe generate → `/docs` يرد بـ HTML ✅ commit (current)
- [x] `BE-0.26` 🔵 Rate Limiters في `bootstrap/app.php` (auth, otp, search, publish, messages, api) ✅ ضمن commit `1ed6551`
- [x] `BE-0.27` 🔵 Sentry SDK 4.25 installed (DSN فاضي) ✅ commit `2c8591c`
- [x] `BE-0.28` 🔵 GitHub Actions CI workflows (api Pint+PHPStan+Pest + contracts Redocly+Prism) ✅ commits `2c8591c` (api) + `4732efe` (contracts)

#### Day 6 — Next.js + Design System
- [ ] `FE-0.2` 🟣 `create-next-app` مع TypeScript + Tailwind + App Router
- [ ] `FE-0.3` 🟣 تنصيب packages: TanStack Query, Zustand, axios, next-intl, RHF + Zod, Echo, Pusher, nuqs, Lucide, Sharp, next-themes, Embla
- [ ] `FE-0.4` 🟣 `shadcn@latest init` + إضافة primitives (button, input, card, form, sheet, dialog, dropdown-menu, badge, avatar, tabs, textarea, select, switch, label)
- [ ] `FE-0.5` 🟣 نقل brand assets من `DOCS/uploads/` → `public/brand/`
- [ ] `FE-0.6` 🟣 ضبط fonts في `app/layout.tsx`: DM Sans + Instrument Serif + Cairo
- [ ] `FE-0.7` 🟣 `app/globals.css`: CSS variables للـ light + dark modes (Coral palette)
- [ ] `FE-0.8` 🟣 `tailwind.config.ts` بالـ Bazzar tokens (coral/terracotta/cream/ink/sage)
- [ ] `FE-0.9` 🟣 `app/[locale]/layout.tsx` RTL aware + ThemeProvider
- [ ] `FE-0.10` 🟣 `middleware.ts` (next-intl i18n routing `/ar/*` `/en/*`)
- [ ] `FE-0.11` 🟣 `i18n/ar.json` + `i18n/en.json` (فاضيات `{}`)
- [ ] `FE-0.12` 🟣 `lib/api/client.ts` axios instance (interceptors فاضيين)
- [ ] `FE-0.13` 🟣 `components/ui/logo.tsx` + `components/theme-toggle.tsx`
- [ ] `FE-0.14` 🟣 Home placeholder بـ Instrument Serif italic للتحقق من الـ theme

#### Day 7 — Mock + Workflow + Sprint 1 Planning
- [ ] `CT-0.4` 🟡 `npm init` في contracts + تنصيب `@stoplight/prism-cli`
- [ ] `CT-0.5` 🟡 OpenAPI skeleton: info + servers + base schemas (User, Ad, Category, Error)
- [ ] `CT-0.6` 🟡 Auth endpoints في v1.yaml مع examples
- [ ] `CT-0.7` 🟡 Components/responses/Error موحّد
- [ ] `FE-0.15` 🟣 `.env.local`: `NEXT_PUBLIC_API_URL=http://localhost:4010` (Prism)
- [ ] `INT-0.2` 🟢 GitHub Project + 13 Milestones + Labels (track:backend/frontend/contract, area:*, type:*, priority:*)
- [ ] `INT-0.3` 🟢 Issues لـ Sprint 1 منشأة في الـ repos
- [ ] `INT-0.4` 🟢 Sprint 0 Retro في `ROADMAP.md`

---

## Sprint 1 — Auth (3 أيام)

**التاريخ:** 2026-05-27 → 2026-05-29
**Goal:** المستخدم يقدر يسجل، يدخل، يطلع، يستعيد كلمة المرور، ويتحقق من الهاتف.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-1.1 | كزائر، أقدر أسجل بإيميل + هاتف قطري + كلمة مرور | P0 |
| US-1.2 | كمستخدم جديد، أتلقى OTP عبر SMS على رقمي القطري | P0 |
| US-1.3 | كمستخدم، أقدر أتحقق من هاتفي بإدخال OTP | P0 |
| US-1.4 | كمستخدم، أقدر أعيد إرسال OTP بعد 60 ثانية | P0 |
| US-1.5 | كمستخدم مسجل، أقدر أسجل دخول بالإيميل أو الهاتف + كلمة المرور | P0 |
| US-1.6 | كمستخدم، أقدر أسجل خروج من device واحد | P0 |
| US-1.7 | كمستخدم، أقدر أستعيد كلمة المرور عبر إيميل | P0 |
| US-1.8 | كمستخدم، أقدر أتحقق من إيميلي عبر رابط | P0 |
| US-1.9 | كمستخدم، access_token بيتجدد تلقائياً بدون ما أعيد login | P0 |
| US-1.10 | كمستخدم معلّق (suspended)، يُرفض الـ login | P0 |
| US-1.11 | كمستخدم، password يحتاج 8+ chars مع uppercase/lowercase/number/symbol | P0 |
| US-1.12 | كمستخدم، أختار اللغة عند التسجيل (ar/en) | P1 |

### 🔁 Critical Flows

- **F-1.1 (Registration):** Land on /register → fill form → submit → receive OTP via SMS → enter OTP → phone verified → check email for verification link → click link → email verified → redirect to /account
- **F-1.2 (Login):** Land on /login → fill email/phone + password → submit → JWT issued → redirect to / or last visited
- **F-1.3 (Token refresh):** API call returns 401 → axios interceptor → calls /auth/refresh → retries original call with new token (مرة واحدة)
- **F-1.4 (Forgot Password):** Click "forgot password" → enter email → receive email with link → click link → enter new password → login
- **F-1.5 (Logout):** Click logout → token revoked on server → clear local state → redirect to /login

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint | Notes |
|----|------|----------|-------|
| BE-1.1 | RegisterController + RegisterRequest | `POST /auth/register` | Validate phone `^\+974[0-9]{8}$`, password strong, accepted_terms |
| BE-1.2 | LoginController + LoginRequest | `POST /auth/login` | Accept email أو phone كـ identifier |
| BE-1.3 | LogoutController | `POST /auth/logout` | Revoke current token |
| BE-1.4 | RefreshTokenController + custom Layer | `POST /auth/refresh` | Issue new access + rotated refresh token. Device fingerprint check |
| BE-1.5 | OtpController::send | `POST /auth/send-otp` | Twilio integration, 6 digits, 5min expiry, throttle 3/min by phone |
| BE-1.6 | OtpController::verify | `POST /auth/verify-otp` | Max 3 attempts, mark phone_verified |
| BE-1.7 | OtpController::resend | `POST /auth/resend-otp` | Cooldown 60s, max 5/hour |
| BE-1.8 | PasswordResetController::forgot | `POST /auth/forgot-password` | Send email with token |
| BE-1.9 | PasswordResetController::reset | `POST /auth/reset-password` | Validate token, set new password |
| BE-1.10 | EmailVerificationController | `POST /auth/send-email-verification` | Auth required |
| BE-1.11 | Email verification link handler | `GET /auth/verify-email/{id}/{hash}` | Spatie signed URL |
| BE-1.12 | OtpService | — | Generate, hash, verify, expire |
| BE-1.13 | RefreshTokenService | — | Mutex/lock للـ race condition |
| BE-1.14 | UserObserver | — | Activity log on status change, password change, email change |
| BE-1.15 | EnsureUserIsActive middleware | — | Block suspended users on protected routes |
| BE-1.16 | EnsurePhoneVerified middleware | — | Required for certain endpoints |
| BE-1.17 | OtpNotification (Twilio) | — | Locale-aware |
| BE-1.18 | WelcomeNotification (mail) | — | |
| BE-1.19 | PasswordResetNotification | — | |
| BE-1.20 | SecurityAlertNotification | — | New device login |
| BE-1.21 | Migration: `users` table updates | — | Add language, phone_verified, last_login_at |
| BE-1.22 | Migration: `otp_codes` table | — | phone, code_hash, attempts, expires_at, used_at |
| BE-1.23 | Migration: `refresh_tokens` table | — | user_id, token_hash, device_fingerprint, expires_at |
| BE-1.24 | UserFactory + OtpCodeFactory | — | |
| BE-1.25 | Pest Feature Tests: 8 endpoints × happy path + edge cases | — | Min 30 tests |
| BE-1.26 | Scribe annotations لكل endpoint | — | Examples + Form Request docs |
| BE-1.27 | Localization: `lang/{ar,en}/auth.php` + `validation.php` | — | كل error messages |

### 🟣 Frontend Tasks (`qbazaar-web`)

| ID | Task | Path / Component |
|----|------|------------------|
| FE-1.1 | Login page | `app/[locale]/(auth)/login/page.tsx` |
| FE-1.2 | Register page | `app/[locale]/(auth)/register/page.tsx` |
| FE-1.3 | OTP verification page | `app/[locale]/(auth)/verify-otp/page.tsx` |
| FE-1.4 | Forgot password page | `app/[locale]/(auth)/forgot-password/page.tsx` |
| FE-1.5 | Reset password page | `app/[locale]/(auth)/reset-password/page.tsx` |
| FE-1.6 | Email verification page | `app/[locale]/verify-email/page.tsx` |
| FE-1.7 | Auth layout (split with hero image) | `app/[locale]/(auth)/layout.tsx` — مرجع `auth.jsx` mockup |
| FE-1.8 | LoginForm component | `components/auth/LoginForm.tsx` |
| FE-1.9 | RegisterForm component | `components/auth/RegisterForm.tsx` |
| FE-1.10 | OtpInput (4 boxes auto-advance) | `components/auth/OtpInput.tsx` |
| FE-1.11 | PasswordStrengthIndicator | `components/auth/PasswordStrengthIndicator.tsx` |
| FE-1.12 | PhoneInput (Qatar prefix +974) | `components/auth/PhoneInput.tsx` |
| FE-1.13 | Auth Zustand store | `store/auth.ts` — user, accessToken (memory), isLoading |
| FE-1.14 | API: auth functions | `lib/api/auth.ts` |
| FE-1.15 | axios interceptors | `lib/api/interceptors.ts` — Bearer header + 401 refresh mutex |
| FE-1.16 | Refresh token cookie route | `app/api/auth/refresh/route.ts` (proxy للـ Backend) |
| FE-1.17 | `useAuth()` hook | `hooks/useAuth.ts` |
| FE-1.18 | `useRequireAuth()` hook | `hooks/useRequireAuth.ts` — redirect to /login |
| FE-1.19 | Protected route HOC | `components/auth/RequireAuth.tsx` |
| FE-1.20 | Zod schemas matching backend validation | `lib/validation/auth.ts` |
| FE-1.21 | i18n keys للـ auth | `i18n/ar.json` + `en.json` |
| FE-1.22 | Toast notifications للـ success/error | استخدم shadcn `sonner` |

### 🟡 Contract Tasks (`qbazaar-contracts`)

| ID | Task |
|----|------|
| CT-1.1 | Auth endpoints paths في `openapi/v1.yaml` (10 endpoints) |
| CT-1.2 | Schemas: `RegisterRequest`, `LoginRequest`, `AuthResponse`, `RefreshRequest`, `OtpSendRequest`, `OtpVerifyRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `User`, `Token` |
| CT-1.3 | Error codes موثقة في `error-codes.md` (AUTH_001 → AUTH_010) |
| CT-1.4 | Examples لكل response (success + error variations) |
| CT-1.5 | Prism mock verified — كل endpoint يرد بـ JSON صحيح |

---

## Sprint 2 — Users (3 أيام)

**التاريخ:** 2026-05-30 → 2026-06-01
**Goal:** المستخدم يقدر يدير حسابه، يحدث profile، يتحكم في الخصوصية، يحظر مستخدمين.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-2.1 | كمستخدم، أشوف dashboard حسابي (ads count, messages, notifications) | P0 |
| US-2.2 | كمستخدم، أعدّل profile (name, bio, location, language) | P0 |
| US-2.3 | كمستخدم، أرفع/أغيّر avatar | P0 |
| US-2.4 | كمستخدم، أغيّر كلمة المرور (مع كلمة المرور القديمة) | P0 |
| US-2.5 | كمستخدم، أشوف الـ sessions النشطة (الأجهزة) | P0 |
| US-2.6 | كمستخدم، أسجّل خروج من device معين | P0 |
| US-2.7 | كمستخدم، أتحكم في الـ privacy (إظهار رقم الهاتف، السماح بالمحادثات) | P0 |
| US-2.8 | كمستخدم، أحظر مستخدم آخر (لا رسائل، لا يشوف إعلاناتي) | P0 |
| US-2.9 | كمستخدم، أشوف قائمة المحظورين وأرفع الحظر | P0 |
| US-2.10 | كزائر/مستخدم، أشوف public profile لبائع | P0 |
| US-2.11 | كزائر/مستخدم، أشوف إعلانات بائع معين | P0 |
| US-2.12 | كمستخدم، أطلب data export (GDPR-like) | P1 |
| US-2.13 | كمستخدم، أعطل حسابي مؤقتاً | P1 |
| US-2.14 | كمستخدم، أطلب حذف حسابي (مع grace period) | P1 |
| US-2.15 | كمستخدم business، أشوف verification status | P1 |

### 🔁 Critical Flows

- **F-2.1 (Edit Profile):** Account → Profile → edit → save → success toast
- **F-2.2 (Upload Avatar):** Click avatar → file picker → crop → upload → optimistic display + actual when conversions ready
- **F-2.3 (Block User):** Open user profile / conversation → "Block" → confirm → user added to blocked, conversation hidden
- **F-2.4 (View Public Profile):** Click seller name on ad → public profile page → see their other ads
- **F-2.5 (Delete Account):** Account → Data → "Delete account" → enter password → grace period notice → email confirmation → after 30 days deleted

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint |
|----|------|----------|
| BE-2.1 | AccountController::summary | `GET /account/summary` |
| BE-2.2 | AccountController::show + update | `GET, PUT /account/profile` |
| BE-2.3 | AccountController::updatePassword | `PUT /account/password` |
| BE-2.4 | SessionsController::index + destroy | `GET, DELETE /account/sessions[/{id}]` |
| BE-2.5 | VerificationController::status | `GET /account/verification-status` |
| BE-2.6 | PrivacyController::show + update | `GET, PUT /account/privacy-settings` |
| BE-2.7 | PrivacyController::exportRequest | `POST /account/data-export-request` |
| BE-2.8 | AccountController::deactivate | `POST /account/deactivate` |
| BE-2.9 | AccountController::deleteRequest | `DELETE /account/delete-request` |
| BE-2.10 | BlockedUsersController::index | `GET /account/blocked-users` |
| BE-2.11 | BlockController::block + unblock | `POST, DELETE /users/{user}/block` |
| BE-2.12 | AvatarUploadController | `POST /uploads/avatar` (MediaLibrary collection 'avatar', conversion 'thumb') |
| BE-2.13 | PublicProfileController | `GET /users/{user}/public-profile` |
| BE-2.14 | UserAdsController | `GET /users/{user}/ads` (filter active only) |
| BE-2.15 | AccountPolicy + BlockPolicy | — |
| BE-2.16 | ExportUserDataJob | — (queues, generates ZIP, emails link) |
| BE-2.17 | DeleteAccountJob | — (30-day grace period via scheduled job) |
| BE-2.18 | DeactivateAccountAction | — |
| BE-2.19 | Migrations: `user_blocks` pivot, `privacy_settings` JSON column on users, `data_export_requests` | — |
| BE-2.20 | UserObserver updates: log email/phone changes, deactivation | — |
| BE-2.21 | UserResource: respects privacy settings (hide phone if disabled) | — |
| BE-2.22 | Pest Feature Tests: ~20 tests | — |
| BE-2.23 | Scribe + Localization | — |

### 🟣 Frontend Tasks (`qbazaar-web`)

| ID | Task | Path / Component |
|----|------|------------------|
| FE-2.1 | Account dashboard page | `app/[locale]/account/page.tsx` |
| FE-2.2 | Edit profile page | `app/[locale]/account/profile/page.tsx` |
| FE-2.3 | Security page (password change) | `app/[locale]/account/security/page.tsx` |
| FE-2.4 | Sessions page | `app/[locale]/account/sessions/page.tsx` |
| FE-2.5 | Privacy settings page | `app/[locale]/account/privacy/page.tsx` |
| FE-2.6 | Blocked users page | `app/[locale]/account/blocked-users/page.tsx` |
| FE-2.7 | Data & Account page | `app/[locale]/account/data/page.tsx` (export + delete) |
| FE-2.8 | Verification page | `app/[locale]/account/verification/page.tsx` |
| FE-2.9 | Public profile page | `app/[locale]/u/[id]/page.tsx` |
| FE-2.10 | User ads page | `app/[locale]/u/[id]/ads/page.tsx` |
| FE-2.11 | Account sidebar nav | `components/account/AccountSidebar.tsx` — مرجع `profile.jsx` mockup |
| FE-2.12 | ProfileForm | `components/account/ProfileForm.tsx` |
| FE-2.13 | AvatarUploader (with crop) | `components/account/AvatarUploader.tsx` |
| FE-2.14 | PasswordChangeForm | `components/account/PasswordChangeForm.tsx` |
| FE-2.15 | SessionsList | `components/account/SessionsList.tsx` |
| FE-2.16 | PrivacySettings | `components/account/PrivacySettings.tsx` |
| FE-2.17 | BlockedUsersList | `components/account/BlockedUsersList.tsx` |
| FE-2.18 | BlockUserButton (used in profile + chat) | `components/users/BlockUserButton.tsx` |
| FE-2.19 | PublicProfileHeader | `components/users/PublicProfileHeader.tsx` |
| FE-2.20 | Account API client | `lib/api/account.ts` |
| FE-2.21 | User Zustand store | `store/user.ts` |
| FE-2.22 | Image cropper integration | `react-easy-crop` package |

### 🟡 Contract Tasks (`qbazaar-contracts`)

| ID | Task |
|----|------|
| CT-2.1 | 15+ account endpoints في v1.yaml |
| CT-2.2 | Schemas: `UserProfile`, `UpdateProfileRequest`, `PrivacySettings`, `Session`, `DataExportRequest`, `PublicProfile` |
| CT-2.3 | Error codes: USER_001 → USER_005 |
| CT-2.4 | Examples + Prism verified |

---

## Sprint 3 — Categories & Locations (2 أيام)

**التاريخ:** 2026-06-02
**Goal:** البيانات المرجعية (الأقسام + المناطق القطرية) جاهزة ومُخزّنة بـ cache.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-3.1 | كزائر، أشوف الأقسام الرئيسية في الـ home | P0 |
| US-3.2 | كزائر، أشوف شجرة الأقسام الكاملة | P0 |
| US-3.3 | كزائر، أشوف الأقسام الفرعية لقسم معين | P0 |
| US-3.4 | كزائر، أشوف الفلاتر الخاصة بكل قسم (مثلاً للسيارات: year, transmission, fuel) | P0 |
| US-3.5 | كزائر، أشوف الحقول المخصصة المطلوبة عند نشر إعلان في القسم | P0 |
| US-3.6 | كزائر، أشوف عدد الإعلانات في كل قسم | P1 |
| US-3.7 | كمستخدم، أختار منطقة قطرية من dropdown (Doha, Lusail, Al Wakra, etc.) | P0 |

### 🔁 Critical Flows

- **F-3.1 (Browse Categories):** Home → click main category card → category page → see subcategories grid + filters
- **F-3.2 (Pick Location):** Post Ad / Search → Location dropdown → see Qatar regions hierarchical (city → district)

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint |
|----|------|----------|
| BE-3.1 | CategoryController::tree | `GET /categories/tree` (cached 1hr) |
| BE-3.2 | CategoryController::main | `GET /categories/main` |
| BE-3.3 | CategoryController::stats | `GET /categories/{slug}/stats` |
| BE-3.4 | CategoryController::filters | `GET /categories/{slug}/filters` |
| BE-3.5 | CategoryController::fields | `GET /categories/{slug}/fields` |
| BE-3.6 | LocationController::qatar | `GET /locations/qatar` (cached 24hr) |
| BE-3.7 | Category model (translatable, HasUlids, custom_fields JSON, custom_filters JSON) | — |
| BE-3.8 | Location model (translatable, hierarchical parent_id) | — |
| BE-3.9 | Migration: `categories` (id, parent_id, slug, name JSON, description JSON, icon, order, is_active, custom_fields, custom_filters) | — |
| BE-3.10 | Migration: `locations` (id, parent_id, slug, name JSON, type, lat, lng) | — |
| BE-3.11 | CategorySeeder (~50 categories: cars, real-estate, electronics, jobs, services, pets, etc.) | — |
| BE-3.12 | LocationSeeder: all Qatar regions (Doha, Lusail, Al Wakra, Al Khor, Al Rayyan, Umm Salal, etc. with districts) | — |
| BE-3.13 | Cache invalidation hooks (في Filament Resource بعدين) | — |
| BE-3.14 | CategoryResource (API resource) + LocationResource | — |
| BE-3.15 | Pest tests + Scribe annotations | — |

### 🟣 Frontend Tasks (`qbazaar-web`)

| ID | Task | Path / Component |
|----|------|------------------|
| FE-3.1 | Categories index page | `app/[locale]/categories/page.tsx` |
| FE-3.2 | Category page (placeholder until Sprint 5) | `app/[locale]/c/[slug]/page.tsx` |
| FE-3.3 | CategoryTree component | `components/categories/CategoryTree.tsx` |
| FE-3.4 | CategoryGrid (home tiles) | `components/categories/CategoryGrid.tsx` |
| FE-3.5 | CategoryFilters dynamic | `components/categories/CategoryFilters.tsx` |
| FE-3.6 | CategoryBreadcrumb | `components/categories/CategoryBreadcrumb.tsx` |
| FE-3.7 | LocationPicker (cascading dropdown) | `components/locations/LocationPicker.tsx` |
| FE-3.8 | Categories Zustand store (cached client-side) | `store/categories.ts` |
| FE-3.9 | Locations store | `store/locations.ts` |
| FE-3.10 | API: categories.ts, locations.ts | `lib/api/categories.ts`, `lib/api/locations.ts` |
| FE-3.11 | TanStack Query setup with staleTime 1hr للـ static data | — |

### 🟡 Contract Tasks (`qbazaar-contracts`)

| ID | Task |
|----|------|
| CT-3.1 | Category + Location endpoints (6 endpoints) |
| CT-3.2 | Schemas: `Category`, `CategoryNode` (recursive), `CategoryFilter`, `CategoryField`, `Location` |
| CT-3.3 | Examples لكل response |

---

# Milestone 2 — Marketplace Core (3 أسابيع)

> القلب — الإعلانات والبحث.

---

## Sprint 4 — Uploads (2 أيام)

**التاريخ:** 2026-06-03 → 2026-06-04
**Goal:** نظام رفع الصور جاهز (multi-size conversions, BlurHash, pHash dedup).

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-4.1 | كمستخدم، أرفع 1-10 صور للإعلان | P0 |
| US-4.2 | أشوف progress أثناء الرفع | P0 |
| US-4.3 | أحذف صورة من المرفوعات | P0 |
| US-4.4 | أعيد ترتيب الصور (drag & drop) | P0 |
| US-4.5 | أشوف BlurHash placeholder قبل تحميل الصورة | P0 |
| US-4.6 | لا أقدر أرفع صورة > 10MB | P0 |
| US-4.7 | لا أقدر أرفع صورة بصيغة غير image/jpeg|png|webp | P0 |
| US-4.8 | الصور تنضغط محلياً قبل الرفع (يوفر bandwidth) | P0 |

### 🔁 Critical Flows

- **F-4.1 (Upload):** Click upload → file picker (multi) → client-side compress → POST /uploads → progress → preview with BlurHash → conversions ready event → final image
- **F-4.2 (Reorder):** Drag image → drop in new position → optimistic update → API confirms

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint |
|----|------|----------|
| BE-4.1 | AdImageController::store | `POST /ads/{ad}/images` (multipart) |
| BE-4.2 | AdImageController::destroy | `DELETE /media/{media}` |
| BE-4.3 | AdImageController::reorder | `POST /ads/{ad}/images/reorder` |
| BE-4.4 | UploadImagesRequest (validation) | mimes:jpg,jpeg,png,webp · max:10240 |
| BE-4.5 | MediaResource (returns sizes object + BlurHash) | — |
| BE-4.6 | Ad model: `registerMediaCollections` + `registerMediaConversions` | thumbnail (200×200), medium (640), large (1024), original-webp (1920) |
| BE-4.7 | ProcessAdImagesJob (queued) | Spatie conversions + BlurHash + pHash |
| BE-4.8 | ImageHashService (perceptual hash via php-image-hash or similar) | للـ duplicate detection |
| BE-4.9 | BlurHashGeneratorService | — |
| BE-4.10 | Local disk config + Cloudflare R2 config (production deferred) | — |
| BE-4.11 | Magic byte validation (security) | Intervention Image |
| BE-4.12 | Storage policy: signed URLs لـ original size لو needed | — |
| BE-4.13 | Pest tests: upload, delete, reorder, validation errors | — |

### 🟣 Frontend Tasks (`qbazaar-web`)

| ID | Task | Path / Component |
|----|------|------------------|
| FE-4.1 | ImageDropzone (drag & drop + click) | `components/upload/ImageDropzone.tsx` |
| FE-4.2 | UploadProgressBar | `components/upload/UploadProgress.tsx` |
| FE-4.3 | ImagePreviewList (reorderable via @dnd-kit) | `components/upload/ImagePreviewList.tsx` |
| FE-4.4 | BlurHashImage wrapper | `components/upload/BlurHashImage.tsx` |
| FE-4.5 | Client-side compression (browser-image-compression lib) | `lib/upload/compress.ts` |
| FE-4.6 | useImageUpload hook | `hooks/useImageUpload.ts` |
| FE-4.7 | API: uploads.ts | `lib/api/uploads.ts` |
| FE-4.8 | shadcn/ui upload integration | — |

### 🟡 Contract Tasks (`qbazaar-contracts`)

| ID | Task |
|----|------|
| CT-4.1 | Upload endpoints (3 endpoints) |
| CT-4.2 | Schemas: `Media`, `MediaSizes`, `UploadImagesRequest`, `ReorderImagesRequest` |
| CT-4.3 | Error codes: UPLOAD_001 (size), UPLOAD_002 (mime), UPLOAD_003 (max images) |

---

## Sprint 5 — Ads (أسبوعين)

**التاريخ:** 2026-06-05 → 2026-06-18
**Goal:** أهم Sprint — نظام الإعلانات الكامل: draft → publish → moderation → live → renew/sold/expired.

### 📖 User Stories (17 stories)

| ID | Story | Priority |
|----|-------|----------|
| US-5.1 | كمستخدم، أبدأ إنشاء إعلان (auto-save كـ draft) | P0 |
| US-5.2 | كمستخدم، أكمل multi-step flow (Category → Details → Photos → Review) | P0 |
| US-5.3 | كمستخدم، أشوف custom fields حسب القسم المختار (مثلاً year/mileage للسيارات) | P0 |
| US-5.4 | كمستخدم، أحدد price + price_type (fixed/negotiable/free/contact) | P0 |
| US-5.5 | كمستخدم، أحدد condition (new/like_new/good/fair) | P0 |
| US-5.6 | كمستخدم، أحدد location | P0 |
| US-5.7 | كمستخدم، أرفع 1-10 صور (من Sprint 4) | P0 |
| US-5.8 | كمستخدم، أحدد contact preferences (show_phone, allow_chat) | P0 |
| US-5.9 | كمستخدم، preview الإعلان قبل النشر | P0 |
| US-5.10 | كمستخدم، أنشر الإعلان (يدخل auto-moderation) | P0 |
| US-5.11 | كمستخدم، إذا approved → الإعلان live لـ 30 يوم | P0 |
| US-5.12 | كمستخدم، إذا rejected → أشوف السبب وأعدّل وأعيد النشر | P0 |
| US-5.13 | كمستخدم، أعدّل إعلان موجود | P0 |
| US-5.14 | كمستخدم، أحذف إعلاني | P0 |
| US-5.15 | كمستخدم، أعلّم إعلان كـ "sold" | P0 |
| US-5.16 | كمستخدم، أجدد إعلان منتهي الصلاحية | P0 |
| US-5.17 | كزائر/مستخدم، أشوف تفاصيل إعلان (gallery, description, contact, similar ads) | P0 |
| US-5.18 | كزائر/مستخدم، أشوف "latest" + "featured" ads على home | P0 |
| US-5.19 | views بتُسجَّل (throttled per user/IP) | P0 |
| US-5.20 | كصاحب الإعلان، أشوف views/contacts count | P1 |

### 🔁 Critical Flows

- **F-5.1 (Post Ad):** Click "Post Ad" → Step 1: pick category → Step 2: fill title + description + price + condition + custom fields + location → Step 3: upload photos → Step 4: review → Publish → Auto-moderation → Pending/Active → Notification + email
- **F-5.2 (View Ad):** Click ad card → SSR ad page → image gallery (swipe) → seller info → contact (chat/phone if allowed) → similar ads → save to favorites
- **F-5.3 (Edit & Resubmit):** My Ads → Click rejected ad → see reason → Edit → status → pending → re-moderation
- **F-5.4 (Renew):** Expired ad → "Renew" button → confirm → expires_at + 30 days → status = pending → re-moderation
- **F-5.5 (Auto-moderation):** Publish → check banned words / phone in description / external links / image dedup → if clean: active; else: pending (manual review)

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint |
|----|------|----------|
| BE-5.1 | DraftController (store/update/show/preview/index) | `POST,PUT,GET /ads/draft` |
| BE-5.2 | DraftController::reorderImages | `PUT /ads/draft/{ad}/images` |
| BE-5.3 | PublishAdController | `POST /ads/{ad}/publish` (idempotent) |
| BE-5.4 | AdController::update + destroy | `PUT,DELETE /ads/{ad}` |
| BE-5.5 | AdController::markSold | `POST /ads/{ad}/mark-sold` |
| BE-5.6 | AdController::renew | `POST /ads/{ad}/renew` |
| BE-5.7 | MyAdsController::index | `GET /account/ads` (filter by status) |
| BE-5.8 | DraftController::index | `GET /account/drafts` |
| BE-5.9 | AdController::show (public) | `GET /ads/{ad}` |
| BE-5.10 | AdController::trackView | `POST /ads/{ad}/view` (throttle 60/min) |
| BE-5.11 | AdController::similar | `GET /ads/{ad}/similar` |
| BE-5.12 | AdController::latest | `GET /ads/latest` (cursor pagination) |
| BE-5.13 | AdController::featured | `GET /ads/featured` |
| BE-5.14 | CreateAdRequest + UpdateAdRequest (with dynamic custom_fields validation) | — |
| BE-5.15 | Ad model (HasUlids, HasMedia, LogsActivity, Searchable later) | — |
| BE-5.16 | AdPolicy (view/update/delete based on ownership) | — |
| BE-5.17 | AdStatus enum + canTransitionTo state machine | — |
| BE-5.18 | PublishAdAction (DB transaction, moderation, event) | — |
| BE-5.19 | ModerateAdAction (banned words, phone regex, external link regex, pHash) | — |
| BE-5.20 | RenewAdAction | — |
| BE-5.21 | MarkSoldAction | — |
| BE-5.22 | ModerationRulesService | — |
| BE-5.23 | AdExpirationService + ExpireOldAdsJob (scheduled daily) | — |
| BE-5.24 | AdObserver (logs status changes, price changes, title changes) | — |
| BE-5.25 | Events: AdPublished, AdApproved, AdRejected, AdExpired, AdRenewed | — |
| BE-5.26 | Listeners: IndexAdInSearch (Meilisearch), SendAdApprovalNotification | — |
| BE-5.27 | Notifications: AdApproved, AdRejected, AdExpiringSoon, AdExpired | — |
| BE-5.28 | Migration: `ads` table (full schema from doc) + indexes | — |
| BE-5.29 | Migration: `ad_views` table (for analytics) | — |
| BE-5.30 | Migration: `moderation_rules` table (banned_words seedable) | — |
| BE-5.31 | AdFactory + ModerationRuleSeeder | — |
| BE-5.32 | AdResource + AdCollection | — |
| BE-5.33 | Idempotency middleware لـ publish endpoint | — |
| BE-5.34 | Pest tests: ~40 tests (auto-mod edge cases, state machine transitions) | — |
| BE-5.35 | Scribe + Localization | — |

### 🟣 Frontend Tasks (`qbazaar-web`)

| ID | Task | Path / Component |
|----|------|------------------|
| FE-5.1 | Home page (Hero variant) | `app/[locale]/page.tsx` — مرجع `home.jsx` (A) |
| FE-5.2 | Ad Detail page (SSR + ISR 30s) | `app/[locale]/ad/[id]/[slug]/page.tsx` — مرجع `detail.jsx` |
| FE-5.3 | Post Ad layout | `app/[locale]/post-ad/layout.tsx` — مرجع `post.jsx` |
| FE-5.4 | Post Ad: Category step | `app/[locale]/post-ad/page.tsx` |
| FE-5.5 | Post Ad: Details step | `app/[locale]/post-ad/details/page.tsx` |
| FE-5.6 | Post Ad: Photos step | `app/[locale]/post-ad/photos/page.tsx` |
| FE-5.7 | Post Ad: Review step | `app/[locale]/post-ad/review/page.tsx` |
| FE-5.8 | My Ads page | `app/[locale]/account/my-ads/page.tsx` |
| FE-5.9 | My Drafts page | `app/[locale]/account/drafts/page.tsx` |
| FE-5.10 | Edit Ad page | `app/[locale]/account/my-ads/[id]/edit/page.tsx` |
| FE-5.11 | HomeHero (CTA + search) | `components/home/Hero.tsx` |
| FE-5.12 | HomeCategoryGrid | `components/home/CategoryGrid.tsx` |
| FE-5.13 | HomeLatestAds + HomeFeaturedAds | `components/home/*.tsx` |
| FE-5.14 | AdCard | `components/ads/AdCard.tsx` |
| FE-5.15 | AdGallery (Embla carousel + photo-view zoom) | `components/ads/AdGallery.tsx` |
| FE-5.16 | AdMeta (price, location, condition, posted_at) | `components/ads/AdMeta.tsx` |
| FE-5.17 | AdDescription (expandable) | `components/ads/AdDescription.tsx` |
| FE-5.18 | AdContactBox (chat/phone CTAs) | `components/ads/AdContactBox.tsx` |
| FE-5.19 | AdSellerInfo (avatar, name, joined) | `components/ads/AdSellerInfo.tsx` |
| FE-5.20 | AdSimilar | `components/ads/AdSimilar.tsx` |
| FE-5.21 | AdActions (edit/delete/mark sold/renew/share) | `components/ads/AdActions.tsx` |
| FE-5.22 | Post Ad Stepper | `components/post-ad/Stepper.tsx` |
| FE-5.23 | DynamicFields renderer (based on category.custom_fields) | `components/post-ad/DynamicFields.tsx` |
| FE-5.24 | PriceInput (with type selector) | `components/post-ad/PriceInput.tsx` |
| FE-5.25 | ContactPreferences component | `components/post-ad/ContactPreferences.tsx` |
| FE-5.26 | AdPreview (used in review step) | `components/post-ad/AdPreview.tsx` |
| FE-5.27 | Post Ad Zustand store (draft persistence in localStorage too) | `store/postAd.ts` |
| FE-5.28 | API: ads.ts | `lib/api/ads.ts` |
| FE-5.29 | useAd, useAds, useMyAds hooks (TanStack Query) | `hooks/ads/*.ts` |
| FE-5.30 | View tracker (debounced POST /ads/{id}/view) | `lib/analytics/adView.ts` |
| FE-5.31 | SEO: OpenGraph + JSON-LD Product schema on ad page | `app/[locale]/ad/[id]/[slug]/page.tsx` |
| FE-5.32 | Sitemap generator (consumes Backend /sitemap/ads) | `app/sitemap.ts` |

### 🟡 Contract Tasks (`qbazaar-contracts`)

| ID | Task |
|----|------|
| CT-5.1 | 14+ Ad endpoints في v1.yaml |
| CT-5.2 | Schemas: `Ad`, `AdDetail`, `AdSummary`, `AdDraft`, `CreateAdRequest`, `UpdateAdRequest`, `Money`, `PriceType`, `Condition`, `AdStatus`, `ContactPreferences`, `CustomField` |
| CT-5.3 | Error codes: AD_001 (not found) → AD_010 |
| CT-5.4 | Events spec: `ad.published`, `ad.approved`, `ad.rejected` |

---

## Sprint 6 — Search (3 أيام)

**التاريخ:** 2026-06-19 → 2026-06-21
**Goal:** Meilisearch مدمج، البحث سريع ودقيق (عربي + إنجليزي)، Saved Searches بـ alerts.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-6.1 | كزائر، أكتب keyword وأبحث في الإعلانات | P0 |
| US-6.2 | كزائر، أصفّي بقسم، موقع، نطاق سعر، condition، seller_type | P0 |
| US-6.3 | كزائر، أرتّب النتائج (newest/price_asc/price_desc/relevance) | P0 |
| US-6.4 | كزائر، أشوف suggestions أثناء الكتابة | P0 |
| US-6.5 | كزائر، أشوف recent searches (localStorage) | P1 |
| US-6.6 | كمستخدم، أحفظ بحث | P0 |
| US-6.7 | كمستخدم، أحصل على إشعار لما إعلان جديد يطابق saved search | P0 |
| US-6.8 | كزائر، أشوف عدد النتائج | P0 |
| US-6.9 | البحث بالعربي يطابق synonyms (سيارة ↔ car) | P0 |
| US-6.10 | الفلاتر تنعكس في URL (shareable + bookmarkable) | P0 |

### 🔁 Critical Flows

- **F-6.1 (Search):** Type "بيت في الخور" → see suggestions → enter → results page with filters → adjust filters → URL updates → results updated
- **F-6.2 (Saved Search):** Apply filters → Save Search → Name it → "Notify me of new matches" toggle → save

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint |
|----|------|----------|
| BE-6.1 | SearchController::__invoke | `GET /search` |
| BE-6.2 | SearchSuggestionsController | `GET /search/suggestions` |
| BE-6.3 | SavedSearchController (CRUD) | `GET, POST, PUT, DELETE /saved-searches` |
| BE-6.4 | Ad model: `Searchable` trait + `toSearchableArray` | — |
| BE-6.5 | Meilisearch index settings (searchable/filterable/sortable attrs + Arabic synonyms) | — |
| BE-6.6 | Synonyms seed (Arabic dictionary: سيارة, شقة, موبايل, etc.) | — |
| BE-6.7 | SearchService (handles query building, filters) | — |
| BE-6.8 | SearchRequest (validation: q, category_id, location_id, price range, condition, sort) | — |
| BE-6.9 | CheckSavedSearchesJob (scheduled hourly) — يجلب new ads + ينبه | — |
| BE-6.10 | SavedSearchMatchNotification (database + push) | — |
| BE-6.11 | Migration: `saved_searches` table (user_id, name, query, filters JSON, alerts_enabled, last_checked_at) | — |
| BE-6.12 | Reindex artisan command + `IndexAdInSearch` listener | — |
| BE-6.13 | Pest tests: search filters, saved search alerts | — |

### 🟣 Frontend Tasks (`qbazaar-web`)

| ID | Task | Path / Component |
|----|------|------------------|
| FE-6.1 | Search page | `app/[locale]/search/page.tsx` — مرجع `search.jsx` |
| FE-6.2 | SearchBar (with suggestions dropdown) | `components/search/SearchBar.tsx` |
| FE-6.3 | SearchFilters (sidebar on desktop, bottom sheet on mobile) | `components/search/SearchFilters.tsx` |
| FE-6.4 | FilterChips (active filters preview) | `components/search/FilterChips.tsx` |
| FE-6.5 | SortDropdown | `components/search/SortDropdown.tsx` |
| FE-6.6 | SearchResults (grid + cursor pagination + infinite scroll) | `components/search/SearchResults.tsx` |
| FE-6.7 | SearchEmptyState | `components/search/SearchEmptyState.tsx` |
| FE-6.8 | SavedSearchesList | `components/search/SavedSearchesList.tsx` |
| FE-6.9 | SaveSearchModal | `components/search/SaveSearchModal.tsx` |
| FE-6.10 | useSearch hook (URL params via nuqs) | `hooks/useSearch.ts` |
| FE-6.11 | useSearchSuggestions (debounced) | `hooks/useSearchSuggestions.ts` |
| FE-6.12 | Recent searches in localStorage | `lib/search/recent.ts` |
| FE-6.13 | API: search.ts | `lib/api/search.ts` |

### 🟡 Contract Tasks (`qbazaar-contracts`)

| ID | Task |
|----|------|
| CT-6.1 | Search + Saved Searches endpoints |
| CT-6.2 | Schemas: `SearchRequest`, `SearchResults`, `SearchSuggestion`, `SavedSearch` |

---

# Milestone 3 — Engagement (3 أسابيع)

---

## Sprint 7 — Favorites & Recently Viewed (1 يوم)

**التاريخ:** 2026-06-24
**Goal:** المستخدم يحفظ إعلانات ويشوف اللي شافها مؤخراً.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-7.1 | كمستخدم، أضيف إعلان لـ favorites بـ heart icon | P0 |
| US-7.2 | كمستخدم، أزيله من favorites | P0 |
| US-7.3 | كمستخدم، أشوف كل المحفوظات في صفحة | P0 |
| US-7.4 | كمستخدم، أشوف recently viewed كـ strip في home | P1 |
| US-7.5 | كمستخدم، أنظف recently viewed | P1 |

### 🔁 Critical Flows

- **F-7.1 (Save):** Click heart on AdCard → optimistic toggle → API confirms → toast
- **F-7.2 (View saved):** /saved → grid of saved ads → tap to view

### 🔵 Backend Tasks

| ID | Task | Endpoint |
|----|------|----------|
| BE-7.1 | FavoriteController (index/store/destroy) | `GET, POST, DELETE /favorites` |
| BE-7.2 | RecentlyViewedController (index/clear) | `GET, DELETE /account/recently-viewed` |
| BE-7.3 | TrackAdView observer → recent_views table update | — |
| BE-7.4 | CleanupRecentlyViewedJob (cap 50/user, scheduled daily) | — |
| BE-7.5 | Migration: `favorites` (user_id, ad_id, created_at, primary key composite) | — |
| BE-7.6 | Migration: `recently_viewed` (user_id, ad_id, viewed_at) | — |

### 🟣 Frontend Tasks

| ID | Task | Path / Component |
|----|------|------------------|
| FE-7.1 | Saved page | `app/[locale]/saved/page.tsx` — مرجع `saved.jsx` |
| FE-7.2 | FavoriteButton (optimistic toggle) | `components/favorites/FavoriteButton.tsx` |
| FE-7.3 | RecentlyViewedStrip (home) | `components/home/RecentlyViewedStrip.tsx` |
| FE-7.4 | useFavorites hook | `hooks/useFavorites.ts` |
| FE-7.5 | API: favorites.ts | `lib/api/favorites.ts` |

### 🟡 Contract Tasks

CT-7.1: 5 endpoints, schemas: `Favorite`, `RecentlyViewed`

---

## Sprint 8 — Messaging via Reverb (أسبوعين)

**التاريخ:** 2026-06-25 → 2026-07-08
**Goal:** Real-time chat بين المشتري والبائع عبر Laravel Reverb. Content safety + Reports integrated.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-8.1 | كمشتري، أبدأ محادثة من صفحة إعلان | P0 |
| US-8.2 | كمستخدم، أرسل رسالة نصية | P0 |
| US-8.3 | كمستخدم، أستلم رسائل real-time لما الـ tab مفتوح | P0 |
| US-8.4 | كمستخدم، أشوف كل المحادثات (inbox) | P0 |
| US-8.5 | كمستخدم، أشوف unread badges | P0 |
| US-8.6 | كمستخدم، الرسالة تتعلّم كمقروءة لما أفتح المحادثة | P0 |
| US-8.7 | كمستخدم، أبلّغ عن محادثة (spam, harassment) | P0 |
| US-8.8 | كمستخدم، الرسالة بتنذرني لو فيها رقم هاتف/رابط (content safety) | P0 |
| US-8.9 | كمستخدم محظور من آخر، ما أقدر أرسل له رسائل | P0 |
| US-8.10 | كمستخدم، أحظر شخص من داخل المحادثة | P0 |
| US-8.11 | كمستخدم، إذا الـ tab مغلق وأجتني رسالة → push notification (Sprint 10) | P0 |
| US-8.12 | كمستخدم، typing indicator | P2 (later) |
| US-8.13 | كمستخدم، online status indicator | P2 (later) |

### 🔁 Critical Flows

- **F-8.1 (Start Chat):** Ad page → "Chat with seller" → POST /conversations (creates if not exists) → redirect to /messages/{id}
- **F-8.2 (Send Message):** Type in composer → submit → POST /messages → optimistic UI → broadcast event → other party sees message instantly
- **F-8.3 (Real-time Receive):** Echo subscribes to private channel `conversation.{id}` → MessageSent event → message appended to list
- **F-8.4 (Mark Read):** Open conversation → PUT /conversations/{id}/read → backend marks unread_count = 0 → badge cleared
- **F-8.5 (Content Safety):** User types phone number → on send, server scans → flagged → message saved + warning shown to recipient

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint |
|----|------|----------|
| BE-8.1 | ConversationController (index/show/store) | `GET, POST /conversations` |
| BE-8.2 | ConversationController::markRead | `PUT /conversations/{id}/read` |
| BE-8.3 | ConversationController::report | `POST /conversations/{id}/report` |
| BE-8.4 | MessageController (index/store) | `GET, POST /conversations/{id}/messages` |
| BE-8.5 | Models: Conversation, Message | — |
| BE-8.6 | Migration: `conversations` (id, ad_id, buyer_id, seller_id, last_message_at, buyer_unread_count, seller_unread_count) | — |
| BE-8.7 | Migration: `messages` (id, conversation_id, sender_id, type, content, metadata JSON, flagged, flag_reason) | — |
| BE-8.8 | ConversationPolicy (only participants can view/post) | — |
| BE-8.9 | ConversationService::findOrCreate(ad, buyer) | — |
| BE-8.10 | ContentSafetyService (phone regex, link regex, banned words from cache) | — |
| BE-8.11 | MessageSent event (ShouldBroadcast) | — |
| BE-8.12 | Broadcasting channel `conversation.{id}` (auth check in `routes/channels.php`) | — |
| BE-8.13 | NewMessageNotification (push + email if user away) | — |
| BE-8.14 | Block check: refuse messages if either user blocks the other | — |
| BE-8.15 | Reverb setup: `php artisan reverb:start` instructions + Windows supervisor alternative | — |
| BE-8.16 | Rate limit: 30 messages/min per user | — |
| BE-8.17 | MessageType enum (TEXT, OFFER, SYSTEM) | — |
| BE-8.18 | Pest tests: conversations, messages, content safety edge cases | — |
| BE-8.19 | Scribe + Localization | — |

### 🟣 Frontend Tasks (`qbazaar-web`)

| ID | Task | Path / Component |
|----|------|------------------|
| FE-8.1 | Messages inbox page | `app/[locale]/messages/page.tsx` — مرجع `messages.jsx` |
| FE-8.2 | Chat page | `app/[locale]/messages/[id]/page.tsx` |
| FE-8.3 | ConversationList | `components/messages/ConversationList.tsx` |
| FE-8.4 | ConversationItem (preview + unread badge) | `components/messages/ConversationItem.tsx` |
| FE-8.5 | ChatWindow | `components/messages/ChatWindow.tsx` |
| FE-8.6 | MessageBubble (own/other style, timestamp, status) | `components/messages/MessageBubble.tsx` |
| FE-8.7 | MessageComposer (textarea + send + emoji later) | `components/messages/MessageComposer.tsx` |
| FE-8.8 | ContentSafetyWarning (banner in chat) | `components/messages/ContentSafetyWarning.tsx` |
| FE-8.9 | TypingIndicator (placeholder) | `components/messages/TypingIndicator.tsx` |
| FE-8.10 | ChatHeader (seller name, ad link, block/report menu) | `components/messages/ChatHeader.tsx` |
| FE-8.11 | StartChatButton (used on Ad page) | `components/messages/StartChatButton.tsx` |
| FE-8.12 | Laravel Echo setup | `lib/echo.ts` |
| FE-8.13 | useConversation, useMessages (cursor pagination) | `hooks/messages/*.ts` |
| FE-8.14 | useEchoChannel hook | `hooks/useEchoChannel.ts` |
| FE-8.15 | Messages store (active conversation, unread counts) | `store/messages.ts` |
| FE-8.16 | API: conversations.ts, messages.ts | `lib/api/conversations.ts`, `lib/api/messages.ts` |
| FE-8.17 | Reconnection logic (exponential backoff + fetch missed messages via REST) | — |
| FE-8.18 | Optimistic message send + status (pending/sent/failed) | — |

### 🟡 Contract Tasks

| ID | Task |
|----|------|
| CT-8.1 | Conversations + Messages endpoints |
| CT-8.2 | Schemas: `Conversation`, `Message`, `SendMessageRequest`, `ContentFlag` |
| CT-8.3 | WebSocket events spec في `events/messages.yaml`: `MessageSent`, `MessageRead`, `UserTyping` |
| CT-8.4 | Error codes: MSG_001 (blocked), MSG_002 (rate limited), MSG_003 (flagged) |

---

## Sprint 9 — Offers (1 يوم)

**التاريخ:** 2026-07-09
**Goal:** نظام عروض الأسعار داخل المحادثات.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-9.1 | كمشتري، أقدّم عرض سعر على إعلان قابل للتفاوض | P0 |
| US-9.2 | كبائع، أشوف العرض داخل المحادثة | P0 |
| US-9.3 | كبائع، أقبل العرض | P0 |
| US-9.4 | كبائع، أرفض العرض | P0 |
| US-9.5 | كأي طرف، أقدم counter-offer | P0 |
| US-9.6 | العرض ينتهي تلقائياً بعد 7 أيام | P0 |
| US-9.7 | كمستخدم، أشوف كل عروضي (sent + received) | P0 |

### 🔁 Critical Flows

- **F-9.1 (Make Offer):** Ad page (negotiable) → "Make Offer" → enter amount + optional message → submit → POST /offers → conversation created if not exists → offer message added to chat
- **F-9.2 (Counter):** Chat → click offer message → "Counter" → enter new amount → submit → new offer in chat

### 🔵 Backend Tasks

| ID | Task | Endpoint |
|----|------|----------|
| BE-9.1 | OfferController (store/show/accept/reject/counter) | `POST /offers`, `GET /offers/{id}`, `POST /offers/{id}/accept|reject|counter` |
| BE-9.2 | Models: Offer | — |
| BE-9.3 | Migration: `offers` (id, conversation_id, ad_id, buyer_id, seller_id, amount, message, status, parent_offer_id, expires_at) | — |
| BE-9.4 | OfferStatus enum (PENDING, ACCEPTED, REJECTED, COUNTERED, EXPIRED) | — |
| BE-9.5 | MakeOfferAction (creates conversation if needed, creates Offer + Message) | — |
| BE-9.6 | AcceptOfferAction, RejectOfferAction, CounterOfferAction | — |
| BE-9.7 | ExpireOldOffersJob (scheduled daily) | — |
| BE-9.8 | OfferReceivedNotification, OfferAcceptedNotification, OfferCounteredNotification | — |
| BE-9.9 | Pest tests | — |

### 🟣 Frontend Tasks

| ID | Task | Path / Component |
|----|------|------------------|
| FE-9.1 | MakeOfferModal | `components/ads/MakeOfferModal.tsx` |
| FE-9.2 | OfferMessageBubble (special inside chat) | `components/messages/OfferMessageBubble.tsx` |
| FE-9.3 | CounterOfferModal | `components/messages/CounterOfferModal.tsx` |
| FE-9.4 | OfferActions (Accept/Reject/Counter buttons) | `components/messages/OfferActions.tsx` |
| FE-9.5 | My Offers page | `app/[locale]/account/offers/page.tsx` |
| FE-9.6 | API: offers.ts | `lib/api/offers.ts` |

### 🟡 Contract Tasks

CT-9.1: 5 endpoints, schemas: `Offer`, `MakeOfferRequest`, `CounterOfferRequest`

---

# Milestone 4 — Trust & Admin (أسبوعين)

---

## Sprint 10 — Reports & Notifications (1 أسبوع)

**التاريخ:** 2026-07-10 → 2026-07-16
**Goal:** نظام البلاغات + multi-channel notifications (database, email, FCM push).

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-10.1 | كمستخدم، أبلّغ عن إعلان (spam, fraud, inappropriate, duplicate, wrong_category) | P0 |
| US-10.2 | كمستخدم، أبلّغ عن مستخدم آخر (harassment, fake, scam) | P0 |
| US-10.3 | كمستخدم، أبلّغ عن محادثة | P0 |
| US-10.4 | كمستخدم، أشوف تاريخ بلاغاتي | P0 |
| US-10.5 | كمستخدم، أشوف notifications inbox مع filtering | P0 |
| US-10.6 | كمستخدم، أعلّم notifications كمقروءة | P0 |
| US-10.7 | كمستخدم، أعلّم الكل كمقروء | P0 |
| US-10.8 | كمستخدم، أتحكم في notification preferences (push/email/in-app per category) | P0 |
| US-10.9 | كمستخدم، أسجّل device للـ FCM (web/mobile) | P0 |
| US-10.10 | كمستخدم، أتلقى push notification لما يصير حدث | P0 |
| US-10.11 | كمستخدم، أحذف device token عند logout | P0 |

### 🔁 Critical Flows

- **F-10.1 (Report Ad):** On ad page → "..." menu → Report → modal with reasons → submit → POST /reports/ad → toast "thanks"
- **F-10.2 (Push Permission):** First visit → banner "Allow notifications" → click → browser permission → POST /device-tokens
- **F-10.3 (Receive Push):** New message arrives + user tab closed → FCM message → browser notification → click → opens conversation

### 🔵 Backend Tasks (`qbazaar-api`)

| ID | Task | Endpoint |
|----|------|----------|
| BE-10.1 | ReportController (reportAd, reportUser, reportConversation, myReports) | `POST /reports/{type}`, `GET /account/reports` |
| BE-10.2 | NotificationController (index, markRead, markAllRead) | `GET /notifications`, `PUT /notifications/{id}/read`, `PUT /notifications/read-all` |
| BE-10.3 | NotificationPrefController (show, update) | `GET, PUT /account/notification-preferences` |
| BE-10.4 | DeviceTokenController (register, unregister) | `POST /device-tokens`, `DELETE /device-tokens/{token}` |
| BE-10.5 | Migrations: `reports`, `device_tokens`, `notification_preferences` (JSON on users), `notifications` (Laravel native) | — |
| BE-10.6 | ReportTarget enum (AD, USER, CONVERSATION) | — |
| BE-10.7 | Models: Report, DeviceToken | — |
| BE-10.8 | FCM channel integration | — |
| BE-10.9 | Notification classes: AdApproved, AdRejected, NewMessage, OfferReceived, OfferAccepted, SavedSearchMatch, SecurityAlert, ReportResolved | — |
| BE-10.10 | NotificationService::dispatch with channel routing based on prefs | — |
| BE-10.11 | Multi-language notifications (uses user.language) | — |
| BE-10.12 | Pest tests + Firebase test setup (mocked) | — |

### 🟣 Frontend Tasks

| ID | Task | Path / Component |
|----|------|------------------|
| FE-10.1 | Notifications page | `app/[locale]/account/notifications/page.tsx` |
| FE-10.2 | Notification preferences page | `app/[locale]/account/notification-preferences/page.tsx` |
| FE-10.3 | NotificationBell (header dropdown) | `components/notifications/NotificationBell.tsx` |
| FE-10.4 | NotificationItem | `components/notifications/NotificationItem.tsx` |
| FE-10.5 | NotificationDropdown (in header) | `components/notifications/NotificationDropdown.tsx` |
| FE-10.6 | ReportModal (3 variants: ad/user/conversation) | `components/reports/ReportModal.tsx` |
| FE-10.7 | PushPermissionBanner | `components/notifications/PushPermissionBanner.tsx` |
| FE-10.8 | usePushPermission hook | `hooks/usePushPermission.ts` |
| FE-10.9 | useNotifications hook (with TanStack Query polling) | `hooks/useNotifications.ts` |
| FE-10.10 | Firebase Web SDK integration | `lib/firebase/client.ts` |
| FE-10.11 | Service Worker لـ background notifications | `public/firebase-messaging-sw.js` |
| FE-10.12 | API: notifications.ts, reports.ts | `lib/api/*.ts` |

### 🟡 Contract Tasks

CT-10.1: 10+ endpoints, schemas: `Notification`, `NotificationPreferences`, `Report`, `DeviceToken`

---

## Sprint 11 — Filament Admin Panel (1 أسبوع)

**التاريخ:** 2026-07-17 → 2026-07-23
**Goal:** Admin panel كامل لإدارة المنصة (16 resources + 3 pages + widgets).

### 📖 User Stories (admin-facing)

| ID | Story | Priority |
|----|-------|----------|
| US-11.1 | كأدمن، أراجع الإعلانات في status=pending وأقبلها/أرفضها/أحجبها | P0 |
| US-11.2 | كأدمن، أشوف dashboard مع KPIs (active ads, pending mod, open reports, new users 24h) | P0 |
| US-11.3 | كأدمن، أعلّق مستخدم أو أحذف حسابه | P0 |
| US-11.4 | كأدمن، أحلّ بلاغ (resolve) أو أتجاهله (dismiss) | P0 |
| US-11.5 | كأدمن، أعدّل أقسام/مواقع/قواعد الإشراف | P0 |
| US-11.6 | كأدمن، أعدّل CMS pages (terms, privacy, etc.) | P0 |
| US-11.7 | كأدمن، أعدّل help articles | P0 |
| US-11.8 | كأدمن، أعدّل notification templates | P0 |
| US-11.9 | كأدمن، أشوف audit logs | P0 |
| US-11.10 | كأدمن، أعدّل system settings (key-value) | P0 |
| US-11.11 | كأدمن، أوافق/أرفض طلبات business sellers (لو فعّلنا) | P1 |
| US-11.12 | كأدمن، أدير admin users + roles | P0 |
| Admin Auth | كأدمن، أسجل دخول مع 2FA إجباري | P0 |

### 🔁 Critical Flows

- **F-11.1 (Moderate Ad):** Login as admin → Dashboard → "Pending Ads" widget → click → Ad list → click ad → Review → "Approve" or "Reject" with reason → User notified
- **F-11.2 (Resolve Report):** Reports list → filter "open" → click report → context (linked ad/user/conv) → action: ignore / warn user / suspend user / remove ad → Resolve

### 🔵 Backend Tasks (`qbazaar-api` — Filament)

| ID | Task |
|----|------|
| BE-11.1 | `app/Filament/Resources/UserResource.php` (form + table + filters + suspend action) |
| BE-11.2 | `AdResource.php` (with Approve/Reject/Block actions, status filters, image preview) |
| BE-11.3 | `ReportResource.php` (link to target, resolve actions) |
| BE-11.4 | `CategoryResource.php` (tree builder + translatable form) |
| BE-11.5 | `LocationResource.php` (hierarchical) |
| BE-11.6 | `BusinessApplicationResource.php` |
| BE-11.7 | `SupportTicketResource.php` |
| BE-11.8 | `ModerationRuleResource.php` (banned words/patterns) |
| BE-11.9 | `CmsPageResource.php` (rich text + translatable) |
| BE-11.10 | `HelpArticleResource.php` (categories + slug) |
| BE-11.11 | `NotificationTemplateResource.php` |
| BE-11.12 | `AdminUserResource.php` (roles via Spatie Permission) |
| BE-11.13 | Pages: `Dashboard.php`, `SystemSettings.php`, `AuditLogsPage.php` |
| BE-11.14 | Widgets: `StatsOverview`, `AdsChart`, `PendingReportsWidget`, `LatestUsersWidget` |
| BE-11.15 | `app/Filament/Pages/Auth/AdminLogin.php` |
| BE-11.16 | 2FA: `stephenjude/filament-two-factor-authentication` plugin |
| BE-11.17 | Custom admin guard في `config/auth.php` |
| BE-11.18 | Actions: ApproveAdAction, RejectAdAction, BlockAdAction, SuspendUserAction, ResolveReportAction |
| BE-11.19 | Filament Spatie Translatable + MediaLibrary + Activitylog plugins |

### 🟣 Frontend Tasks
> Filament هو admin panel server-side. ما في frontend tasks في Sprint 11 على الـ Next.js side.

**Polish Tasks (Web side):**
| ID | Task |
|----|------|
| FE-11.1 | تنظيف console warnings عبر كل الـ pages |
| FE-11.2 | Lighthouse audit + إصلاح المشاكل |
| FE-11.3 | accessibility audit عبر axe-core |
| FE-11.4 | RTL audit (test all pages in Arabic) |
| FE-11.5 | Loading skeletons لكل query |
| FE-11.6 | Error boundaries |

### 🟡 Contract Tasks
> Filament internal، ما في contract changes في Sprint 11.

---

# Milestone 5 — Content & Polish (أسبوع)

---

## Sprint 12 — CMS, Help & Support (2 أيام)

**التاريخ:** 2026-07-24 → 2026-07-25
**Goal:** صفحات الـ CMS الثابتة + مركز المساعدة + الدعم.

### 📖 User Stories

| ID | Story | Priority |
|----|-------|----------|
| US-12.1 | كزائر، أقرأ About / Terms / Privacy / Safety | P0 |
| US-12.2 | كزائر، أتصفّح مقالات Help center | P0 |
| US-12.3 | كزائر، أبحث في مقالات Help | P0 |
| US-12.4 | كمستخدم، أرسل ticket دعم | P0 |
| US-12.5 | كمستخدم، أشوف تذاكر دعمي + ردود الفريق | P0 |
| US-12.6 | كمستخدم، أرد على ticket | P1 |
| US-12.7 | كزائر، أتواصل مع الفريق عبر contact form | P0 |

### 🔁 Critical Flows

- **F-12.1 (Read Help):** Help center → search "كيف أنشر إعلان" → results → article → satisfied or "still need help" → contact

### 🔵 Backend Tasks

| ID | Task | Endpoint |
|----|------|----------|
| BE-12.1 | CmsController::show | `GET /cms/pages/{slug}` |
| BE-12.2 | HelpController (index/show/search) | `GET /cms/help-articles`, `GET /cms/help-articles/{slug}` |
| BE-12.3 | SupportController (store, index, show, reply) | `POST /support/tickets`, `GET /account/support-tickets[/{id}]`, `POST /support/tickets/{id}/messages` |
| BE-12.4 | Models: CmsPage, HelpArticle, SupportTicket, SupportMessage (all translatable) | — |
| BE-12.5 | Migrations | — |
| BE-12.6 | Filament resources for CMS/Help/Support (added in Sprint 11 actually, polish here) | — |
| BE-12.7 | Seeders: initial CMS pages (Arabic + English) | — |

### 🟣 Frontend Tasks

| ID | Task | Path / Component |
|----|------|------------------|
| FE-12.1 | About page | `app/[locale]/about/page.tsx` |
| FE-12.2 | Terms page | `app/[locale]/terms/page.tsx` |
| FE-12.3 | Privacy page | `app/[locale]/privacy/page.tsx` |
| FE-12.4 | Safety page | `app/[locale]/safety/page.tsx` |
| FE-12.5 | Help center | `app/[locale]/help/page.tsx` — مرجع `help.jsx` |
| FE-12.6 | Help article | `app/[locale]/help/[slug]/page.tsx` |
| FE-12.7 | Help search | `components/help/HelpSearch.tsx` |
| FE-12.8 | Contact page | `app/[locale]/contact/page.tsx` |
| FE-12.9 | Support tickets list | `app/[locale]/account/support/page.tsx` |
| FE-12.10 | Support ticket detail + reply | `app/[locale]/account/support/[id]/page.tsx` |
| FE-12.11 | CmsContent (markdown/rich text renderer) | `components/cms/CmsContent.tsx` |

### 🟡 Contract Tasks

CT-12.1: 7 endpoints, schemas: `CmsPage`, `HelpArticle`, `SupportTicket`, `SupportMessage`

---

## QA & Buffer (3 أيام)

**التاريخ:** 2026-07-26 → 2026-07-28
**Goal:** Bug bash + audits + polish قبل التحضير للإطلاق.

### Tasks

- [ ] `QA-1` Bug bash session: اختبر كل user stories من Sprint 1-12 manually
- [ ] `QA-2` Lighthouse audit على كل صفحة (mobile + desktop)
- [ ] `QA-3` Laravel Pulse review (slow queries, slow jobs)
- [ ] `QA-4` Accessibility audit (axe DevTools على كل صفحة)
- [ ] `QA-5` Security audit:
  - [ ] `composer audit` لا vulnerabilities
  - [ ] `npm audit` لا high/critical
  - [ ] OWASP Top 10 checklist
  - [ ] SQL injection test (Eloquent should protect, but verify)
  - [ ] XSS test على user-generated content
  - [ ] CSRF test على Filament
  - [ ] Rate limiting verified على endpoints حساسة
- [ ] `QA-6` RTL audit: كل page في `/ar/*` لازم layout صحيح
- [ ] `QA-7` i18n audit: لا missing translations في console
- [ ] `QA-8` Dark mode audit: كل page تشتغل في dark mode
- [ ] `QA-9` Performance: bundle size analysis (next-bundle-analyzer)
- [ ] `QA-10` Performance: DB query analysis with Telescope
- [ ] `QA-11` Error tracking: Sentry sample errors verified
- [ ] `QA-12` Backup test: استرجع database backup في staging

---

# Milestone 6 — Web Frontend (Parallel)

> الـ Frontend track موزع داخل كل sprint أعلاه. هذا الـ milestone للأهداف المستقلة:

### Tasks

- [ ] `FE-M6.1` PWA capabilities: `manifest.json` + service worker (offline shell) — اختياري
- [ ] `FE-M6.2` Sitemap dynamic generation (consumes `/sitemap/ads` + `/sitemap/categories`)
- [ ] `FE-M6.3` OpenGraph + Twitter Cards على كل ad page
- [ ] `FE-M6.4` JSON-LD Product schema على ad detail
- [ ] `FE-M6.5` JSON-LD BreadcrumbList على category pages
- [ ] `FE-M6.6` robots.txt + canonical URLs
- [ ] `FE-M6.7` Lighthouse score > 90 mobile لكل page رئيسية
- [ ] `FE-M6.8` next-bundle-analyzer pass + code splitting حيث ينفع
- [ ] `FE-M6.9` Error boundary على كل route
- [ ] `FE-M6.10` Analytics setup (Plausible أو Vercel Analytics)

---

# Milestone 7 — Launch Prep (1 أسبوع)

**التاريخ:** 2026-08-12 → 2026-08-26
**Goal:** Production live على qbazaar.qa.

### Tasks

- [ ] `LP-1` تسجيل qbazaar.qa domain
- [ ] `LP-2` SSL certificate (Let's Encrypt via Forge/Cloudflare)
- [ ] `LP-3` Backend hosting setup:
  - [ ] اختيار: Laravel Forge / Laravel Cloud / DigitalOcean Droplet
  - [ ] Provision server (Ubuntu 22.04, PHP 8.3, Nginx, MySQL, Redis, Supervisor)
  - [ ] Deploy script + zero-downtime deploy
  - [ ] Horizon + Reverb daemons via Supervisor
  - [ ] Scheduled tasks via cron
- [ ] `LP-4` Frontend hosting: Vercel أو self-host Next.js on Forge
- [ ] `LP-5` CDN: Cloudflare for static + images
- [ ] `LP-6` R2 bucket production setup
- [ ] `LP-7` Production env variables (Twilio, FCM, Cloudflare R2, Sentry)
- [ ] `LP-8` DB migration to production (with seeders for categories/locations/CMS)
- [ ] `LP-9` Smoke tests on staging
- [ ] `LP-10` UAT (user acceptance testing) — أنت + 2-3 testers
- [ ] `LP-11` Sentry production alerts (error rate, latency)
- [ ] `LP-12` Backup strategy:
  - [ ] Daily DB dump → S3/R2
  - [ ] Cross-region replication (لاحقاً)
- [ ] `LP-13` Monitoring dashboard (Pulse + Sentry visible)
- [ ] `LP-14` Production deploy
- [ ] `LP-15` Post-launch monitoring (48 hours active)
- [ ] `LP-16` Soft launch announcement

---

## 📊 إجمالي التاسكات

| المايلستون | Backend | Frontend | Contract | Integration |
|------------|---------|----------|----------|-------------|
| Milestone 1 (Sprints 0-3) | ~75 | ~50 | ~15 | 5 |
| Milestone 2 (Sprints 4-6) | ~70 | ~55 | ~12 | 4 |
| Milestone 3 (Sprints 7-9) | ~30 | ~30 | ~8 | 3 |
| Milestone 4 (Sprints 10-11) | ~30 | ~15 | ~5 | 2 |
| Milestone 5 (Sprint 12 + QA) | ~10 | ~15 | ~3 | 12 |
| Milestone 6-7 | — | ~10 | — | ~16 |
| **الإجمالي** | **~215** | **~175** | **~43** | **~42** |

**~475 تاسك ضمن 14 أسبوع** = ~34 تاسك/أسبوع = ~7 تاسك/يوم عمل.

---

## 🚦 كيف نتقدم في كل Sprint

1. **Sprint Planning** (1-2 ساعة في بداية الـ sprint):
   - حدّث `ROADMAP.md` بحالة السبرنت السابق
   - راجع user stories للسبرنت الحالي
   - أنشئ GitHub Issues لكل task — issue per BE-X.Y / FE-X.Y / CT-X.Y
   - حدد الـ milestone و labels (track:*, area:*, priority:*)
   - اكتب الـ OpenAPI changes في `openapi/v1.yaml`

2. **التنفيذ اليومي (Multi-agent):**
   - أنا (Claude orchestrator) أشغّل Backend Agent + Frontend Agent بالتوازي
   - كل agent يفتح task من قائمته
   - يفرع branch: `feature/sprint-{N}-{task-id}-{short-desc}`
   - يلتزم بـ DoD ويفتح PR

3. **Integration (نهاية الـ sprint):**
   - بدّل `NEXT_PUBLIC_API_URL` من Prism (4010) لـ actual API (8000)
   - اختبر كل flow E2E
   - أصلح أي mismatch بين spec ↔ implementation

4. **Sprint Retro:**
   - اكتب في `ROADMAP.md`: ✅ ما تم / 🟡 تأخر / 🔴 blockers
   - أغلق الـ milestone على GitHub
   - merge develop → main لما يكون stable

---

**نهاية ملف MILESTONES.md.**
**الملف ده هيكون live document — كل أسبوع يتحدّث بناءً على ما تم في الـ retro.**
