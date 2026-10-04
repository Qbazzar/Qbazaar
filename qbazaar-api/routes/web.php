<?php

declare(strict_types=1);

use App\Http\Controllers\Manage\ActivityController;
use App\Http\Controllers\Manage\AdController;
use App\Http\Controllers\Manage\AuthController;
use App\Http\Controllers\Manage\CategoryController;
use App\Http\Controllers\Manage\CommissionRateController;
use App\Http\Controllers\Manage\ConversationController;
use App\Http\Controllers\Manage\DashboardController;
use App\Http\Controllers\Manage\Finance;
use App\Http\Controllers\Manage\HelpArticleController;
use App\Http\Controllers\Manage\HelpCategoryController;
use App\Http\Controllers\Manage\LocationController;
use App\Http\Controllers\Manage\ModerationRuleController;
use App\Http\Controllers\Manage\NotificationController;
use App\Http\Controllers\Manage\OfferController;
use App\Http\Controllers\Manage\PageController;
use App\Http\Controllers\Manage\ProfileController;
use App\Http\Controllers\Manage\ReportController;
use App\Http\Controllers\Manage\RoleController;
use App\Http\Controllers\Manage\SavedSearchController;
use App\Http\Controllers\Manage\SettingController;
use App\Http\Controllers\Manage\SupportTicketController;
use App\Http\Controllers\Manage\UserController;
use App\Http\Middleware\AuditAdminMutations;
use App\Http\Middleware\EnsureApiDocsEnabled;
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

    Route::middleware(['staff', AuditAdminMutations::class])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Signed-in staff manage their own account.
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

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
        });
        Route::middleware('permission:users.ban')->group(function () {
            Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        });
        Route::post('users/{user}/roles', [UserController::class, 'updateRoles'])->middleware('permission:roles.manage')->name('users.roles');
        Route::post('users/{user}/reset-password', [UserController::class, 'sendPasswordReset'])->middleware('permission:users.update')->name('users.reset-password');
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->middleware('permission:users.impersonate')->name('users.impersonate');

        // Roles (read-only)
        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.manage')->name('roles.index');

        // Reports (moderation queue)
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        });
        Route::middleware('permission:reports.action')->group(function () {
            Route::post('reports/{report}/resolve', [ReportController::class, 'resolve'])->name('reports.resolve');
            Route::post('reports/{report}/dismiss', [ReportController::class, 'dismiss'])->name('reports.dismiss');
            Route::post('reports/{report}/action', [ReportController::class, 'action'])->name('reports.action');
            Route::post('reports/{report}/suspend-ad', [ReportController::class, 'suspendAd'])->middleware('permission:ads.suspend')->name('reports.suspend-ad');
            Route::post('reports/{report}/ban-user', [ReportController::class, 'banUser'])->middleware('permission:users.ban')->name('reports.ban-user');
            Route::post('reports/bulk-dismiss', [ReportController::class, 'bulkDismiss'])->name('reports.bulk-dismiss');
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

        // Categories (CRUD)
        Route::middleware('permission:categories.manage')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::post('categories/bulk-destroy', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
        });

        // Locations (CRUD)
        Route::middleware('permission:locations.manage')->group(function () {
            Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
            Route::get('locations/create', [LocationController::class, 'create'])->name('locations.create');
            Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
            Route::get('locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
            Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
            Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
            Route::post('locations/bulk-destroy', [LocationController::class, 'bulkDestroy'])->name('locations.bulk-destroy');
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

        // Conversations (read-only)
        Route::middleware('permission:conversations.view')->group(function () {
            Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
            Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        });

        // Platform settings
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
            Route::post('settings/commission-rates', [CommissionRateController::class, 'store'])->name('settings.commission-rates.store');
            Route::delete('settings/commission-rates/{category}', [CommissionRateController::class, 'destroy'])->name('settings.commission-rates.destroy');
        });

        // Finance: settlements, withdrawals, disputes and user statements
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::middleware('permission:finance.view')->group(function () {
                Route::get('settlements', [Finance\SettlementController::class, 'index'])->name('settlements.index');
                Route::get('settlements/{settlement}/proof', [Finance\SettlementController::class, 'proof'])->name('settlements.proof');
                Route::get('withdrawals', [Finance\WithdrawalController::class, 'index'])->name('withdrawals.index');
                Route::get('disputes', [Finance\DisputeController::class, 'index'])->name('disputes.index');
                Route::get('users/{user}/statement', [Finance\StatementController::class, 'show'])->name('statements.show');
            });
            Route::middleware('permission:finance.manage')->group(function () {
                Route::post('settlements/{settlement}/approve', [Finance\SettlementController::class, 'approve'])->name('settlements.approve');
                Route::post('settlements/{settlement}/reject', [Finance\SettlementController::class, 'reject'])->name('settlements.reject');
                Route::post('withdrawals/{withdrawal}/pay', [Finance\WithdrawalController::class, 'pay'])->name('withdrawals.pay');
                Route::post('withdrawals/{withdrawal}/reject', [Finance\WithdrawalController::class, 'reject'])->name('withdrawals.reject');
                Route::post('disputes/{order}/resolve', [Finance\DisputeController::class, 'resolve'])->name('disputes.resolve');
            });
        });

        // Read-only surfaces
        Route::get('offers', [OfferController::class, 'index'])->middleware('permission:offers.view')->name('offers.index');
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
