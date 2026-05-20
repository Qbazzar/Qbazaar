---
title: QBazaar — خطة الـ Backend التنفيذية (Laravel Edition)

---

# QBazaar — خطة الـ Backend التنفيذية (Laravel Edition)
### دليل العمل التفصيلي للمطور | مبني على Laravel 13 + Filament v5

> **الأساس:** المخرجات المطلوبة لكل صفحة (القسم 9 من الـ PRD):
> Endpoint · Request Schema · Response Schema · Error Codes · Authorization · Validation · Pagination · Sorting · Audit Log · Notification Triggers · Localization Keys

---

## 📋 جدول المحتويات

1. [الـ Stack النهائي](#1-الـ-stack-النهائي)
2. [لماذا Laravel لهذا المشروع؟](#2-لماذا-laravel-لهذا-المشروع)
3. [المبادئ المعمارية](#3-المبادئ-المعمارية)
4. [هيكل المشروع](#4-هيكل-المشروع)
5. [خريطة الـ Modules والتبعيات](#5-خريطة-الـ-modules-والتبعيات)
6. [خطة التنفيذ بالـ Sprints](#6-خطة-التنفيذ-بالـ-sprints)
7. [Cross-cutting Concerns](#7-cross-cutting-concerns)
8. [Definition of Done](#8-definition-of-done)
9. [الجدول الزمني](#9-الجدول-الزمني)

---

## 1. الـ Stack النهائي

| الطبقة | التقنية | المبرر |
|---------|---------|---------|
| **Framework** | Laravel 13 | الأحدث المستقر (مارس 2026) — Zero breaking changes من 12 |
| **Language** | PHP 8.3+ | إلزامي لـ Laravel 13، typed properties، readonly classes |
| **Database** | PostgreSQL 16 | JSONB للحقول الديناميكية، أداء أعلى للقراءة الموازية |
| **ORM** | Eloquent (built-in) | Active Record، علاقات قوية، Eager loading نظيف |
| **Cache / Sessions** | Redis 7 | Atomic locks، queue، rate limiting |
| **Queue** | Laravel Horizon + Redis | Dashboard متابعة، retries، failed jobs UI |
| **Search Engine** | Meilisearch via **Laravel Scout** | دعم عربي ممتاز، Scout يجرّد التكامل |
| **File Storage** | S3 / Cloudflare R2 (Flysystem) | متكامل مع Laravel Filesystem |
| **Media Library** | `spatie/laravel-medialibrary` | conversions، responsive images، collections |
| **Image Processing** | Intervention Image v3 | Sharp-like API في PHP |
| **WebSocket** | **Laravel Reverb** | First-party، Pusher-compatible، scaling جاهز |
| **Auth (API)** | **Laravel Sanctum** | مثالي للـ SPA و Mobile، token-based نظيف |
| **Auth (Admin Panel)** | Filament built-in + 2FA package | جاهز out of the box |
| **RBAC** | `spatie/laravel-permission` | Roles + Permissions، يتكامل مع Filament |
| **Audit Log** | `spatie/laravel-activitylog` | Auto-log on model events |
| **Translations (DB)** | `spatie/laravel-translatable` | حقول AR/EN في نفس column كـ JSON |
| **Validation** | Form Requests (built-in) | DRY، separation of concerns |
| **API Resources** | Eloquent API Resources | Transformations نظيفة |
| **API Docs** | Scribe | يولّد من PHPDoc + Form Requests تلقائياً |
| **Admin Panel** | **Filament v5** | 16 صفحة أدمن في أيام بدل أسابيع |
| **OTP / SMS** | Twilio عبر Laravel Notification Channel | يدعم أرقام قطر |
| **Testing** | Pest v3 | أنظف من PHPUnit، نفس القوة |
| **Code Quality** | Laravel Pint + PHPStan (level 8) | Style + Static Analysis |
| **Monitoring** | Sentry + **Laravel Pulse** | Pulse من Laravel نفسه |
| **Dev Inspection** | Laravel Telescope | dev فقط |
| **Containerization** | Laravel Sail (Docker) | dev setup بأمر واحد |
| **CI/CD** | GitHub Actions | Pest + Pint + Deploy |
| **Deployment** | Laravel Forge / Laravel Cloud / DO | Forge هو الـ standard للـ Laravel |

---

## 2. لماذا Laravel لهذا المشروع؟

### المكاسب الفورية

1. **Filament v5 يختصر 1.5 أسبوع** من تطوير لوحة الأدمن
   - 16 صفحة أدمن = 16 Filament Resource
   - CRUD + Tables + Filters + Forms مجاناً
   - Charts و Widgets جاهزة
2. **Spatie ecosystem** يختصر 2 أسبوع إضافية:
   - Permission package جاهز (لا تكتب RBAC من الصفر)
   - Activitylog جاهز (لا تكتب Audit من الصفر)
   - MediaLibrary جاهز (لا تكتب Image conversions من الصفر)
   - Translatable جاهز (لا تكتب AR/EN logic من الصفر)
3. **Laravel Reverb** أبسط من Socket.IO setup
4. **Eloquent + Form Requests** أسرع للكتابة من DTOs + Decorators
5. **Mature ecosystem** لكل ميزة في الـ PRD يوجد package جاهز

### المقارنة العملية (الوقت لكل Module)

| Module | NestJS | Laravel | الفرق |
|--------|---------|---------|--------|
| Auth | 5 أيام | 3 أيام | -40% (Sanctum) |
| RBAC | 3 أيام | 0.5 يوم | -83% (Spatie Permission) |
| Audit Log | 2 يوم | 0.5 يوم | -75% (Spatie Activitylog) |
| Image Upload + Conversions | 3 أيام | 1 يوم | -66% (Spatie MediaLibrary) |
| Admin Panel (16 pages) | 14 يوم | 5 أيام | -64% (Filament) |
| Translations | 2 يوم | 0.5 يوم | -75% (Spatie Translatable) |

**الإجمالي:** Laravel يوفّر ~3-4 أسابيع على نفس المشروع.

---

## 3. المبادئ المعمارية

### 3.1 قواعد لا تتغير

1. **API-First:** Scribe annotations قبل أي كود، endpoints موثّقة دائماً
2. **Form Request لكل endpoint:** ممنوع validation داخل Controller
3. **Service Layer لكل Module:** Controllers رفيعة، Services للمنطق، Actions للعمليات الذرية
4. **Repository Pattern اختياري:** Eloquent قوي، استخدم Repository فقط عند الحاجة لـ queries معقدة متكررة
5. **API Resources إلزامية:** ممنوع `return $model` مباشرة من Controller
6. **Queue كل شيء بطيء:** Notifications, Emails, Image processing, Webhooks
7. **Idempotency Keys** على publish/payment endpoints
8. **Soft Delete** على Users, Ads, Conversations عبر `SoftDeletes` trait
9. **Spatie Activitylog** على كل Model حساس (تلقائي)
10. **Versioned API:** `/api/v1/...` من اليوم الأول

### 3.2 RBAC عبر Spatie Permission

```php
// نموذج الصلاحيات
$permissions = [
    // Ads
    'ads.create', 'ads.edit.own', 'ads.edit.any',
    'ads.moderate', 'ads.delete.any',

    // Users
    'users.view', 'users.suspend', 'users.verify', 'users.delete',

    // Reports
    'reports.view', 'reports.resolve',

    // CMS
    'cms.edit',

    // Settings
    'settings.update',

    // Audit
    'audit.read',

    // Business
    'business.approve', 'business.reject',
];

// Roles كـ bundles
Role::create(['name' => 'user'])->givePermissionTo(['ads.create', 'ads.edit.own']);
Role::create(['name' => 'moderator'])->givePermissionTo([
    'ads.moderate', 'reports.view', 'reports.resolve', 'users.view',
]);
Role::create(['name' => 'admin'])->givePermissionTo(Permission::all());
Role::create(['name' => 'super_admin'])->givePermissionTo(Permission::all());
```

### 3.3 Translations Strategy

استخدام `spatie/laravel-translatable` للمحتوى الديناميكي:

```php
class Category extends Model
{
    use HasTranslations;
    public $translatable = ['name', 'description'];
}

// Usage
$category->name = ['ar' => 'سيارات', 'en' => 'Cars'];
$category->getTranslation('name', 'ar'); // "سيارات"
```

للنصوص الثابتة في الـ Backend: `resources/lang/ar/*` و `resources/lang/en/*` (built-in).

---

## 4. هيكل المشروع

```
qbazaar-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/V1/
│   │   │       ├── Auth/
│   │   │       │   ├── LoginController.php
│   │   │       │   ├── RegisterController.php
│   │   │       │   ├── OtpController.php
│   │   │       │   └── PasswordResetController.php
│   │   │       ├── Ads/
│   │   │       │   ├── AdController.php
│   │   │       │   ├── DraftController.php
│   │   │       │   └── MyAdsController.php
│   │   │       ├── Search/SearchController.php
│   │   │       ├── Categories/CategoryController.php
│   │   │       ├── Conversations/
│   │   │       ├── Favorites/
│   │   │       ├── Reports/
│   │   │       ├── Notifications/
│   │   │       └── ...
│   │   ├── Requests/
│   │   │   └── Api/V1/
│   │   │       ├── Auth/
│   │   │       │   ├── RegisterRequest.php
│   │   │       │   └── LoginRequest.php
│   │   │       ├── Ads/
│   │   │       │   ├── CreateAdRequest.php
│   │   │       │   └── UpdateAdRequest.php
│   │   │       └── ...
│   │   ├── Resources/
│   │   │   └── Api/V1/
│   │   │       ├── AdResource.php
│   │   │       ├── AdCollection.php
│   │   │       ├── UserResource.php
│   │   │       └── ...
│   │   └── Middleware/
│   │       ├── EnsureUserIsActive.php
│   │       ├── EnsurePhoneVerified.php
│   │       ├── LocaleMiddleware.php
│   │       └── ApiResponseWrapper.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Ad.php
│   │   ├── Category.php
│   │   ├── Location.php
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   ├── Offer.php
│   │   ├── Report.php
│   │   ├── Notification.php
│   │   ├── BusinessProfile.php
│   │   └── ...
│   │
│   ├── Services/
│   │   ├── Ads/
│   │   │   ├── AdPublisherService.php
│   │   │   ├── AdModerationService.php
│   │   │   └── AdExpirationService.php
│   │   ├── Auth/OtpService.php
│   │   ├── Search/SearchService.php
│   │   └── Messaging/ConversationService.php
│   │
│   ├── Actions/                # Single-purpose, callable
│   │   ├── Ads/PublishAdAction.php
│   │   ├── Ads/RenewAdAction.php
│   │   ├── Users/SuspendUserAction.php
│   │   └── Reports/ResolveReportAction.php
│   │
│   ├── Policies/
│   │   ├── AdPolicy.php
│   │   ├── ConversationPolicy.php
│   │   └── UserPolicy.php
│   │
│   ├── Observers/              # Audit + side effects
│   │   ├── AdObserver.php
│   │   └── UserObserver.php
│   │
│   ├── Events/
│   │   ├── AdPublished.php
│   │   ├── AdApproved.php
│   │   ├── MessageSent.php
│   │   └── OfferReceived.php
│   │
│   ├── Listeners/
│   │   ├── SendAdApprovalNotification.php
│   │   └── IndexAdInSearch.php
│   │
│   ├── Jobs/
│   │   ├── ProcessAdImages.php
│   │   ├── SendOtpJob.php
│   │   ├── ExpireOldAdsJob.php
│   │   └── ReindexSearchJob.php
│   │
│   ├── Notifications/
│   │   ├── AdApprovedNotification.php
│   │   ├── AdRejectedNotification.php
│   │   ├── NewMessageNotification.php
│   │   └── OtpNotification.php
│   │
│   ├── Filament/               # Admin Panel
│   │   ├── Resources/
│   │   │   ├── UserResource.php
│   │   │   ├── AdResource.php
│   │   │   ├── ReportResource.php
│   │   │   ├── CategoryResource.php
│   │   │   ├── BusinessApplicationResource.php
│   │   │   └── ...
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── SystemSettings.php
│   │   │   └── ModerationRules.php
│   │   ├── Widgets/
│   │   │   ├── StatsOverview.php
│   │   │   ├── AdsChart.php
│   │   │   └── PendingReportsWidget.php
│   │   └── Pages/Auth/AdminLogin.php
│   │
│   ├── Enums/
│   │   ├── AdStatus.php
│   │   ├── UserStatus.php
│   │   ├── PriceType.php
│   │   ├── ReportTarget.php
│   │   └── Language.php
│   │
│   ├── Broadcasting/           # WebSocket channels (Reverb)
│   │   └── ConversationChannel.php
│   │
│   ├── Search/                 # Scout configuration
│   │   └── AdSearchable.php (trait config)
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── EventServiceProvider.php
│       ├── AuthServiceProvider.php
│       └── FilamentServiceProvider.php
│
├── database/
│   ├── migrations/
│   ├── factories/
│   ├── seeders/
│   │   ├── CategorySeeder.php
│   │   ├── LocationSeeder.php (مدن وأحياء قطر)
│   │   ├── RoleAndPermissionSeeder.php
│   │   └── DemoDataSeeder.php
│   └── schema/
│       └── postgres-schema.sql
│
├── routes/
│   ├── api.php                 # يحمّل ملفات v1
│   ├── api_v1.php             # كل endpoints v1
│   ├── channels.php           # Broadcasting (Reverb)
│   ├── console.php            # Scheduled tasks
│   └── web.php                # Filament + Webhooks
│
├── config/
│   ├── qbazaar.php            # إعدادات خاصة بالمشروع
│   ├── permission.php         # Spatie
│   ├── activitylog.php
│   ├── media-library.php
│   ├── scout.php
│   ├── reverb.php
│   ├── sanctum.php
│   └── ...
│
├── lang/
│   ├── ar/
│   │   ├── auth.php
│   │   ├── validation.php
│   │   ├── messages.php
│   │   └── notifications.php
│   └── en/
│
├── tests/
│   ├── Feature/
│   │   └── Api/V1/
│   ├── Unit/
│   └── Pest.php
│
├── docker-compose.yml          # Sail
├── .env.example
└── composer.json
```

---

## 5. خريطة الـ Modules والتبعيات

```
Infrastructure (Sprint 0)
    ↓
Auth (Sanctum) ──┬─→ Users (Profiles + Spatie Permission)
                 │              ↓
                 │     Categories + Locations (Translatable)
                 │              ↓
                 │     Media Library (Spatie) ──→ Ads (مع Auto-Moderation)
                 │                                  ↓
                 │                          ┌──────┼──────┐
                 │                          ↓      ↓      ↓
                 │                       Search  Favorites  Messaging (Reverb)
                 │                      (Scout)              ↓
                 │                                         Offers
                 │                                            ↓
                 │                                         Reports
                 │                          ↓
                 │              Notifications (Channels + FCM/APNs)
                 │                          ↓
                 └─→ Filament Admin Panel (يستهلك كل ما سبق)
                              ↓
                          CMS + Help + Support
```

---

## 6. خطة التنفيذ بالـ Sprints

---

### 🏗️ Sprint 0 — Infrastructure & Foundation (أسبوع)

**الهدف:** بيئة جاهزة قبل أي feature.

#### المهام
- [ ] `composer create-project laravel/laravel qbazaar-backend`
- [ ] Laravel Sail setup (Postgres + Redis + Meilisearch + Mailpit)
- [ ] تنصيب الحزم الأساسية:
  ```bash
  composer require laravel/sanctum laravel/scout laravel/reverb laravel/horizon laravel/pulse
  composer require spatie/laravel-permission spatie/laravel-medialibrary
  composer require spatie/laravel-activitylog spatie/laravel-translatable
  composer require spatie/laravel-query-builder
  composer require meilisearch/meilisearch-php
  composer require intervention/image
  composer require filament/filament:"^5.0"
  composer require knuckleswtf/scribe
  composer require --dev pestphp/pest pestphp/pest-plugin-laravel
  composer require --dev laravel/telescope larastan/larastan
  ```
- [ ] إعداد `config/qbazaar.php` (constants: max images, ad lifetime, OTP TTL...)
- [ ] إعداد PHPStan level 8 + Laravel Pint
- [ ] إعداد Pest + factories باستراتيجية موحّدة
- [ ] إعداد Scribe `/api/docs`
- [ ] Global Exception Handler موحّد (JSON responses)
- [ ] Global API Response wrapper middleware
- [ ] Locale Middleware (من `Accept-Language` header)
- [ ] Health check endpoint `/up` (Laravel 13 built-in)
- [ ] CI: GitHub Actions (Pint + PHPStan + Pest)
- [ ] Sentry integration
- [ ] Rate Limiter middleware (Redis-backed)
- [ ] `.env.example` كامل

#### المُخرَج
```bash
git clone ... && cd qbazaar-backend
cp .env.example .env
./vendor/bin/sail up -d
sail artisan migrate --seed
sail artisan serve
# يشتغل على localhost:80, Meilisearch على 7700, Reverb على 8080
```

---

### 🔐 Sprint 1 — Auth Module (3 أيام)

**الصفحات المغطاة:** Login, Register, OTP, Forgot Password, Account Security

#### الـ Routes (`routes/api_v1.php`)
```php
Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);
    Route::post('logout', LogoutController::class)->middleware('auth:sanctum');
    Route::post('refresh', RefreshTokenController::class);

    Route::post('send-otp', [OtpController::class, 'send']);
    Route::post('verify-otp', [OtpController::class, 'verify']);
    Route::post('resend-otp', [OtpController::class, 'resend'])
         ->middleware('throttle:3,1'); // 3 per minute

    Route::post('forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('reset-password', [PasswordResetController::class, 'reset']);

    Route::post('send-email-verification', SendEmailVerificationController::class)
         ->middleware('auth:sanctum');
});
```

#### Form Request مثال
```php
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name'    => ['required', 'string', 'min:3', 'max:80'],
            'email'        => ['required', 'email:rfc,dns', 'unique:users,email'],
            'phone'        => ['required', 'regex:/^\+974[0-9]{8}$/', 'unique:users,phone'],
            'password'     => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'language'     => ['nullable', Rule::enum(Language::class)],
            'accepted_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('validation.qatar_phone'),
        ];
    }
}
```

#### Validation Rules حرجة
- Phone: `+974XXXXXXXX` regex إلزامي
- Password: 8+ chars, uppercase + lowercase + number + symbol
- Email: RFC + DNS check
- OTP: 6 digits, 5 دقائق صلاحية, 3 محاولات
- Resend OTP: cooldown 60s، max 5/hour

#### Error Codes (موحدة في `app/Exceptions/ErrorCodes.php`)
```php
enum ErrorCode: string {
    case INVALID_CREDENTIALS    = 'AUTH_001';
    case ACCOUNT_SUSPENDED      = 'AUTH_002';
    case PHONE_NOT_VERIFIED     = 'AUTH_003';
    case OTP_EXPIRED            = 'AUTH_004';
    case OTP_INVALID            = 'AUTH_005';
    case RATE_LIMIT_EXCEEDED    = 'AUTH_006';
    case EMAIL_ALREADY_EXISTS   = 'AUTH_007';
    case PHONE_ALREADY_EXISTS   = 'AUTH_008';
    case TOKEN_EXPIRED          = 'AUTH_009';
    case TOKEN_INVALID          = 'AUTH_010';
}
```

#### Authorization
- جميع endpoints عامة عدا `logout` و `send-email-verification`
- المستخدمون المعلّقون يُرفضون عند login عبر middleware

#### Audit Log Triggers (Spatie Activitylog)
```php
// app/Observers/UserObserver.php
class UserObserver
{
    public function updated(User $user): void
    {
        if ($user->isDirty('status')) {
            activity('user')
                ->performedOn($user)
                ->withProperties(['old' => $user->getOriginal('status'), 'new' => $user->status])
                ->log('user.status.changed');
        }
    }
}
```

#### Notification Triggers
- Welcome email عند register
- OTP via SMS عند `send-otp` (Twilio Channel)
- Password reset email
- Security alert: تغيير password / دخول من device جديد

#### Migration
```php
Schema::create('users', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('email')->unique();
    $table->string('phone')->unique();
    $table->string('password');
    $table->string('full_name');
    $table->string('account_type')->default('private');
    $table->string('status')->default('active');
    $table->boolean('email_verified')->default(false);
    $table->boolean('phone_verified')->default(false);
    $table->string('language', 2)->default('ar');
    $table->timestamp('last_login_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['status', 'deleted_at']);
});

Schema::create('otp_codes', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('phone')->index();
    $table->string('code_hash');
    $table->unsignedTinyInteger('attempts')->default(0);
    $table->timestamp('expires_at');
    $table->timestamp('used_at')->nullable();
    $table->timestamps();
});
```

---

### 👤 Sprint 2 — Users Module (3 أيام)

**الصفحات المغطاة:** Profile, Public Profile, Account Dashboard, Settings, Privacy, Verification Center, Blocked Users

#### Endpoints
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('account')->group(function () {
        Route::get('summary', [AccountController::class, 'summary']);
        Route::get('profile', [AccountController::class, 'show']);
        Route::put('profile', [AccountController::class, 'update']);
        Route::put('password', [AccountController::class, 'updatePassword']);

        Route::get('sessions', [SessionsController::class, 'index']);
        Route::delete('sessions/{id}', [SessionsController::class, 'destroy']);

        Route::get('verification-status', [VerificationController::class, 'status']);
        Route::get('privacy-settings', [PrivacyController::class, 'show']);
        Route::put('privacy-settings', [PrivacyController::class, 'update']);

        Route::post('data-export-request', [PrivacyController::class, 'exportRequest']);
        Route::post('deactivate', [AccountController::class, 'deactivate']);
        Route::delete('delete-request', [AccountController::class, 'deleteRequest']);

        Route::get('blocked-users', [BlockedUsersController::class, 'index']);
    });

    Route::post('users/{user}/block', [BlockController::class, 'block']);
    Route::delete('users/{user}/block', [BlockController::class, 'unblock']);

    Route::post('uploads/avatar', AvatarUploadController::class);
});

Route::get('users/{user}/public-profile', PublicProfileController::class);
Route::get('users/{user}/ads', UserAdsController::class);
```

#### Policies
```php
// AccountPolicy.php
public function update(User $user, User $target): bool
{
    return $user->id === $target->id;
}

public function block(User $user, User $target): bool
{
    return $user->id !== $target->id
        && !$target->hasRole(['admin', 'super_admin']);
}
```

#### Avatar Upload via MediaLibrary
```php
class User extends Authenticatable implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(150)->height(150)->sharpen(10);
    }
}

// In controller
$user->addMediaFromRequest('avatar')->toMediaCollection('avatar');
```

#### Audit Log
- تغيير email/phone (مع old/new)
- طلب حذف حساب
- تعطيل حساب
- تصدير بيانات (GDPR-like)

---

### 🗂️ Sprint 3 — Categories & Locations (يومين)

#### Endpoints
```php
Route::get('categories/tree', [CategoryController::class, 'tree'])->middleware('cache.headers:public;max_age=3600');
Route::get('categories/main', [CategoryController::class, 'main']);
Route::get('categories/{category:slug}/stats', [CategoryController::class, 'stats']);
Route::get('categories/{category:slug}/filters', [CategoryController::class, 'filters']);
Route::get('categories/{category:slug}/fields', [CategoryController::class, 'fields']);
Route::get('locations/qatar', [LocationController::class, 'qatar']);
```

#### Model مع Translatable
```php
class Category extends Model
{
    use HasTranslations, HasUlids;

    public $translatable = ['name', 'description'];

    protected $casts = [
        'custom_fields'  => 'array',
        'custom_filters' => 'array',
        'is_active'      => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
```

#### Caching Strategy
```php
public function tree(): JsonResponse
{
    $tree = Cache::remember('categories.tree', 3600, function () {
        return Category::with('children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    });

    return CategoryResource::collection($tree)->response();
}

// عند تعديل من Filament:
// Cache::forget('categories.tree');
```

#### Seeder
```php
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'cars', 'name' => ['ar' => 'سيارات', 'en' => 'Cars'], 'icon' => 'car'],
            ['slug' => 'real-estate', 'name' => ['ar' => 'عقارات', 'en' => 'Real Estate'], 'icon' => 'home'],
            // ...
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }
    }
}
```

---

### 📤 Sprint 4 — Uploads via MediaLibrary (يومين)

**Spatie MediaLibrary يختصر تنفيذ كامل لـ Image Service.**

#### Setup
```php
class Ad extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->onlyKeepLatest(10);  // max 10 صور
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')->width(200)->height(200)->format('webp');
        $this->addMediaConversion('medium')->width(640)->format('webp');
        $this->addMediaConversion('large')->width(1024)->format('webp');
        $this->addMediaConversion('original-webp')->width(1920)->format('webp');
    }
}
```

#### Upload Controller
```php
class AdImageController
{
    public function store(UploadImagesRequest $request, Ad $ad)
    {
        Gate::authorize('update', $ad);

        $media = $ad->addMultipleMediaFromRequest(['images'])
            ->each(fn ($file) => $file->toMediaCollection('images'));

        return MediaResource::collection($ad->getMedia('images'));
    }

    public function destroy(Ad $ad, Media $media)
    {
        Gate::authorize('update', $ad);
        $media->delete();
        return response()->noContent();
    }

    public function reorder(ReorderImagesRequest $request, Ad $ad)
    {
        Media::setNewOrder($request->image_ids);
        return response()->noContent();
    }
}
```

#### Conversions Async
في `config/media-library.php`:
```php
'queue_conversions_by_default' => true,
```
→ كل التحويلات تذهب لـ Horizon Queue تلقائياً.

#### قواعد الأمان
- Form Request: `'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240']` (10MB)
- Magic byte validation عبر Intervention Image قبل الحفظ
- pHash للصور (للكشف عن التكرار)

---

### 📝 Sprint 5 — Ads Module (أسبوعين)

**أهم Module في النظام.**

#### Endpoints
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('ads/draft', [DraftController::class, 'store']);
    Route::put('ads/draft/{ad}', [DraftController::class, 'update']);
    Route::get('ads/draft/{ad}', [DraftController::class, 'show']);
    Route::get('ads/draft/{ad}/preview', [DraftController::class, 'preview']);
    Route::put('ads/draft/{ad}/images', [DraftController::class, 'reorderImages']);

    Route::post('ads/{ad}/publish', PublishAdController::class)
         ->middleware('idempotent:publish');

    Route::put('ads/{ad}', [AdController::class, 'update']);
    Route::delete('ads/{ad}', [AdController::class, 'destroy']);
    Route::post('ads/{ad}/mark-sold', [AdController::class, 'markSold']);
    Route::post('ads/{ad}/renew', [AdController::class, 'renew']);

    Route::get('account/ads', [MyAdsController::class, 'index']);
    Route::get('account/drafts', [DraftController::class, 'index']);
});

// Public
Route::get('ads/{ad}', [AdController::class, 'show']);
Route::post('ads/{ad}/view', [AdController::class, 'trackView'])->middleware('throttle:60,1');
Route::get('ads/{ad}/similar', [AdController::class, 'similar']);
Route::get('ads/latest', [AdController::class, 'latest']);
Route::get('ads/featured', [AdController::class, 'featured']);
```

#### Form Request – CreateAdRequest
```php
class CreateAdRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id'        => ['required', 'ulid', 'exists:categories,id'],
            'title'              => ['required', 'array'],
            'title.ar'           => ['required', 'string', 'min:5', 'max:100'],
            'title.en'           => ['nullable', 'string', 'min:5', 'max:100'],
            'description'        => ['required', 'array'],
            'description.ar'     => ['required', 'string', 'min:20', 'max:3000'],
            'description.en'     => ['nullable', 'string', 'min:20', 'max:3000'],
            'price'              => ['required', 'numeric', 'min:0', 'max:99999999'],
            'price_type'         => ['required', Rule::enum(PriceType::class)],
            'condition'          => ['nullable', Rule::enum(Condition::class)],
            'location_id'        => ['required', 'ulid', 'exists:locations,id'],
            'custom_fields'      => ['array'],
            'contact_preferences' => ['required', 'array'],
            'contact_preferences.show_phone' => ['boolean'],
            'contact_preferences.allow_chat' => ['boolean'],
            'image_ids'          => ['required', 'array', 'min:1', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Validate custom_fields ضد category.custom_fields schema
        $category = Category::find($this->category_id);
        if ($category) {
            // dynamic validation logic
        }
    }
}
```

#### State Machine
```php
enum AdStatus: string {
    case DRAFT     = 'draft';
    case PENDING   = 'pending';
    case ACTIVE    = 'active';
    case REJECTED  = 'rejected';
    case SOLD      = 'sold';
    case EXPIRED   = 'expired';
    case BLOCKED   = 'blocked';

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::DRAFT, self::PENDING], [self::DRAFT, self::ACTIVE]   => true,
            [self::PENDING, self::ACTIVE], [self::PENDING, self::REJECTED] => true,
            [self::ACTIVE, self::SOLD], [self::ACTIVE, self::EXPIRED], [self::ACTIVE, self::BLOCKED] => true,
            [self::EXPIRED, self::ACTIVE] => true,  // renew
            [self::REJECTED, self::PENDING] => true,  // edit & resubmit
            default => false,
        };
    }
}
```

#### Auto-Moderation Action
```php
class ModerateAdAction
{
    public function __construct(
        private ModerationRulesService $rules,
        private ImageHashService $imageHash,
    ) {}

    public function execute(Ad $ad): AdStatus
    {
        // 1. فحص banned words في title + description
        if ($this->rules->containsBannedWords($ad)) {
            return AdStatus::PENDING;
        }

        // 2. اكتشاف أرقام هاتف في النص
        if ($this->rules->containsPhoneNumber($ad->description)) {
            return AdStatus::PENDING;
        }

        // 3. اكتشاف روابط خارجية
        if ($this->rules->containsExternalLinks($ad->description)) {
            return AdStatus::PENDING;
        }

        // 4. pHash check للصور (duplicate detection)
        if ($this->imageHash->hasDuplicates($ad)) {
            return AdStatus::PENDING;
        }

        return AdStatus::ACTIVE;
    }
}
```

#### Publish Action
```php
class PublishAdAction
{
    public function __construct(
        private ModerateAdAction $moderate,
        private SearchService $search,
    ) {}

    public function execute(Ad $ad): Ad
    {
        DB::transaction(function () use ($ad) {
            $status = $this->moderate->execute($ad);

            $ad->update([
                'status'       => $status,
                'published_at' => now(),
                'expires_at'   => now()->addDays(30),
            ]);

            if ($status === AdStatus::ACTIVE) {
                event(new AdPublished($ad));  // → indexes in Meilisearch, notifies
            }
        });

        return $ad;
    }
}
```

#### Policies
```php
class AdPolicy
{
    public function view(?User $user, Ad $ad): bool
    {
        if ($ad->status === AdStatus::ACTIVE) return true;
        return $user && ($user->id === $ad->owner_id || $user->can('ads.moderate'));
    }

    public function update(User $user, Ad $ad): bool
    {
        return $user->id === $ad->owner_id || $user->can('ads.edit.any');
    }

    public function delete(User $user, Ad $ad): bool
    {
        return $user->id === $ad->owner_id || $user->can('ads.delete.any');
    }
}
```

#### Pagination + Sorting via Spatie QueryBuilder
```php
public function index(Request $request)
{
    $ads = QueryBuilder::for(Ad::class)
        ->allowedFilters([
            'category_id', 'location_id', 'condition',
            AllowedFilter::scope('price_between'),
        ])
        ->allowedSorts(['created_at', 'price', 'views'])
        ->defaultSort('-created_at')
        ->where('status', AdStatus::ACTIVE)
        ->paginate($request->integer('limit', 20));

    return AdResource::collection($ads);
}
```

#### Migration
```php
Schema::create('ads', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('slug');
    $table->foreignUlid('owner_id')->constrained('users');
    $table->foreignUlid('category_id')->constrained();
    $table->foreignUlid('location_id')->constrained();
    $table->json('title');         // translatable
    $table->json('description');   // translatable
    $table->decimal('price', 15, 2);
    $table->string('price_type');
    $table->string('condition')->nullable();
    $table->json('custom_fields')->nullable();
    $table->json('contact_preferences');
    $table->string('status')->default('draft');
    $table->unsignedInteger('views')->default(0);
    $table->timestamp('published_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('sold_at')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['status', 'deleted_at', 'published_at']);
    $table->index(['category_id', 'status', 'published_at']);
    $table->index(['location_id', 'status', 'published_at']);
    $table->index(['owner_id', 'status']);
});
```

#### Audit Log (تلقائي عبر Spatie)
```php
class Ad extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'price', 'title', 'rejection_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

#### Scheduled Job — Expire Old Ads
```php
// app/Console/Kernel.php (Laravel 13 يستخدم routes/console.php)
Schedule::job(new ExpireOldAdsJob)->daily();
```

---

### 🔎 Sprint 6 — Search via Laravel Scout + Meilisearch (3 أيام)

#### Setup
```php
// app/Models/Ad.php
use Laravel\Scout\Searchable;

class Ad extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id'           => $this->id,
            'title_ar'     => $this->getTranslation('title', 'ar'),
            'title_en'     => $this->getTranslation('title', 'en'),
            'description_ar' => $this->getTranslation('description', 'ar'),
            'description_en' => $this->getTranslation('description', 'en'),
            'price'        => (float) $this->price,
            'price_type'   => $this->price_type,
            'condition'    => $this->condition,
            'category_id'  => $this->category_id,
            'location_id'  => $this->location_id,
            'seller_type'  => $this->owner->account_type,
            'has_delivery' => $this->custom_fields['has_delivery'] ?? false,
            'created_at'   => $this->created_at->timestamp,
            'is_featured'  => $this->is_featured,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === AdStatus::ACTIVE && !$this->trashed();
    }
}
```

#### Meilisearch Index Settings
```php
// في AppServiceProvider أو dedicated command
$client->index('ads')->updateSettings([
    'searchableAttributes' => ['title_ar', 'title_en', 'description_ar', 'description_en'],
    'filterableAttributes' => ['category_id', 'location_id', 'price', 'condition', 'seller_type', 'has_delivery'],
    'sortableAttributes'   => ['price', 'created_at'],
    'synonyms' => [
        'سيارة' => ['car', 'auto'],
        'شقة'   => ['apartment', 'flat'],
        // ...
    ],
    'rankingRules' => ['typo', 'words', 'proximity', 'attribute', 'sort', 'exactness'],
]);
```

#### Endpoints
```php
Route::get('search', SearchController::class);
Route::get('search/suggestions', SearchSuggestionsController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('saved-searches', [SavedSearchController::class, 'index']);
    Route::post('saved-searches', [SavedSearchController::class, 'store']);
    Route::put('saved-searches/{search}', [SavedSearchController::class, 'update']);
    Route::delete('saved-searches/{search}', [SavedSearchController::class, 'destroy']);
});
```

#### Search Controller
```php
class SearchController
{
    public function __invoke(SearchRequest $request)
    {
        $ads = Ad::search($request->q ?? '')
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->location_id, fn ($q, $id) => $q->where('location_id', $id))
            ->when($request->min_price, fn ($q, $p) => $q->where('price', '>=', $p))
            ->when($request->max_price, fn ($q, $p) => $q->where('price', '<=', $p))
            ->when($request->condition, fn ($q, $c) => $q->where('condition', $c))
            ->when($request->sort === 'priceAsc', fn ($q) => $q->orderBy('price', 'asc'))
            ->when($request->sort === 'priceDesc', fn ($q) => $q->orderBy('price', 'desc'))
            ->when($request->sort === 'newest', fn ($q) => $q->orderBy('created_at', 'desc'))
            ->paginate($request->integer('limit', 20));

