<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Moderation\Concerns;

/**
 * The ad ids a bulk action posts, capped at bulk_action_max.
 */
trait ValidatesSelectedAds
{
    /**
     * @return array<string, list<string>>
     */
    protected function selectedAdRules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:' . (int) config('qbazaar.admin.bulk_action_max')],
            'ids.*' => ['required', 'string', 'ulid'],
        ];
    }

    /**
     * @return list<string>
     */
    public function adIds(): array
    {
        return array_values(array_unique(array_map(strtolower(...), (array) $this->validated('ids'))));
    }
}
