<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\IntegrationGroup;
use App\Enums\IntegrationSetting;
use App\Models\User;
use App\Services\Settings\IntegrationSettings;
use App\Services\Settings\WorkerRestarter;
use Illuminate\Support\Facades\DB;

class UpdateIntegrationSettingsAction
{
    public function __construct(
        private readonly IntegrationSettings $settings,
        private readonly WorkerRestarter $workers,
    ) {}

    /**
     * Secrets are write-only: an empty submit keeps the stored value and only
     * an explicit clear flag removes it. Audit entries carry the key, never a value.
     *
     * @param array<string, mixed> $values submitted integrations[...] input
     * @param array<string, mixed> $clear integrations_clear[...] flags
     * @param list<IntegrationGroup> $groups groups the actor may edit
     */
    public function execute(array $values, array $clear, array $groups, User $actor): void
    {
        $changed = [];

        DB::transaction(function () use ($values, $clear, $groups, $actor, &$changed): void {
            foreach ($groups as $group) {
                foreach (IntegrationSetting::forGroup($group) as $setting) {
                    if ($this->apply($setting, $values, $clear, $actor)) {
                        $changed[] = $setting;
                        $this->audit($setting, $actor);
                    }
                }
            }
        });

        if (array_filter($changed, fn (IntegrationSetting $s): bool => $s->definition()->group !== IntegrationGroup::CONTACT) !== []) {
            $this->workers->restart();
        }
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $clear
     */
    private function apply(IntegrationSetting $setting, array $values, array $clear, User $actor): bool
    {
        $definition = $setting->definition();
        $current = $this->settings->get($setting);

        if (! empty($clear[$setting->value])) {
            return $current !== null && $this->remove($setting);
        }

        if (! array_key_exists($setting->value, $values)) {
            return false;
        }

        $new = trim((string) $values[$setting->value]);

        if ($new === '') {
            return ! $definition->secret && $current !== null && $this->remove($setting);
        }

        if ($new === $current) {
            return false;
        }

        $this->settings->put($setting, $new, $actor);

        return true;
    }

    private function remove(IntegrationSetting $setting): bool
    {
        $this->settings->forget($setting);

        return true;
    }

    private function audit(IntegrationSetting $setting, User $actor): void
    {
        activity('settings')
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['key' => $setting->value])
            ->log('Integration setting updated');
    }
}
