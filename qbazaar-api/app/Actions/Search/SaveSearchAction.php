<?php

declare(strict_types=1);

namespace App\Actions\Search;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Search\SavedSearchCriteria;
use Illuminate\Support\Facades\DB;

/**
 * Creates a saved search or changes one of the caller's. The indexed alert
 * criteria are refreshed whenever `query_params` changes.
 */
class SaveSearchAction
{
    public function __construct(private readonly SavedSearchCriteria $criteria) {}

    /**
     * The user row is locked so two parallel requests cannot both pass the
     * per-user cap.
     *
     * @param array{name: string, query_params: array<string, mixed>, alerts_enabled?: bool} $attributes
     */
    public function create(User $user, array $attributes): SavedSearch
    {
        return DB::transaction(function () use ($user, $attributes): SavedSearch {
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);

            $count = SavedSearch::query()->where('user_id', $user->id)->count();

            if ($count >= (int) config('qbazaar.search.saved_search_max_per_user')) {
                throw new DomainException(ErrorCode::SEARCH_SAVED_LIMIT);
            }

            $search = new SavedSearch($attributes);
            $search->user_id = $user->id;

            return $this->store($search);
        });
    }

    /**
     * @param array{name?: string, query_params?: array<string, mixed>, alerts_enabled?: bool} $changes
     */
    public function update(User $user, string $id, array $changes): SavedSearch
    {
        /** @var SavedSearch|null $search */
        $search = SavedSearch::query()->where('user_id', $user->id)->whereKey($id)->first();

        if ($search === null) {
            throw new DomainException(ErrorCode::SEARCH_SAVED_NOT_FOUND);
        }

        return $this->store($search->fill($changes));
    }

    private function store(SavedSearch $search): SavedSearch
    {
        if (! $search->exists || $search->isDirty('query_params')) {
            $this->criteria->apply($search);
        }

        $search->save();

        return $search;
    }
}