        return AdResource::collection($ads);
    }
}
```

#### Saved Searches Alerts (Scheduled Job)
```php
class CheckSavedSearchesJob
{
    public function handle(): void
    {
        SavedSearch::with('user')
            ->where('alerts_enabled', true)
            ->chunk(100, function ($searches) {
                foreach ($searches as $search) {
                    $newAds = Ad::search($search->query)
                        ->where('created_at', '>', $search->last_checked_at?->timestamp)
                        ->get();

                    if ($newAds->isNotEmpty()) {
                        $search->user->notify(new SavedSearchMatchNotification($search, $newAds));
                        $search->update(['last_checked_at' => now()]);
                    }
                }
            });
    }
}
```

---

### ❤️ Sprint 7 — Favorites + Recently Viewed (يوم واحد)

#### Endpoints
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('favorites', [FavoriteController::class, 'index']);
    Route::post('favorites', [FavoriteController::class, 'store']);
    Route::delete('favorites/{ad}', [FavoriteController::class, 'destroy']);

    Route::get('account/recently-viewed', [RecentlyViewedController::class, 'index']);
    Route::delete('account/recently-viewed', [RecentlyViewedController::class, 'clear']);
});
```

#### Migrations
```php
Schema::create('favorites', function (Blueprint $table) {
    $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUlid('ad_id')->constrained()->cascadeOnDelete();
    $table->timestamp('created_at');
    $table->primary(['user_id', 'ad_id']);
});

Schema::create('recently_viewed', function (Blueprint $table) {
    $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
    $table->foreignUlid('ad_id')->constrained()->cascadeOnDelete();
    $table->timestamp('viewed_at');
    $table->primary(['user_id', 'ad_id']);
    $table->index(['user_id', 'viewed_at']);
});
```

