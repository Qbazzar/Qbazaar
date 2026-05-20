---
title: QBazaar — المخطط المعماري الشامل

---

# QBazaar — المخطط المعماري الشامل
### Laravel 13 (API) + Next.js 15 (Web) + Flutter 3.x (Mobile)

> **هذا الملف يحدد كيف تتحدث الأطراف الثلاثة مع بعضها، والقرارات الحرجة قبل البدء.**
> **بدون اتفاق على هذه القرارات الآن — سيكون هناك إعادة كتابة لاحقاً.**

---

## 📋 جدول المحتويات

1. [الستاك الكامل](#1-الستاك-الكامل)
2. [الفلسفة المعمارية](#2-الفلسفة-المعمارية)
3. [القرارات المعمارية الحرجة](#3-القرارات-المعمارية-الحرجة)
4. [العقد بين الأطراف (API Contract)](#4-العقد-بين-الأطراف-api-contract)
5. [تحديثات الـ Backend الخاصة بالـ Multi-Client](#5-تحديثات-الـ-backend)
6. [Next.js Frontend — النظرة العامة](#6-nextjs-frontend)
7. [Flutter Mobile — النظرة العامة](#7-flutter-mobile)
8. [استراتيجية الـ Repository وسير العمل](#8-استراتيجية-الـ-repository)
9. [الجدول الزمني للتطوير المتوازي](#9-الجدول-الزمني)
10. [خريطة المخاطر والحلول](#10-خريطة-المخاطر)

---

## 1. الستاك الكامل

### 🖥️ Backend (Laravel)
- Laravel 13 + PHP 8.3
- PostgreSQL 16 + Redis 7 + Meilisearch
- Sanctum (Auth) + Reverb (WebSocket) + Horizon (Queues)
- Filament v5 (Admin Panel)
- Spatie Permission + Activitylog + MediaLibrary + Translatable
- نقطة دخول واحدة: REST API `/api/v1/*` + WebSocket `/app/*`

### 🌐 Frontend Web (Next.js)
- **Next.js 15** (App Router + Server Components)
- TypeScript 5.x (strict mode)
- Tailwind CSS 4 + shadcn/ui (premium aesthetic)
- TanStack Query v5 (Data fetching + caching)
- Zustand (Client state, خفيف)
- next-intl (i18n + AR/EN routing)
- React Hook Form + Zod (Forms + validation)
- Laravel Echo + pusher-js (WebSocket client)
- next-themes (Dark mode لاحقاً)
- nuqs (URL state management للفلاتر والبحث)
- Sharp + next/image (Image optimization)
- Sentry (Error tracking)
- Vercel أو self-hosted على VPS

### 📱 Mobile (Flutter)
- **Flutter 3.x** (Stable channel)
- Dart 3.x
- **State Management:** Riverpod 2.x (موصى به للمشاريع الإنتاجية)
- **Networking:** Dio + Retrofit (generated typed clients)
- **Storage:** Hive (cache) + flutter_secure_storage (tokens)
- **Routing:** go_router (Deep linking + Type-safe)
- **i18n:** flutter_localizations + intl + easy_localization
- **WebSocket:** pusher_channels_flutter (Reverb compatible)
- **Push:** firebase_messaging (FCM) + flutter_local_notifications
- **Images:** cached_network_image + image_picker + image_cropper
- **Forms:** flutter_form_builder
- **Maps (Phase 2):** google_maps_flutter
- **Analytics:** Firebase Analytics + Crashlytics
- **CI/CD:** Codemagic أو Fastlane + GitHub Actions

---

## 2. الفلسفة المعمارية

### 2.1 Headless API-First
الـ Backend ما يعرف شي عن الـ UI. يقدّم JSON API نظيف، والـ Web والـ Mobile يستهلكونه بحرية.

```
                    ┌────────────────────┐
                    │   Laravel Backend  │
                    │   (REST + WS API)  │
                    └─────────┬──────────┘
                              │
                  ┌───────────┼───────────┐
                  │           │           │
              ┌───▼───┐   ┌──▼────┐  ┌───▼────┐
              │Next.js│   │Flutter│  │Filament│
              │ Web   │   │ App   │  │ Admin  │
              └───────┘   └───────┘  └────────┘
```

### 2.2 العقد قبل الكود (Contract-First)
- OpenAPI 3 spec هي المصدر الوحيد للحقيقة
- Backend يولّدها من Scribe annotations
- Next.js يولّد TypeScript types منها (`openapi-typescript`)
- Flutter يولّد Dart classes منها (`openapi-generator` أو Retrofit + freezed)
- **القاعدة:** أي تغيير في الـ API يتم في الـ Spec أولاً، ثم الـ code

### 2.3 Single Source of Truth
- بيانات المستخدم → Backend
- منطق الأعمال → Backend
- التحقق (Validation) → Backend (الـ clients يكررونه لـ UX فقط، لكن الحقيقة في الـ Backend)
- التراجم الثابتة (UI strings) → كل client يدير ملفاته
- المحتوى الديناميكي (Categories, CMS) → Backend بـ AR/EN

### 2.4 ما لا يفعله الـ Backend
- ❌ ما يولّد HTML
- ❌ ما يدير Sessions باستخدام cookies للـ API (Tokens فقط)
- ❌ ما يعرف صيغة الصور المطلوبة من العميل (يقدّم URLs لأحجام متعددة)
- ❌ ما يفترض locale معين (يستقبل عبر `Accept-Language`)

---

## 3. القرارات المعمارية الحرجة

> **هذه القرارات يجب أن تُحسم قبل أي كود.**

### 3.1 استراتيجية المصادقة (Authentication)

**القرار:** **Sanctum Personal Access Tokens + Custom Refresh Token Layer**

#### لماذا ليس Sanctum SPA Mode (Cookies)?
- يتطلب same root domain بين الـ API والـ Frontend
- يحتاج CSRF token management في Next.js
- ما يشتغل مع Flutter بدون hacks
- محدودية في multi-device

#### لماذا ليس Laravel Passport (OAuth2)?
- مبالغة لمشروع marketplace
- إعداد أثقل، حزم إضافية
- شي قوي لكن غير مطلوب هنا

#### الحل المختار: Sanctum + Refresh Tokens

```
Login:
   POST /api/v1/auth/login
   Body: { identifier, password }
   Response: {
     access_token: "...",     // صلاحية 15 دقيقة
     refresh_token: "...",    // صلاحية 30 يوم، مخزن بـ hash في DB
     user: { ... }
   }

Refresh:
   POST /api/v1/auth/refresh
   Body: { refresh_token }
   Response: { access_token, refresh_token (rotated) }

Logout:
   POST /api/v1/auth/logout
   Header: Authorization: Bearer <access_token>
   → يحذف الـ refresh token من DB
```

#### Token Storage بكل side
| Side | Access Token | Refresh Token |
|------|--------------|---------------|
| **Next.js** | HTTP-only Cookie + Memory | HTTP-only Cookie (Secure, SameSite=Lax) |
| **Flutter** | Memory + flutter_secure_storage | flutter_secure_storage (Keychain/Keystore) |

> **مهم:** Next.js لا يخزن tokens في localStorage (XSS risk). يستخدم HTTP-only cookies عبر API routes intermediary، أو يخزن في memory ويعيد login بعد refresh.

#### Token Rotation Policy
- كل `refresh` يولّد refresh token جديد ويبطل القديم
- إذا استُخدم refresh token مبطل → suspect attack → invalidate كل tokens المستخدم
- Device fingerprint مخزن مع كل refresh token (لاكتشاف السرقة)

---

### 3.2 استراتيجية الـ API Versioning

**القرار:** URL-based versioning من اليوم الأول

```
/api/v1/ads/...
/api/v1/auth/...
```

#### القواعد
- **Mobile لا يمكن إجباره على التحديث** → يجب الحفاظ على v1 لفترة طويلة
- نشر v2 عند Breaking changes فقط، مع دعم v1 لـ 12 شهر على الأقل
- Backend يدعم endpoint للتحقق من إصدار الـ Mobile app:
  ```
  GET /api/v1/app/version-check?platform=ios&version=1.2.3
  Response: { is_supported: true, force_update: false, latest: "1.3.0" }
  ```
- إذا `force_update: true` → Flutter يعرض شاشة إجبارية للتحديث

#### Deprecation Strategy
- Header: `X-API-Deprecation: true`
- Header: `X-API-Sunset: 2027-01-01`
- Sentry alert عند أي client يستدعي endpoint deprecated

---

### 3.3 استراتيجية الـ Real-Time (WebSocket)

**القرار:** **Laravel Reverb** (Pusher-compatible)

#### Compatibility
- ✅ Next.js: `laravel-echo` + `pusher-js`
- ✅ Flutter: `pusher_channels_flutter`

#### Channels Architecture
```
Private Channels:
  - user.{userId}                  → notifications شخصية
  - conversation.{conversationId}  → رسائل المحادثة

Presence Channels (مستقبلاً):
  - conversation.{id}.presence     → typing indicators, online status

Public Channels (لا تستخدم):
  - لا داعي. كل شي مرتبط بمستخدم.
```

#### Mobile Background Strategy
- WebSocket يفصل تلقائياً عند اغلاق التطبيق
- الرسائل الجديدة تصل عبر **FCM Push** (يفتحها التطبيق عند العودة)
- عند فتح التطبيق: WebSocket reconnect + جلب الرسائل الجديدة عبر REST

#### Reconnection Logic
- Exponential backoff (1s, 2s, 4s, 8s, max 30s)
- بعد reconnect: جلب الرسائل من last_seen_at عبر REST (مش الاعتماد على WS وحده)

---

### 3.4 توصيل الصور والملفات

**القرار:** Backend يقدّم URLs لأحجام متعددة، كل client يختار الأنسب.

#### Response Format
```json
{
  "id": "01HM...",
  "url": "https://cdn.qbazaar.qa/ads/01HM/original.webp",
  "sizes": {
    "thumbnail": "https://cdn.qbazaar.qa/ads/01HM/thumbnail.webp",   // 200x200
    "medium":    "https://cdn.qbazaar.qa/ads/01HM/medium.webp",      // 640px
    "large":     "https://cdn.qbazaar.qa/ads/01HM/large.webp",       // 1024px
    "original":  "https://cdn.qbazaar.qa/ads/01HM/original.webp"     // max 1920px
  },
  "width": 1920,
  "height": 1280,
  "blurhash": "L6PZfSi_.AyE_3t7t7R**0o#DgR4"
}
```

#### استخدام كل client
- **Next.js:** يستخدم `next/image` مع `sizes` attribute. يعتمد على `large` كـ src أساسي و `medium` كـ srcset
- **Flutter:** يستخدم `medium` بشكل افتراضي، `original` عند الفتح كاملاً
- **BlurHash:** placeholder بسيط أثناء التحميل (تجربة premium)

#### CDN Strategy
- Cloudflare R2 (cheaper) أو AWS S3 + CloudFront
- جميع الصور WebP في النهاية (توفير bandwidth)
- Cache headers طويلة (مع versioned URLs)

#### Upload Flow
```
Client (Flutter/Next.js):
1. (اختياري) compress + resize محلياً (Flutter: image_picker quality)
2. POST /api/v1/uploads/images (multipart)
3. Response: { id, status: "processing" }
4. Backend Worker (Horizon) يعالج: conversions + pHash
5. Client يستقبل event عبر WebSocket: "media.ready" أو يـ poll
6. UI يعرض الصورة الجاهزة
```

---

### 3.5 الترجمة والـ RTL

**القرار:** ثلاث طبقات منفصلة، كل طبقة لها مالك واضح.

| الطبقة | المالك | الأداة |
|--------|---------|---------|
| **UI Strings** (Buttons, Labels) | كل client | next-intl / easy_localization |
| **Dynamic Content** (Categories, CMS) | Backend | Spatie Translatable في DB |
| **Validation Messages** | Backend | `lang/ar/validation.php` + `lang/en/validation.php` |
| **Error Messages** | Backend | يرسل `message_key` والـ client يترجم |

#### الـ Backend يستقبل لغة المستخدم عبر:
1. `Accept-Language` header (الافتراضي)
2. `?lang=ar` في query (override)
3. User preference في DB (للـ authenticated users)

#### RTL Implementation
- **Next.js:**
  - `<html dir="rtl" lang="ar">` ديناميكياً
  - Tailwind CSS 4 يدعم `rtl:` modifier
  - استخدام logical properties (`ms-4` بدل `ml-4`)
- **Flutter:**
  - `MaterialApp(locale: ..., supportedLocales: [ar, en])` تلقائياً يحدد direction
  - `Directionality.of(context)` للحالات الخاصة

#### Dynamic Content Response
```json
{
  "category": {
    "id": "...",
    "name": { "ar": "سيارات", "en": "Cars" },
    "description": { "ar": "...", "en": "..." }
  }
}
```
> ⚠️ **القرار:** Backend يرسل **الكائن الكامل** لكلتا اللغتين دائماً. الـ Client يختار حسب locale. هذا يبسّط الـ caching ويسمح بتبديل اللغة فورياً بدون إعادة fetch.

**استثناء:** Search results — Backend يرسل اللغة المطلوبة فقط لتخفيف payload.

---

### 3.6 إشعارات الـ Push

**القرار:** **FCM لكل المنصات** (Android + iOS + Web).

#### لماذا ليس APNs مباشرة؟
- FCM يدعم iOS عبر APNs internally
- إدارة موحّدة في الـ Backend
- Web Push يستخدم نفس البنية

#### Device Token Management
```
POST /api/v1/device-tokens
Body: {
  token: "fcm_token_here",
  platform: "ios|android|web",
  app_version: "1.2.3",
  device_name: "iPhone 15 Pro",
  device_id: "unique_device_id"
}

DELETE /api/v1/device-tokens/{token}  // عند logout أو app uninstall
```

#### Notification Payload Structure
```json
{
  "notification": {
    "title": "إعلانك تمت الموافقة عليه",
    "body": "تويوتا كامري 2020"
  },
  "data": {
    "type": "ad.approved",
    "ad_id": "01HM...",
    "deep_link": "qbazaar://ad/01HM..."
  }
}
```

#### Deep Linking
- Format: `qbazaar://ad/{id}` و `https://qbazaar.qa/ad/{id}`
- Flutter: go_router + uni_links
- Next.js: native URLs (لا داعي لـ deep linking)
- Universal Links على iOS + App Links على Android (يتطلب `.well-known/assetlinks.json` على دومين الـ web)

---

## 4. العقد بين الأطراف (API Contract)

### 4.1 Response Format موحّد

#### Success Response
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 156,
    "has_more": true,
    "next_cursor": null
  }
}
```

#### Error Response
```json
{
  "success": false,
  "error": {
    "code": "AD_001",
    "message_key": "errors.ad.not_found",
    "message": "Ad not found",
    "details": null,
    "request_id": "req_abc123"
  }
}
```

#### Validation Errors
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message_key": "errors.validation_failed",
    "message": "The given data was invalid",
    "details": {
      "email": ["The email has already been taken"],
      "phone": ["The phone format is invalid"]
    }
  }
}
```

### 4.2 Pagination

**القرار:** Cursor-based للقوائم الطويلة، Page-based للأدمن.

- **Cursor-based** لـ: `/ads/latest`, `/conversations/{id}/messages`, `/notifications`
  - أداء أعلى مع scroll لانهائي
  - مستقر مع التحديثات الكثيرة
- **Page-based** لـ: `/search`, `/admin/*`
  - يحتاج المستخدم رؤية رقم الصفحة

### 4.3 Date & Time

**القرار:** ISO 8601 UTC في كل مكان.

```
"created_at": "2026-05-19T14:30:00.000Z"
```

- Backend يخزن UTC في DB
- Backend يرسل UTC في API
- Client يحوّل لـ Asia/Qatar timezone عند العرض

### 4.4 Money & Currency

```json
{
  "price": 5000,
  "currency": "QAR",
  "formatted": "5,000 ر.ق"  // معروض من Backend ليتوحد
}
```

> Backend يرسل القيمة الرقمية + الصيغة المعروضة. هذا يضمن توحيد العرض بين Web و Mobile.

### 4.5 Identifiers

**القرار:** ULID (Universally Unique Lexicographically Sortable Identifier).

```
"id": "01HM3XQVZP5KJWQR2K8N0DJSAW"
```

- 26 حرف، URL-safe
- مرتب زمنياً (أفضل من UUID للـ indexing)
- Laravel 13 يدعمه عبر `HasUlids` trait
- مدعوم في Dart و TypeScript بسهولة

---

## 5. تحديثات الـ Backend خصيصاً للـ Multi-Client

### 5.1 CORS Configuration
```php
// config/cors.php
'allowed_origins' => [
    'https://qbazaar.qa',
    'https://www.qbazaar.qa',
    'https://admin.qbazaar.qa',  // Filament
    env('APP_ENV') === 'local' ? 'http://localhost:3000' : null,
],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'exposed_headers' => ['X-API-Deprecation', 'X-API-Sunset', 'X-Request-Id'],
'supports_credentials' => true,
```

### 5.2 User Agent Detection
```php
// Middleware: TrackClient
class TrackClient
{
    public function handle($request, $next)
    {
        $client = match(true) {
            $request->hasHeader('X-Client-Platform') => $request->header('X-Client-Platform'),
            str_contains($request->userAgent(), 'Flutter') => 'mobile',
            str_contains($request->userAgent(), 'Mozilla') => 'web',
            default => 'unknown',
        };

        $request->attributes->set('client_platform', $client);
        return $next($request);
    }
}
```

### 5.3 Mobile-Specific Endpoints

```php
// Version check
Route::get('app/version-check', AppVersionController::class);

// Device tokens
Route::middleware('auth:sanctum')->group(function () {
    Route::post('device-tokens', [DeviceTokenController::class, 'register']);
    Route::delete('device-tokens/{token}', [DeviceTokenController::class, 'unregister']);
});

// Sync endpoint (للـ offline-first)
Route::middleware('auth:sanctum')->get('sync', SyncController::class);
// يرجع: { categories, locations, user_data, last_sync_at }
```

### 5.4 SEO-Friendly Endpoints للـ Next.js

```php
// Sitemap data
Route::get('sitemap/ads', SitemapAdsController::class);
Route::get('sitemap/categories', SitemapCategoriesController::class);

// Ad page metadata (لـ SSR/SSG)
Route::get('ads/{ad}/seo-data', AdSeoController::class);
// يرجع: { title, description, og_image, structured_data (JSON-LD) }
```

### 5.5 Webhook Endpoint للـ Next.js Revalidation

```php
// عند تعديل ad من admin → Next.js يعيد بناء الصفحة
Route::post('webhooks/revalidate', RevalidationWebhookController::class)
     ->middleware('webhook.signature');

// Backend يستدعي: POST https://qbazaar.qa/api/revalidate
// Next.js يـ revalidate الـ path المطلوب
```

---

## 6. Next.js Frontend — النظرة العامة

### 6.1 هيكل المشروع
```
qbazaar-web/
├── app/
│   ├── [locale]/                 # ar | en
│   │   ├── (marketing)/
│   │   │   ├── page.tsx          # Home /
│   │   │   ├── safety/page.tsx
│   │   │   └── terms/page.tsx
│   │   ├── (browse)/
│   │   │   ├── categories/page.tsx
│   │   │   ├── category/[slug]/page.tsx
│   │   │   └── search/page.tsx
│   │   ├── ad/[id]/[slug]/page.tsx
│   │   ├── (auth)/
│   │   │   ├── login/page.tsx
│   │   │   └── register/page.tsx
│   │   ├── (account)/
│   │   │   ├── account/page.tsx
│   │   │   ├── my-ads/page.tsx
│   │   │   └── messages/[id]/page.tsx
│   │   └── post-ad/
│   ├── api/
│   │   ├── revalidate/route.ts
│   │   └── auth/[...]/route.ts   # Token refresh proxy
│   └── layout.tsx
├── components/
│   ├── ui/                       # shadcn primitives
│   ├── shared/                   # Navbar, Footer
│   ├── ads/                      # AdCard, AdGallery
│   ├── search/                   # SearchBar, Filters
│   └── chat/                     # MessageBubble, etc.
├── lib/
│   ├── api/                      # Generated client from OpenAPI
│   ├── auth/                     # Token management
│   ├── hooks/                    # Custom hooks
│   ├── utils/
│   └── echo.ts                   # Laravel Echo setup
├── i18n/
│   ├── ar.json
│   ├── en.json
│   └── config.ts
├── middleware.ts                 # i18n + auth routing
└── next.config.ts
```

### 6.2 Rendering Strategy
| الصفحة | الاستراتيجية | المبرر |
|---------|--------------|---------|
| Home `/` | **SSG + ISR (60s)** | عام، يستفيد من الـ cache |
| Category pages | **ISR (5min)** | يتغير مع الإعلانات الجديدة |
| Ad details | **SSG على demand + ISR (30s)** | SEO حرج، يجب يكون متاح |
| Search results | **CSR** | ديناميكي بالكامل |
| Account pages | **CSR** | محتوى شخصي، Auth-required |
| Messages | **CSR** | Real-time |

### 6.3 Data Fetching Pattern
- **Server Components** للـ initial load (SSR)
- **TanStack Query** للـ client-side updates
- **Optimistic Updates** للإجراءات السريعة (favorite, send message)
- **Suspense Boundaries** للـ loading states

### 6.4 Auth Flow في Next.js
```
1. Login → POST /api/v1/auth/login → استلام tokens
2. Next.js يخزن refresh_token في HTTP-only cookie (عبر API route)
3. access_token في memory (Zustand store)
4. كل request: axios interceptor يضيف Bearer token
5. عند 401: interceptor يستدعي /api/auth/refresh تلقائياً ويعيد المحاولة
6. لو فشل refresh: redirect إلى /login
```

### 6.5 الميزات الخاصة بـ Next.js
- **next-intl middleware** يحدد locale من URL (`/ar/...` vs `/en/...`)
- **next/image** يستفيد من `sizes` من الـ API
- **Sitemap توليد ديناميكي** يستهلك endpoint من Backend
- **OpenGraph tags** ديناميكية لكل ad
- **JSON-LD structured data** للـ SEO (Product schema)
- **RSS feed** لـ آخر الإعلانات (اختياري)

---

## 7. Flutter Mobile — النظرة العامة

### 7.1 هيكل المشروع
```
qbazaar_mobile/
├── lib/
│   ├── core/
│   │   ├── api/                  # Dio + Retrofit clients
│   │   ├── auth/                 # AuthRepository, Token storage
│   │   ├── di/                   # Dependency injection (get_it)
│   │   ├── router/               # go_router config
│   │   ├── theme/                # Material 3 theme
│   │   ├── localization/         # easy_localization setup
│   │   ├── network/              # Interceptors, error handling
│   │   ├── storage/              # Hive boxes
│   │   └── websocket/            # Pusher client
│   ├── features/
│   │   ├── auth/
│   │   │   ├── data/
│   │   │   ├── domain/
│   │   │   └── presentation/
│   │   │       ├── pages/
│   │   │       ├── widgets/
│   │   │       └── controllers/  # Riverpod providers
│   │   ├── ads/
│   │   ├── search/
│   │   ├── post_ad/
│   │   ├── messages/
│   │   ├── notifications/
│   │   ├── account/
│   │   └── favorites/
│   ├── shared/
│   │   ├── widgets/              # AdCard, EmptyState, etc.
│   │   ├── models/
│   │   └── utils/
│   ├── app.dart
│   └── main.dart
├── assets/
│   ├── translations/
│   │   ├── ar.json
│   │   └── en.json
│   ├── images/
│   └── icons/
├── android/
├── ios/
└── pubspec.yaml
```

### 7.2 Architecture: Clean Architecture لكل Feature
```
features/ads/
├── data/
│   ├── datasources/
│   │   ├── ad_remote_datasource.dart     # API calls
│   │   └── ad_local_datasource.dart      # Hive cache
│   ├── models/                            # JSON serializable
│   └── repositories/
│       └── ad_repository_impl.dart
├── domain/
│   ├── entities/                          # Pure Dart classes
│   ├── repositories/                      # Abstract
│   └── usecases/
│       ├── get_ads_usecase.dart
│       └── publish_ad_usecase.dart
└── presentation/
    ├── pages/
    ├── widgets/
    └── controllers/                       # Riverpod StateNotifiers
```

### 7.3 الشاشات الأساسية (من PRD)
- Splash + Onboarding (Language, Location, Notifications)
- Home + Search + Filters Bottom Sheet
- Ad Details (مع image swipe + zoom)
- Post Ad Stepper (4 خطوات)
- Camera/Gallery picker + Image cropper
- Messages Inbox + Chat (real-time)
- Account (My Ads, Favorites, Profile, Settings)
- Offline state (no internet)

### 7.4 Flutter-Specific Considerations
- **Splash + Native Splash:** `flutter_native_splash`
- **App Icons:** `flutter_launcher_icons`
- **Force Update Screen:** فحص version على كل cold start
- **Background Notifications:** FCM background handler
- **Image Compression قبل الرفع:** `flutter_image_compress` (يقلل bandwidth)
- **Connectivity Detection:** `connectivity_plus` للـ offline state
- **Biometric Auth (لاحقاً):** `local_auth` للـ Face ID / Touch ID
- **App Lifecycle:** WebSocket disconnect عند background, reconnect عند foreground
- **Deep Linking:** Universal Links + App Links setup
- **Persistent Login:** Auto-refresh tokens عند فتح التطبيق

### 7.5 التحديات الخاصة بـ Flutter في هذا المشروع
1. **RTL في كل التطبيق:** TextDirection.rtl يجب أن يُحترم في كل widget مخصص
2. **الخطوط العربية:** استخدام Cairo أو IBM Plex Sans Arabic (premium aesthetic)
3. **Image Pinch-to-Zoom:** `photo_view` package للـ gallery
4. **Form Validation متطابقة مع Backend:** نفس rules بالضبط
5. **Pull-to-refresh + Infinite scroll:** `pull_to_refresh` + Riverpod pagination
6. **App Store Compliance:** خصوصاً iOS (privacy declarations)

---

## 8. استراتيجية الـ Repository وسير العمل

### 8.1 Polyrepo (موصى به)
```
github.com/qbazaar/
├── qbazaar-api          # Laravel
├── qbazaar-web          # Next.js
├── qbazaar-mobile       # Flutter
├── qbazaar-contracts    # OpenAPI spec (مشترك)
└── qbazaar-infra        # Terraform / Docker / k8s (لاحقاً)
```

**لماذا polyrepo وليس monorepo؟**
- لغات مختلفة (PHP, TypeScript, Dart)
- CI/CD مختلف تماماً
- Deploy cycles مختلفة (Web continuous, Mobile store releases)
- فرق ممكن تنفصل لاحقاً
- Tooling أبسط

### 8.2 Contracts Repo
```
qbazaar-contracts/
├── openapi/
│   ├── v1.yaml                  # المصدر الوحيد للحقيقة
│   └── schemas/
├── events/                      # WebSocket events spec
│   └── messages.yaml
├── error-codes.md               # كل error codes في مكان واحد
└── README.md
```

#### Workflow
1. Backend يضيف endpoint جديد → يحدّث `v1.yaml` في contracts repo
2. CI في contracts repo: validation + diff check
3. تلقائياً يفتح PR في qbazaar-web و qbazaar-mobile لتحديث الـ generated types
4. الـ teams يراجعون ويدمجون

### 8.3 Branch Strategy (لكل repo)
- `main` → production
- `develop` → staging
- `feature/xxx` → PR إلى develop
- Hotfixes → PR إلى main + cherry-pick إلى develop

### 8.4 Environments
| Env | Backend | Web | Mobile |
|-----|---------|-----|--------|
| **Local** | Sail | localhost:3000 | iOS Simulator / Android Emulator |
| **Staging** | api-staging.qbazaar.qa | staging.qbazaar.qa | TestFlight + Internal Testing |
| **Production** | api.qbazaar.qa | qbazaar.qa | App Store + Google Play |

---

## 9. الجدول الزمني للتطوير المتوازي

### 9.1 المرحلة 1: Backend Foundation (أسبوعين)
**Backend وحده يشتغل**
- Sprint 0 (Infrastructure)
- Sprint 1 (Auth)
- Sprint 2 (Users)
- Sprint 3 (Categories & Locations)

**النتيجة:** API stable لـ Auth + Users + Categories + Locations. Contracts repo فيه v1.yaml أولي.

### 9.2 المرحلة 2: التطوير المتوازي يبدأ (8-10 أسابيع)

**3 teams شغالة بالتوازي:**

| الأسبوع | Backend | Next.js | Flutter |
|---------|---------|---------|---------|
| 3 | Uploads + Ads (begin) | Setup + Auth + Home | Setup + Auth + Onboarding |
| 4 | Ads (continue) | Categories + Search UI | Home + Categories |
| 5 | Search (Meilisearch) | Ad Details + SSR | Search + Filters |
| 6 | Favorites + Messaging (begin) | Post Ad flow | Ad Details + Image Gallery |
| 7 | Messaging (Reverb) | My Ads + Favorites | Post Ad Stepper + Camera |
| 8 | Offers + Reports | Messages + Chat UI | Messages + Chat (Pusher) |
| 9 | Notifications + Admin | Notifications + Settings | Notifications + Push (FCM) |
| 10 | Admin (Filament) + CMS | Account + Profile | Account + Profile |
| 11 | QA + Bug fixes | QA + Polish | QA + Store submission prep |
| 12 | UAT | UAT | TestFlight + Internal Testing |

### 9.3 المرحلة 3: Launch (أسبوع)
- Production deployment للـ Backend
- Next.js على Vercel/VPS
- iOS app review (5-7 أيام عادة)
- Android: Google Play release (1-3 أيام)

### 9.4 إجمالي الوقت
- **مع 3 developers (Backend + Web + Mobile):** **~3 أشهر** للـ MVP
- **مع 5 developers (Backend×2 + Web + Mobile + DevOps):** **~2.5 شهر**
- **Developer واحد لكل ثلاثة:** **~6 أشهر** (غير موصى به)

### 9.5 نقاط Sync الإلزامية بين الفرق
- Daily standup قصير (15 دقيقة، 3 ممثلين)
- Weekly sync لمراجعة الـ contracts changes
- Bi-weekly demo لكل المشروع
- Slack channel #api-contracts للتحديثات الفورية

---

## 10. خريطة المخاطر والحلول

| المخاطرة | الاحتمال | التأثير | الحل |
|----------|---------|---------|------|
| **Backend API يتأخر، الـ clients عاطلين** | متوسط | عالي | Mock server من OpenAPI (`prism` أو `mockoon`) يبدأ من اليوم 1 |
| **Mobile app rejected من App Store** | متوسط | عالي | Compliance review قبل التقديم: privacy declarations, IAP إذا applicable |
| **WebSocket لا يشتغل على بعض الشبكات في قطر** | منخفض | متوسط | Fallback للـ long-polling عبر REST، أو Server-Sent Events |
| **AR/RTL layout breaks في حالات معينة** | عالي | متوسط | QA مخصص للـ RTL مبكراً، test cases منفصلة |
| **Image upload يفشل من Mobile data** | عالي | متوسط | Compression محلي قبل الرفع + retry logic + chunk upload (لاحقاً) |
| **Token refresh race condition** | متوسط | عالي | Mutex/lock في الـ refresh logic (Web + Mobile) |
| **API breaking change يكسر mobile app** | عالي | كارثي | Versioning صارم + deprecation periods 12 شهر |
| **OTP SMS غير موثوق في قطر** | متوسط | عالي | استخدام Twilio + provider بديل (MessageBird) + WhatsApp OTP كـ fallback |
| **Meilisearch لا يدعم لهجة قطرية** | منخفض | منخفض | Synonyms dictionary مبني يدوياً + community contributions |
| **Push notifications تتأخر على iOS** | متوسط | متوسط | استخدام `time-sensitive` priority + APNs config صحيح |
| **Filament لا يكفي لميزة admin معينة** | منخفض | منخفض | Filament قابل للتخصيص، custom pages متاحة |
| **Flutter performance على أجهزة قديمة** | متوسط | متوسط | Profile mode testing على Android 8 + iPhone 8 |

---

## 🎯 توصيات استراتيجية نهائية

### قبل البدء بأي كود
1. **اتفقوا على OpenAPI Spec الأولي** — حتى لو ناقص، يبدأ النقاش
2. **اعملوا Mock Server** من اليوم الأول (Prism أو Mockoon)
3. **حددوا Design System مشترك** بين Next.js و Flutter (نفس الـ tokens: colors, spacing, typography)
4. **استأجروا UI/UX يفهم RTL** — Arabic-first design حاسم
5. **اشتروا الدومين والـ SSL مبكراً** — `qbazaar.qa` يتطلب أوراق في قطر

### أول 30 يوم
1. **Backend Foundation** (Sprint 0-3) كاملاً
2. **Next.js + Flutter Setup** بدون features (Auth فقط)
3. **CI/CD لكل الـ repos**
4. **Staging environment يعمل End-to-End**
5. **First demo داخلي** للمستثمرين/الشركاء

### Hidden Costs لا تنساها
- **Twilio:** ~$0.05 لكل SMS لقطر × آلاف المستخدمين = مكلف. حضر ميزانية
- **AWS S3 + CloudFront:** صور كثيرة = bandwidth bills. Cloudflare R2 أرخص بكثير (zero egress fees)
- **App Store Fees:** $99/year (iOS) + $25 lifetime (Android)
- **Push Notifications:** FCM مجاني، لكن iOS APNs certificates تحتاج تجديد سنوي
- **Meilisearch hosting:** self-hosted أو cloud (~$30/month)
- **Sentry:** Free tier محدود، الـ paid plan ~$26/month

### مكاسب يجب الاستفادة منها
- **Filament v5** = تطوير admin بسرعة 5x
- **Reverb** = WebSocket بدون رسوم Pusher ($50+/month)
- **Cloudflare R2** = توفير ~70% على bandwidth
- **Vercel للـ Next.js:** Free tier سخي للبداية
- **Laravel Cloud** (متاح الآن): hosting محسّن لـ Laravel، أبسط من Forge

---

## 📊 المخرجات النهائية بعد إكمال هذي الخطة

عند إكمال جميع المراحل، ستملك:

**Backend:**
- ✅ REST API كامل ومُوثّق (OpenAPI 3)
- ✅ WebSocket server (Reverb)
- ✅ Admin Panel كامل (Filament)
- ✅ Auto-moderation engine
- ✅ Audit logs لكل العمليات الحساسة
- ✅ Multi-channel notifications
- ✅ Production-ready مع monitoring

**Web:**
- ✅ موقع SEO-optimized بـ Next.js 15
- ✅ AR/EN كامل مع RTL
- ✅ Real-time chat
- ✅ PWA capabilities (إن أردت لاحقاً)

**Mobile:**
- ✅ iOS app على App Store
- ✅ Android app على Google Play
- ✅ Push notifications
- ✅ Offline-friendly
- ✅ Deep linking يعمل

**Infrastructure:**
- ✅ CI/CD لكل repo
- ✅ Staging + Production
- ✅ Monitoring (Sentry + Pulse)
- ✅ Backup strategy
- ✅ DR plan أساسي

---

**نهاية المخطط المعماري الشامل لمنصة QBazaar**