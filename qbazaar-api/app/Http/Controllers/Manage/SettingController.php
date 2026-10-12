<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Admin\UpdateIntegrationSettingsAction;
use App\Actions\Admin\UpdatePlatformSettingsAction;
use App\Enums\IntegrationGroup;
use App\Enums\PlatformSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\UpdatePlatformSettingsRequest;
use App\Models\Category;
use App\Models\CategoryCommissionRate;
use App\Models\User;
use App\Services\Settings\IntegrationSettings;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(SettingsService $settings, IntegrationSettings $integrations, Request $request): View
    {
        return view('admin.settings.edit', [
            'groups' => PlatformSetting::grouped(),
            'values' => $settings->all(),
            'integrationGroups' => IntegrationGroup::visibleTo($request->user()),
            'integrations' => $integrations,
            'commissionRates' => CategoryCommissionRate::query()->with('category')->orderBy('category_id')->get(),
            'categories' => Category::query()->orderBy('order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(
        UpdatePlatformSettingsRequest $request,
        UpdatePlatformSettingsAction $updateSettings,
        UpdateIntegrationSettingsAction $updateIntegrations,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();

        $updateSettings->execute(Arr::except($validated, ['integrations', 'integrations_clear']), $actor);
        $updateIntegrations->execute($validated['integrations'] ?? [], $validated['integrations_clear'] ?? [], $request->editableGroups(), $actor);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', __('admin.settings.saved'));
    }
}