Recently Viewed: cap at 50 per user via Job يومي ينظف الأقدم.

---

### 💬 Sprint 8 — Messaging via Laravel Reverb (أسبوعين)

**Reverb هو first-party WebSocket من Laravel، Pusher-compatible.**

#### REST Endpoints
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::post('conversations', [ConversationController::class, 'store']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
         ->middleware('throttle:30,1');
    Route::put('conversations/{conversation}/read', [ConversationController::class, 'markRead']);
    Route::post('conversations/{conversation}/report', [ConversationController::class, 'report']);
});
```

#### Broadcasting Channel (`routes/channels.php`)
```php
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    return $conversation && in_array($user->id, [$conversation->buyer_id, $conversation->seller_id]);
});
```

#### Event
```php
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->message->conversation_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => new MessageResource($this->message),
        ];
    }
}
```

#### Send Message
```php
class MessageController
{
    public function store(SendMessageRequest $request, Conversation $conversation, ContentSafetyService $safety)
    {
        Gate::authorize('participate', $conversation);

        // فحص الـ content
        $flagged = $safety->scan($request->content);

        $message = $conversation->messages()->create([
            'sender_id'  => $request->user()->id,
            'type'       => MessageType::TEXT,
            'content'    => $request->content,
            'flagged'    => $flagged->isFlagged,
            'flag_reason'=> $flagged->reason,
        ]);

        $conversation->update(['last_message_at' => now()]);

        broadcast(new MessageSent($message))->toOthers();

        // Push notification للطرف الآخر (لو offline)
        $recipient = $conversation->otherParticipant($request->user());
        $recipient->notify(new NewMessageNotification($message));

        return new MessageResource($message);
    }
}
```

#### Content Safety Service
```php
class ContentSafetyService
{
    public function scan(string $content): ContentSafetyResult
    {
        $flags = [];

        if (preg_match('/\+?[0-9]{8,}/', $content)) $flags[] = 'phone_number';
        if (preg_match('/https?:\/\/(?!qbazaar\.com)/', $content)) $flags[] = 'external_link';
        if (str_contains(strtolower($content), 'otp')) $flags[] = 'otp_request';

        $bannedWords = Cache::remember('banned_words', 3600, fn () => ModerationRule::active()->pluck('pattern'));
        foreach ($bannedWords as $word) {
            if (str_contains(strtolower($content), strtolower($word))) {
                $flags[] = "banned_word:{$word}";
            }
        }

        return new ContentSafetyResult(
            isFlagged: !empty($flags),
            reason: implode(',', $flags),
        );
    }
}
```

#### Reverb Configuration
```bash
# .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=qbazaar
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

