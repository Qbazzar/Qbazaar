<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Actions\Admin\UpdatePlatformSettingsAction;
use App\Enums\PlatformSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\UpdatePlatformSettingsRequest;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(SettingsService $settings): View
    {
        return view('admin.settings.edit', [
            'groups' => PlatformSetting::grouped(),
            'values' => $settings->all(),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request, UpdatePlatformSettingsAction $updateSettings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $updateSettings->execute($request->validated(), $actor);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', __('admin.settings.saved'));
    }
}
