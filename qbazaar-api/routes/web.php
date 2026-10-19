<?php

declare(strict_types=1);

use App\Http\Controllers\Manage\ActivityController;
use App\Http\Controllers\Manage\AdController;
use App\Http\Controllers\Manage\AuthController;
use App\Http\Controllers\Manage\BroadcastController;
use App\Http\Controllers\Manage\BusinessApplicationController;
use App\Http\Controllers\Manage\CategoryController;
use App\Http\Controllers\Manage\CategoryFieldController;
use App\Http\Controllers\Manage\CommissionRateController;
use App\Http\Controllers\Manage\CompanyController;
use App\Http\Controllers\Manage\ConversationController;
use App\Http\Controllers\Manage\DashboardController;
use App\Http\Controllers\Manage\FeaturedCompanyController;
use App\Http\Controllers\Manage\Finance;
use App\Http\Controllers\Manage\HelpArticleController;
use App\Http\Controllers\Manage\HelpCategoryController;
use App\Http\Controllers\Manage\HomeSectionController;
use App\Http\Controllers\Manage\InboxController;
use App\Http\Controllers\Manage\IntegrationTestController;
use App\Http\Controllers\Manage\LocationController;
use App\Http\Controllers\Manage\ModerationRuleController;
use App\Http\Controllers\Manage\NotificationController;
use App\Http\Controllers\Manage\OfferController;
use App\Http\Controllers\Manage\PageController;
use App\Http\Controllers\Manage\ProfileController;
use App\Http\Controllers\Manage\ReportController;
use App\Http\Controllers\Manage\ReviewQueueController;
use App\Http\Controllers\Manage\RoleController;
use App\Http\Controllers\Manage\RolePermissionController;
use App\Http\Controllers\Manage\SavedSearchController;
use App\Http\Controllers\Manage\SettingController;
use App\Http\Controllers\Manage\SupportTicketController;
use App\Http\Controllers\Manage\TwoFactorChallengeController;
use App\Http\Controllers\Manage\TwoFactorController;
use App\Http\Controllers\Manage\UserController;
use App\Http\Controllers\Manage\UserTwoFactorController;
use App\Http\Middleware\AuditAdminMutations;
use App\Http\Middleware\EnsureApiDocsEnabled;
use App\Services\Admin\UserDetailTabs;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Admin panel (/admin) — custom Tailwind, session-authenticated
|--------------------------------------------------------------------------
| Session (web guard) auth, gated to staff roles by the `staff` middleware.
| Every screen and action also requires its Spatie permission; the catalogue
| lives in RolesAndPermissionsSeeder. Every successful mutation is written
| to the admin audit log.
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Staff two-factor sign-in (AD-17.14): the code prompt between the password
    // and the panel. Reached before sign-in; wrong codes are limited per account.
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.verify');

    Route::middleware(['staff', AuditAdminMutations::class])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Signed-in staff manage their own account.
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

        // Staff two-factor sign-in (AD-17.14): each staff member manages their own.
        Route::get('profile/two-factor', [TwoFactorController::class, 'show'])->name('profile.two-factor.show');
        Route::post('profile/two-factor', [TwoFactorController::class, 'store'])->name('profile.two-factor.store');
        Route::post('profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
        Route::post('profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('profile.two-factor.recovery-codes');
        Route::delete('profile/two-factor', [TwoFactorController::class, 'destroy'])->name('profile.two-factor.destroy');
        Route::delete('users/{user}/two-factor', [UserTwoFactorController::class, 'destroy'])->middleware('permission:roles.manage')->name('users.two-factor.destroy');

        // Ads
        Route::middleware('permission:ads.view')->group(function () {
            Route::get('ads', [AdController::class, 'index'])->name('ads.index');
            Route::get('ads/{ad}', [AdController::class, 'show'])->name('ads.show');
        });
        Route::middleware('permission:ads.update')->group(function () {
            Route::get('ads/{ad}/edit', [AdController::class, 'edit'])->name('ads.edit');
            Route::put('ads/{ad}', [AdController::class, 'update'])->name('ads.update');
        });
        Route::post('ads/{ad}/approve', [AdController::class, 'approve'])->middleware('permission:ads.approve')->name('ads.approve');
        Route::post('ads/{ad}/reject', [AdController::class, 'reject'])->middleware('permission:ads.reject')->name('ads.reject');
        Route::middleware('permission:ads.suspend')->group(function () {
            Route::post('ads/{ad}/suspend', [AdController::class, 'suspend'])->name('ads.suspend');
            Route::post('ads/{ad}/unsuspend', [AdController::class, 'unsuspend'])->name('ads.unsuspend');
            Route::post('ads/{ad}/force-expire', [AdController::class, 'forceExpire'])->name('ads.force-expire');
        });
        Route::post('ads/{ad}/feature', [AdController::class, 'toggleFeature'])->middleware('permission:ads.feature')->name('ads.feature');
        Route::middleware('permission:ads.delete')->group(function () {
            Route::delete('ads/{ad}', [AdController::class, 'destroy'])->name('ads.destroy');
            Route::post('ads/bulk-destroy', [AdController::class, 'bulkDestroy'])->name('ads.bulk-destroy');
        });

        // Users
        Route::middleware('permission:users.view')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::get('users/{user}/tabs/{tab}', [UserController::class, 'tab'])
                ->whereIn('tab', UserDetailTabs::keys())
                ->name('users.tab');
        });
        Route::middleware('permission:users.ban')->group(function () {
            Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        });
        Route::post('users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('permission:roles.manage')->name('users.roles');
        Route::post('users/{user}/reset-password', [UserController::class, 'sendPasswordReset'])->middleware('permission:users.update')->name('users.reset-password');
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->middleware('permission:users.impersonate')->name('users.impersonate');

        // ── Companies, business applications and home sections (AD-17.1, AD-17.17, AD-17.2) ──
        Route::middleware('permission:companies.view')->group(function () {
            Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
            Route::get('companies/featured', [FeaturedCompanyController::class, 'index'])->name('companies.featured.index');
            Route::get('companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
            Route::get('business-applications', [BusinessApplicationController::class, 'index'])->name('business-applications.index');
            Route::get('business-applications/{application}', [BusinessApplicationController::class, 'show'])->name('business-applications.show');
        });
        Route::middleware('permission:companies.verify')->group(function () {
            Route::post('companies/{company}/verify', [CompanyController::class, 'verify'])->name('companies.verify');
            Route::post('companies/{company}/revoke-verification', [CompanyController::class, 'revokeVerification'])->name('companies.revoke-verification');
            Route::get('business-applications/{application}/documents/{document}', [BusinessApplicationController::class, 'document'])
                ->whereNumber('document')
                ->name('business-applications.document');
            Route::post('business-applications/{application}/approve', [BusinessApplicationController::class, 'approve'])->name('business-applications.approve');
            Route::post('business-applications/{application}/reject', [BusinessApplicationController::class, 'reject'])->name('business-applications.reject');
            Route::post('business-applications/{application}/request-info', [BusinessApplicationController::class, 'requestInfo'])->name('business-applications.request-info');
        });
        Route::middleware('permission:companies.feature')->group(function () {
            Route::put('companies/featured/order', [FeaturedCompanyController::class, 'reorder'])->name('companies.featured.reorder');
            Route::post('companies/{company}/feature', [CompanyController::class, 'feature'])->name('companies.feature');
            Route::post('companies/{company}/unfeature', [CompanyController::class, 'unfeature'])->name('companies.unfeature');
        });
        Route::middleware('permission:home-sections.manage')->prefix('home-sections')->name('home-sections.')->group(function () {
            Route::get('/', [HomeSectionController::class, 'index'])->name('index');
            Route::put('{section}/mode', [HomeSectionController::class, 'updateMode'])->name('mode');
            Route::post('{section}/items', [HomeSectionController::class, 'storeItem'])->name('items.store');
            Route::delete('{section}/items/{item}', [HomeSectionController::class, 'destroyItem'])->whereNumber('item')->name('items.destroy');
            Route::put('{section}/order', [HomeSectionController::class, 'reorder'])->name('order');
        });

        // Roles and the permission matrix (AD-17.15): super_admin only, by role as
        // well as permission, so a role granted roles.manage can never edit itself.
        Route::middleware(['permission:roles.manage', 'role:super_admin'])->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/permissions', [RolePermissionController::class, 'edit'])->name('roles.permissions.edit');
            Route::put('roles/permissions', [RolePermissionController::class, 'update'])->name('roles.permissions.update');
        });

        // Broadcast notifications to a segment of users (AD-17.15): queued and
        // capped per sender.
        Route::middleware('permission:notifications.broadcast')->group(function () {
            Route::get('broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
            Route::post('broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
        });

        // Reports (moderation queue)
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        });
        Route::middleware('permission:reports.action')->group(function () {
            Route::post('reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');
            Route::post('reports/{report}/dismiss', [ReportController::class, 'dismiss'])->name('reports.dismiss');
            Route::post('reports/{report}/action', [ReportController::class, 'action'])->name('reports.action');
            // Sanction the reported item and close the report in one step; the chosen
            // sanction's own permission (ads.suspend, ads.delete, users.ban) is checked by its request.
            Route::post('reports/{report}/sanction', [ReportController::class, 'sanction'])->name('reports.sanction');
            Route::post('reports/bulk-dismiss', [ReportController::class, 'bulkDismiss'])->name('reports.bulk-dismiss');
        });

        // Moderation tools: FIFO review queue with bulk approve / reject (AD-17.10)
        Route::get('moderation/queue', [ReviewQueueController::class, 'index'])->middleware('permission:ads.approve')->name('moderation.queue');
        Route::post('moderation/queue/approve', [ReviewQueueController::class, 'approve'])->middleware('permission:ads.approve')->name('moderation.queue.approve');
        Route::post('moderation/queue/reject', [ReviewQueueController::class, 'reject'])->middleware('permission:ads.reject')->name('moderation.queue.reject');

        // Staff inbox: each staff member's own bell, like their profile (AD-17.10)
        Route::prefix('inbox')->name('inbox.')->group(function () {
            Route::get('/', [InboxController::class, 'index'])->name('index');
            Route::get('summary', [InboxController::class, 'summary'])->name('summary');
            Route::post('read-all', [InboxController::class, 'readAll'])->name('read-all');
            Route::post('{notification}/open', [InboxController::class, 'open'])->whereUuid('notification')->name('open');
        });

        // Moderation rules (CRUD)
        Route::middleware('permission:moderation-rules.manage')->group(function () {
            Route::get('moderation-rules', [ModerationRuleController::class, 'index'])->name('moderation-rules.index');
            Route::get('moderation-rules/create', [ModerationRuleController::class, 'create'])->name('moderation-rules.create');
            Route::post('moderation-rules', [ModerationRuleController::class, 'store'])->name('moderation-rules.store');
            Route::get('moderation-rules/{rule}/edit', [ModerationRuleController::class, 'edit'])->name('moderation-rules.edit');
            Route::put('moderation-rules/{rule}', [ModerationRuleController::class, 'update'])->name('moderation-rules.update');
            Route::delete('moderation-rules/{rule}', [ModerationRuleController::class, 'destroy'])->name('moderation-rules.destroy');
            Route::post('moderation-rules/bulk-destroy', [ModerationRuleController::class, 'bulkDestroy'])->name('moderation-rules.bulk-destroy');
        });

        // Categories (tree, CRUD, safe delete, fields editor)
        Route::middleware('permission:categories.manage')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::post('categories/bulk-destroy', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
            Route::patch('categories/{category}/status', [CategoryController::class, 'status'])->name('categories.status');

            // Fields editor: per-category custom attributes, inherited down the tree
            Route::get('categories/{category}/fields', [CategoryFieldController::class, 'index'])->name('categories.fields.index');
            Route::get('categories/{category}/fields/create', [CategoryFieldController::class, 'create'])->name('categories.fields.create');
            Route::post('categories/{category}/fields', [CategoryFieldController::class, 'store'])->name('categories.fields.store');
            Route::get('categories/{category}/fields/{field}/edit', [CategoryFieldController::class, 'edit'])->where('field', '[A-Za-z0-9_]{1,64}')->name('categories.fields.edit');
            Route::put('categories/{category}/fields/{field}', [CategoryFieldController::class, 'update'])->where('field', '[A-Za-z0-9_]{1,64}')->name('categories.fields.update');
            Route::delete('categories/{category}/fields/{field}', [CategoryFieldController::class, 'destroy'])->where('field', '[A-Za-z0-9_]{1,64}')->name('categories.fields.destroy');
            Route::post('categories/{category}/fields/{field}/hide', [CategoryFieldController::class, 'hide'])->where('field', '[A-Za-z0-9_]{1,64}')->name('categories.fields.hide');
        });

        // Locations (CRUD, safe delete)
        Route::middleware('permission:locations.manage')->group(function () {
            Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
            Route::get('locations/create', [LocationController::class, 'create'])->name('locations.create');
            Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
            Route::get('locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
            Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
            Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
            Route::post('locations/bulk-destroy', [LocationController::class, 'bulkDestroy'])->name('locations.bulk-destroy');
            Route::patch('locations/{location}/status', [LocationController::class, 'status'])->name('locations.status');
        });

        // CMS Pages (CRUD)
        Route::middleware('permission:pages.manage')->group(function () {
            Route::get('pages', [PageController::class, 'index'])->name('pages.index');
            Route::get('pages/create', [PageController::class, 'create'])->name('pages.create');
            Route::post('pages', [PageController::class, 'store'])->name('pages.store');
            Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit');
            Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
            Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
            Route::post('pages/bulk-destroy', [PageController::class, 'bulkDestroy'])->name('pages.bulk-destroy');
        });

        // Help centre (CRUD)
        Route::middleware('permission:articles.manage')->group(function () {
            Route::get('help-categories', [HelpCategoryController::class, 'index'])->name('help-categories.index');
            Route::get('help-categories/create', [HelpCategoryController::class, 'create'])->name('help-categories.create');
            Route::post('help-categories', [HelpCategoryController::class, 'store'])->name('help-categories.store');
            Route::get('help-categories/{help_category}/edit', [HelpCategoryController::class, 'edit'])->name('help-categories.edit');
            Route::put('help-categories/{help_category}', [HelpCategoryController::class, 'update'])->name('help-categories.update');
            Route::delete('help-categories/{help_category}', [HelpCategoryController::class, 'destroy'])->name('help-categories.destroy');
            Route::post('help-categories/bulk-destroy', [HelpCategoryController::class, 'bulkDestroy'])->name('help-categories.bulk-destroy');

            Route::get('help-articles', [HelpArticleController::class, 'index'])->name('help-articles.index');
            Route::get('help-articles/create', [HelpArticleController::class, 'create'])->name('help-articles.create');
            Route::post('help-articles', [HelpArticleController::class, 'store'])->name('help-articles.store');
            Route::get('help-articles/{help_article}/edit', [HelpArticleController::class, 'edit'])->name('help-articles.edit');
            Route::put('help-articles/{help_article}', [HelpArticleController::class, 'update'])->name('help-articles.update');
            Route::delete('help-articles/{help_article}', [HelpArticleController::class, 'destroy'])->name('help-articles.destroy');
            Route::post('help-articles/bulk-destroy', [HelpArticleController::class, 'bulkDestroy'])->name('help-articles.bulk-destroy');
        });

        // Support tickets
        Route::middleware('permission:support.view')->group(function () {
            Route::get('support', [SupportTicketController::class, 'index'])->name('support.index');
            Route::get('support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
        });
        Route::middleware('permission:support.reply')->group(function () {
            Route::post('support/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');
            Route::post('support/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('support.status');
            Route::post('support/{ticket}/assign', [SupportTicketController::class, 'assignToMe'])->name('support.assign');
        });

        // Conversations (read-only). A transcript opens only after a stated,
        // logged reason (AD-17.11).
        Route::middleware('permission:conversations.view')->group(function () {
            Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
            Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
            Route::post('conversations/{conversation}/access', [ConversationController::class, 'access'])->name('conversations.access');
        });

        // Platform settings
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('settings/commission-rates', [CommissionRateController::class, 'store'])->name('settings.commission-rates.store');
            Route::delete('settings/commission-rates/{category}', [CommissionRateController::class, 'destroy'])->name('settings.commission-rates.destroy');
        });

        // Integration credential checks: super_admin only, rate limited per admin
        Route::middleware(['permission:integrations.manage', 'throttle:integration-test'])->group(function () {
            Route::post('settings/test-email', [IntegrationTestController::class, 'email'])->name('settings.test-email');
            Route::post('settings/test-sms', [IntegrationTestController::class, 'sms'])->name('settings.test-sms');
        });

        // Finance: settlements, withdrawals, disputes and user statements
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::middleware('permission:finance.view')->group(function () {
                Route::get('revenue', [Finance\RevenueController::class, 'index'])->name('revenue.index');
                Route::get('revenue/export', [Finance\RevenueController::class, 'export'])->name('revenue.export');
                Route::get('settlements', [Finance\SettlementController::class, 'index'])->name('settlements.index');
                Route::get('settlements/{settlement}/proof', [Finance\SettlementController::class, 'proof'])->name('settlements.proof');
                Route::get('withdrawals', [Finance\WithdrawalController::class, 'index'])->name('withdrawals.index');
                Route::get('disputes', [Finance\DisputeController::class, 'index'])->name('disputes.index');
                Route::get('promotions', [Finance\PromotionTransferController::class, 'index'])->name('promotions.index');
                Route::get('users/{user}/statement', [Finance\StatementController::class, 'show'])->name('statements.show');
                Route::get('orders', [Finance\OrderController::class, 'index'])->name('orders.index');
                Route::get('orders/{order}', [Finance\OrderController::class, 'show'])->name('orders.show');
            });
            Route::middleware('permission:finance.manage')->group(function () {
                Route::post('settlements/{settlement}/approve', [Finance\SettlementController::class, 'approve'])->name('settlements.approve');
                Route::post('settlements/{settlement}/reject', [Finance\SettlementController::class, 'reject'])->name('settlements.reject');
                Route::post('withdrawals/{withdrawal}/pay', [Finance\WithdrawalController::class, 'pay'])->name('withdrawals.pay');
                Route::post('withdrawals/{withdrawal}/reject', [Finance\WithdrawalController::class, 'reject'])->name('withdrawals.reject');
                Route::post('promotions/{promotion}/confirm', [Finance\PromotionTransferController::class, 'confirm'])->name('promotions.confirm');
                Route::post('promotions/{promotion}/reject', [Finance\PromotionTransferController::class, 'reject'])->name('promotions.reject');
                Route::post('disputes/{order}/resolve', [Finance\DisputeController::class, 'resolve'])->name('disputes.resolve');
            });
        });

        // Read-only surfaces
        Route::get('offers', [OfferController::class, 'index'])->middleware('permission:offers.view')->name('offers.index');
        Route::get('offers/{offer}', [OfferController::class, 'show'])->middleware('permission:offers.view')->name('offers.show');
        Route::post('offers/{offer}/cancel', [OfferController::class, 'cancel'])->middleware('permission:offers.manage')->name('offers.cancel');
        Route::get('saved-searches', [SavedSearchController::class, 'index'])->middleware('permission:users.view')->name('saved-searches.index');
        Route::get('notifications', [NotificationController::class, 'index'])->middleware('permission:users.view')->name('notifications.index');
        Route::get('activity', [ActivityController::class, 'index'])->middleware('permission:activity.view')->name('activity.index');
    });
});

/*
|--------------------------------------------------------------------------
| Swagger UI — single source of API docs
|--------------------------------------------------------------------------
| Loads the OpenAPI spec from qbazaar-contracts/openapi/v1.yaml (served by
| /api/v1/openapi.yaml) and renders Swagger UI from CDN.
| Available at /swagger and the canonical /docs; hidden in production unless
| API_DOCS_ENABLED is set.
*/
Route::view('/swagger', 'swagger')->middleware(EnsureApiDocsEnabled::class)->name('swagger.ui');
Route::view('/docs', 'swagger')->middleware(EnsureApiDocsEnabled::class)->name('docs');