# Production: Redis adapter للـ scaling
REVERB_SCALING_ENABLED=true
```

#### Run Reverb
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
# في production عبر Supervisor
```

---

### 🤝 Sprint 9 — Offers Module (يوم واحد)

#### Endpoints
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('offers', [OfferController::class, 'store']);
    Route::get('offers/{offer}', [OfferController::class, 'show']);
    Route::post('offers/{offer}/accept', [OfferController::class, 'accept']);
    Route::post('offers/{offer}/reject', [OfferController::class, 'reject']);
    Route::post('offers/{offer}/counter', [OfferController::class, 'counter']);
});
```

#### Make Offer Action
```php
class MakeOfferAction
{
    public function execute(User $buyer, Ad $ad, float $amount, ?string $message = null): Offer
    {
        // غير مسموح offer على إعلان مباع/محذوف
        abort_unless($ad->status === AdStatus::ACTIVE, 422, __('errors.ad_not_active'));
        abort_if($ad->owner_id === $buyer->id, 422, __('errors.cannot_offer_own_ad'));

        $conversation = Conversation::firstOrCreate([
            'ad_id'     => $ad->id,
            'buyer_id'  => $buyer->id,
            'seller_id' => $ad->owner_id,
        ]);

        $offer = Offer::create([
            'conversation_id' => $conversation->id,
            'ad_id'           => $ad->id,
            'buyer_id'        => $buyer->id,
            'seller_id'       => $ad->owner_id,
            'amount'          => $amount,
            'message'         => $message,
            'status'          => OfferStatus::PENDING,
            'expires_at'      => now()->addDays(7),
        ]);

        // إنشاء رسالة من نوع OFFER في الـ conversation
        $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'type'      => MessageType::OFFER,
            'content'   => __('messages.offer_made', ['amount' => $amount]),
            'metadata'  => ['offer_id' => $offer->id, 'amount' => $amount],
        ]);

        $ad->owner->notify(new OfferReceivedNotification($offer));

        return $offer;
    }
}
```

---

### 🚨 Sprint 10 — Reports + Notifications (أسبوع)

#### Reports
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('reports/ad', [ReportController::class, 'reportAd']);
    Route::post('reports/user', [ReportController::class, 'reportUser']);
    Route::post('reports/conversation', [ReportController::class, 'reportConversation']);
    Route::get('account/reports', [ReportController::class, 'myReports']);
});
```

