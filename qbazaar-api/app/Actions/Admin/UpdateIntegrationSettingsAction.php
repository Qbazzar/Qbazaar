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
                    $outcome = $this->apply($setting, $values, $clear, $actor);

                    if ($outcome !== null) {
                        $changed[] = $setting;
                        $this->audit($setting, $outcome, $actor);
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
    private function apply(IntegrationSetting $setting, array $values, array $clear, User $actor): ?string
    {
        $definition = $setting->definition();
        $current = $this->settings->get($setting);

        if (! empty($clear[$setting->value])) {
            return $current !== null ? $this->remove($setting) : null;
        }

        if (! array_key_exists($setting->value, $values)) {
            return null;
        }

        $new = trim((string) $values[$setting->value]);

        if ($new === '') {
            return ! $definition->secret && $current !== null ? $this->remove($setting) : null;
        }

        if ($new === $current) {
            return null;
        }

        $this->settings->put($setting, $new, $actor);

        return 'set';
    }

    private function remove(IntegrationSetting $setting): string
    {
        $this->settings->forget($setting);

        return 'cleared';
    }

    private function audit(IntegrationSetting $setting, string $action, User $actor): void
    {
        activity('settings')
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['key' => $setting->value, 'action' => $action])
            ->log('Integration setting updated');
    }
}