#### Notifications via Laravel Channels
Laravel Notifications يدعم multiple channels جاهز:

```php
class AdApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ad $ad) {}

    public function via(User $user): array
    {
        $prefs = $user->notification_preferences;
        $channels = ['database'];

        if ($prefs['push_enabled']) $channels[] = FcmChannel::class;
        if ($prefs['email_enabled']) $channels[] = 'mail';

        return $channels;
    }

    public function toDatabase(User $user): array
    {
        return [
            'type'    => 'ad.approved',
            'title'   => __('notifications.ad_approved.title', [], $user->language),
            'body'    => __('notifications.ad_approved.body', ['title' => $this->ad->title], $user->language),
            'data'    => ['ad_id' => $this->ad->id],
        ];
    }

    public function toFcm(User $user): FcmMessage
    {
        return FcmMessage::create()
            ->setNotification(new FcmNotification(
                title: __('notifications.ad_approved.title', [], $user->language),
                body:  __('notifications.ad_approved.body', ['title' => $this->ad->title], $user->language),
            ))
            ->setData(['ad_id' => $this->ad->id, 'type' => 'ad.approved']);
    }
}
```

#### Notification Preferences
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('account/notification-preferences', [NotificationPrefController::class, 'show']);
    Route::put('account/notification-preferences', [NotificationPrefController::class, 'update']);

    Route::post('device-tokens', [DeviceTokenController::class, 'register']);
    Route::delete('device-tokens/{token}', [DeviceTokenController::class, 'unregister']);
});
```

---

### 🛠️ Sprint 11 — Admin Panel via Filament (أسبوع)

**Filament v5 = أسرع طريقة لبناء الـ 16 صفحة أدمن.**

#### Setup
```bash
php artisan filament:install --panels
php artisan make:filament-user
```

#### Filament Resource مثال — AdResource
```php
class AdResource extends Resource
{
    protected static ?string $model = Ad::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Marketplace';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title.ar')->label('العنوان (عربي)')->required(),
            TextInput::make('title.en')->label('Title (English)'),
            Textarea::make('description.ar')->label('الوصف (عربي)')->required(),
            Select::make('category_id')->relationship('category', 'name->ar')->required(),
            Select::make('location_id')->relationship('location', 'name->ar')->required(),
            TextInput::make('price')->numeric()->prefix('QAR')->required(),
            Select::make('status')->options(AdStatus::class)->required(),
            SpatieMediaLibraryFileUpload::make('images')->collection('images')->multiple(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->copyable(),
                ImageColumn::make('cover')->square(),
                TextColumn::make('title.ar')->searchable()->limit(40),
                TextColumn::make('owner.full_name')->searchable(),
                TextColumn::make('category.name.ar'),
                TextColumn::make('price')->money('QAR'),
                TextColumn::make('status')->badge()->color(fn ($state) => match($state) {
                    'active'   => 'success',
                    'pending'  => 'warning',
                    'rejected' => 'danger',
                    default    => 'gray',
                }),
                TextColumn::make('views')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(AdStatus::class),
                SelectFilter::make('category')->relationship('category', 'name->ar'),
                Filter::make('flagged')->query(fn ($q) => $q->where('flagged', true)),
            ])
            ->actions([
                Action::make('approve')
                    ->visible(fn (Ad $ad) => $ad->status === AdStatus::PENDING)
                    ->action(fn (Ad $ad) => app(ApproveAdAction::class)->execute($ad))
                    ->requiresConfirmation()
                    ->color('success'),

                Action::make('reject')
                    ->visible(fn (Ad $ad) => $ad->status === AdStatus::PENDING)
                    ->form([Textarea::make('reason')->required()])
                    ->action(fn (Ad $ad, array $data) =>
                        app(RejectAdAction::class)->execute($ad, $data['reason']))
                    ->color('danger'),

                Action::make('block')
                    ->visible(fn (Ad $ad) => $ad->status === AdStatus::ACTIVE)
                    ->form([Textarea::make('reason')->required()])
                    ->action(fn (Ad $ad, array $data) =>
                        app(BlockAdAction::class)->execute($ad, $data['reason']))
                    ->color('warning'),
            ]);
    }
}
```

**هذي الـ Resource ~80 سطر تغطي:**
- Index page مع filters + search + pagination
- Form للإنشاء/التعديل
- Approve/Reject/Block actions مع audit log تلقائي
- Media uploads
- Status badges ملونة

#### الـ 16 Resource المطلوبة
1. `UserResource` — إدارة المستخدمين
2. `AdResource` — مراجعة الإعلانات
3. `ReportResource` — إدارة البلاغات
4. `CategoryResource` — إدارة الأقسام
5. `LocationResource` — إدارة المواقع
6. `BusinessApplicationResource` — طلبات الأعمال
7. `SupportTicketResource` — تذاكر الدعم
8. `ModerationRuleResource` — قواعد الإشراف
9. `CmsPageResource` — صفحات CMS
10. `HelpArticleResource` — مقالات المساعدة
11. `NotificationTemplateResource` — قوالب الإشعارات
12. `PromotionResource` (Phase 2)
13. `TransactionResource` (Phase 2)
14. `AdminUserResource` — إدارة الأدمن

#### Custom Pages
- `Dashboard` (الافتراضي) — Widgets للـ KPIs
- `SystemSettings` — إعدادات النظام (key-value editor)
- `AuditLogsPage` — قراءة فقط للسجلات

#### Dashboard Widgets
```php
class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Ads', Ad::where('status', AdStatus::ACTIVE)->count())
                ->description('Total live listings')
                ->chart([7, 4, 5, 12, 8, 14, 22, 18, 21])
                ->color('success'),

            Stat::make('Pending Moderation', Ad::where('status', AdStatus::PENDING)->count())
                ->color('warning'),

            Stat::make('Open Reports', Report::where('status', 'open')->count())
                ->color('danger'),

            Stat::make('New Users (24h)', User::where('created_at', '>', now()->subDay())->count()),
        ];
    }
}
```

#### Admin Authentication
- Filament يدعم 2FA via plugin: `composer require stephenjude/filament-two-factor-authentication`
- Custom guard للأدمن منفصل عن users guard (في `config/auth.php`)

---

### 📚 Sprint 12 — CMS + Help + Support (يومين)

#### Endpoints
```php
Route::get('cms/pages/{slug}', [CmsController::class, 'show']);
Route::get('cms/help-articles', [HelpController::class, 'index']);
Route::get('cms/help-articles/{slug}', [HelpController::class, 'show']);
Route::post('support/tickets', [SupportController::class, 'store']);
```

#### Models
```php
class CmsPage extends Model
{
    use HasTranslations, HasUlids, LogsActivity;
    public $translatable = ['title', 'body'];
}

class HelpArticle extends Model
{
    use HasTranslations, HasUlids;
    public $translatable = ['title', 'body'];
}

class SupportTicket extends Model
{
    use HasUlids;

    public function messages(): HasMany { return $this->hasMany(SupportMessage::class); }
}
```

---

### 🏢 Sprint 13 — Business Sellers (5 أيام) — MVP اختياري

#### Endpoints
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('business/applications', [BusinessApplicationController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:business'])->prefix('business')->group(function () {
    Route::get('dashboard/summary', [BusinessDashboardController::class, 'summary']);
    Route::get('ads', [BusinessAdsController::class, 'index']);
    Route::get('subscription', [BusinessSubscriptionController::class, 'show']);
});

Route::get('stores/{slug}', [StorefrontController::class, 'show']);
Route::get('stores/{slug}/ads', [StorefrontController::class, 'ads']);
```

---

## 🚀 Phase 2 Sprints (بعد إطلاق MVP)

### Sprint 14 — Payments (أسبوعين)
- **Provider:** Tap Payments أو HyperPay (يدعمان قطر)
- Laravel Cashier ليس مناسب هنا (مصمم لـ Stripe subscriptions)
- بناء custom integration:
  - Webhook signature verification إلزامي
  - Transactions، Disputes، Refunds، Payouts
  - Idempotency keys على جميع payment operations

### Sprint 15 — Promotions (أسبوع)
- Featured/Bump/Top placement packages
- Billing & Invoices (DomPDF لإصدار الفواتير)
- Subscription management

### Sprint 16 — Delivery Addresses (3 أيام)
- Address management
- Order tracking
- تكامل مع شركات الشحن القطرية

---

## 7. Cross-cutting Concerns

### 7.1 API Response Format (موحّد)

عبر `app/Http/Middleware/ApiResponseWrapper.php`:

```json
// Success
{
  "success": true,
  "data": { /* ... */ },
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 156,
    "has_more": true
  }
}

// Error
{
  "success": false,
  "error": {
    "code": "AUTH_001",
    "message_key": "errors.auth.invalid_credentials",
    "message": "Invalid credentials",
    "details": null,
    "request_id": "req_abc123"
  }
}
```

### 7.2 Localization Strategy

**ثلاث طبقات:**

1. **النصوص الثابتة** → `lang/ar/*` و `lang/en/*`
   ```php
   __('messages.welcome', [], $user->language);
   ```

2. **المحتوى الديناميكي في DB** → Spatie Translatable
   ```php
   $category->getTranslation('name', 'ar');
   ```

3. **مفاتيح للـ Frontend** → الـ Backend يرجع `message_key`، الـ Frontend يترجم
   ```json
   { "error": { "message_key": "errors.auth.invalid_credentials" } }
   ```

#### Locale Middleware
```php
class LocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language', 'ar');
        if (in_array($locale, ['ar', 'en'])) {
            App::setLocale($locale);
        }
        return $next($request);
    }
}
```

### 7.3 Rate Limiting Tiers

في `app/Providers/RouteServiceProvider.php`:

```php
RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('otp', fn (Request $r) => Limit::perMinute(3)->by($r->input('phone')));
RateLimiter::for('search', fn (Request $r) => Limit::perMinute(60)->by(optional($r->user())->id ?: $r->ip()));
RateLimiter::for('publish', fn (Request $r) => Limit::perDay(10)->by($r->user()->id));
RateLimiter::for('messages', fn (Request $r) => Limit::perMinute(30)->by($r->user()->id));
RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));
```

Usage:
```php
Route::post('auth/login', ...)->middleware('throttle:auth');
```

### 7.4 Security Checklist

- [ ] **CORS** عبر `config/cors.php` (whitelist domains)
- [ ] **HTTPS-only** + HSTS header
- [ ] **CSRF** للـ Filament (مفعّل تلقائياً)
- [ ] **SQL Injection** — Eloquent محمي تلقائياً (لا تكتب raw SQL مع user input)
- [ ] **XSS** — sanitize text inputs قبل الحفظ
- [ ] **Password Hashing** — bcrypt rounds=12 (config/hashing.php)
- [ ] **JWT/Sanctum Tokens** — expiration policy + rotation
- [ ] **Sensitive data masking** في logs (passwords, tokens, OTPs) — استخدم Laravel's `LogContext`
- [ ] **PII Encryption** — استخدم `encrypted` cast على phone/email لو لزم
- [ ] **Webhook signatures** — verify دائماً (Phase 2)
- [ ] **PHPStan level 8** — type safety
- [ ] **Larastan** — checks مخصصة لـ Laravel
- [ ] **Dependency scanning** — `composer audit` في CI

### 7.5 Monitoring & Observability

**Laravel Pulse (built-in من Laravel نفسه):**
- Slow queries, slow jobs, slow requests
- Cache hit rate
- Queue throughput
- Active users
- Servers metrics

**Sentry:**
- Production errors
- Source maps
- Release tracking
- Performance monitoring

**Logging:**
- Production: stack driver → Loki / CloudWatch
- Pino-equivalent: `monolog/monolog` JSON formatter
- Correlation IDs عبر middleware

**Alerts (via Pulse + Sentry):**
- Error rate > 1%
- P95 latency > 500ms
- Queue depth > 1000
- Failed jobs > 50/hour

### 7.6 Database Performance Rules

- كل query لازم له index (تحقق بـ `EXPLAIN`)
- N+1 ممنوع — استخدم `with()` للـ eager loading
- Soft delete: Eloquent يفلتر تلقائياً مع `SoftDeletes` trait
- Connection Pool: تعديل في `config/database.php` (PostgreSQL: 25-50)
- Read Replicas في Phase 2 — Eloquent يدعمهم out of the box
- Query monitoring عبر Telescope (dev) و Pulse (production)

### 7.7 Queue Strategy (Horizon)

**Queues منفصلة حسب الأولوية:**
```php
// config/horizon.php
'queues' => [
    'critical',  // OTP, security alerts
    'default',   // notifications
    'media',     // image processing
    'search',    // Meilisearch indexing
    'low',       // cleanup, exports
],
```

**Supervisors في production:**
```php
'supervisor-critical' => ['queue' => ['critical'], 'processes' => 5],
'supervisor-default'  => ['queue' => ['default'], 'processes' => 10],
'supervisor-media'    => ['queue' => ['media'], 'processes' => 3],
'supervisor-search'   => ['queue' => ['search'], 'processes' => 2],
'supervisor-low'      => ['queue' => ['low'], 'processes' => 1],
```

**Horizon Dashboard على `/horizon` — محمي بـ Gate.**

---

## 8. Definition of Done

أي Module ما يُعتبر مكتمل إلا لو:

- [ ] جميع الـ Routes معرّفة في `routes/api_v1.php`
- [ ] Form Requests مع Validation Rules كاملة
- [ ] API Resources لكل Response
- [ ] Error Codes معرّفة في `app/Exceptions/ErrorCode.php`
- [ ] Policies مطبقة على كل operation حساسة
- [ ] Spatie Activitylog مفعّل على Models الحساسة
- [ ] Events + Listeners للإشعارات والـ side effects
- [ ] Localization keys في `lang/ar/*` و `lang/en/*`
- [ ] Pagination + Sorting على list endpoints
- [ ] Rate limiting محدد للـ tier المناسب
- [ ] Pest Feature Tests لكل endpoint (happy path + edge cases)
- [ ] Pest Unit Tests للـ Services/Actions
- [ ] Coverage > 70% للـ Module
- [ ] Scribe annotations كاملة (auto-generate API docs)
- [ ] Filament Resource (إن كان مطلوب admin)
- [ ] Migration نظيفة + rollback يعمل
- [ ] Factories + Seeders للـ Module
- [ ] PHPStan level 8 يمر
- [ ] Laravel Pint يمر
- [ ] Code review موافق عليه

---

## 9. الجدول الزمني

| Sprint | المدة | التراكمي |
|--------|--------|-----------|
| 0 — Infrastructure | 1 أسبوع | 1 |
| 1 — Auth | 3 أيام | 1.5 |
| 2 — Users | 3 أيام | 2 |
| 3 — Categories & Locations | 2 أيام | 2.5 |
| 4 — Uploads (MediaLibrary) | 2 أيام | 3 |
| 5 — Ads | 2 أسابيع | 5 |
| 6 — Search (Scout + Meilisearch) | 3 أيام | 5.5 |
| 7 — Favorites & Recently Viewed | 1 يوم | 5.7 |
| 8 — Messaging (Reverb) | 2 أسبوع | 7.7 |
| 9 — Offers | 1 يوم | 7.9 |
| 10 — Reports & Notifications | 1 أسبوع | 8.9 |
| 11 — Admin Panel (Filament) | 1 أسبوع | 9.9 |
| 12 — CMS & Support | 2 أيام | 10.3 |
| 13 — Business (لو MVP) | 5 أيام | 11.3 |
| **+ QA + Buffer (20%)** | 2.5 أسبوع | **~14 أسبوع** |

**التقدير الواقعي:**
- **مطور Laravel واحد Senior:** ~3.5 أشهر للـ MVP الكامل
- **اثنين بالتوازي:** 2 - 2.5 أشهر

**هذا أسرع من NestJS بـ 3-4 أسابيع** بسبب Filament + Spatie ecosystem.

---

## 🎯 توصيات نهائية قبل البدء

1. **ابدأ Sprint 0 كاملاً قبل أي feature** — تنصيب كل الحزم، إعداد البيئة، CI/CD
2. **Composer Audit أسبوعياً** — لاكتشاف vulnerabilities
3. **Scribe Annotations من اليوم الأول** — التوثيق متراكم لا مؤجل
4. **Filament Resources أولاً قبل APIs المخصصة للأدمن** — يوفر وقت كبير
5. **Spatie Activitylog على كل model حساس** — Audit Log مجاناً
6. **Auto-Moderation قبل إطلاق Ads** — Sprint 5 لا يكتمل بدونها
7. **Meilisearch Synonyms للعربية** — استثمر فيها مبكراً
8. **Horizon Dashboard في staging** — قبل production
9. **Pulse + Sentry من اليوم الأول** — قراراتك مبنية على بيانات
10. **بيئات منفصلة:** dev (Sail) / staging / production
11. **Backup يومي للـ DB** + S3 cross-region replication
12. **Laravel Forge أو Laravel Cloud للنشر** — أبسط option للـ Laravel

---

## 📦 ملخص الحزم الأساسية

```bash
# Core
composer require laravel/sanctum laravel/scout laravel/reverb laravel/horizon laravel/pulse

# Spatie (لا غنى عنها)
composer require spatie/laravel-permission
composer require spatie/laravel-medialibrary
composer require spatie/laravel-activitylog
composer require spatie/laravel-translatable
composer require spatie/laravel-query-builder
composer require spatie/laravel-data

# Admin Panel
composer require filament/filament:"^5.0"
composer require filament/spatie-laravel-media-library-plugin
composer require filament/spatie-laravel-translatable-plugin
composer require filament/spatie-laravel-activitylog-plugin
composer require stephenjude/filament-two-factor-authentication

# Search + Storage
composer require meilisearch/meilisearch-php
composer require league/flysystem-aws-s3-v3

# Image Processing
composer require intervention/image

# SMS / Notifications
composer require twilio/sdk
composer require laravel-notification-channels/fcm

# API Documentation
composer require knuckleswtf/scribe

# Dev Dependencies
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
composer require --dev laravel/telescope
composer require --dev larastan/larastan
composer require --dev laravel/pint
composer require --dev nunomaduro/collision
```

---

**نهاية خطة الـ Backend التنفيذية لمنصة QBazaar — Laravel Edition**