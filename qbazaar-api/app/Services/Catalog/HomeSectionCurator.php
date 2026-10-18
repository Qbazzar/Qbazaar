<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\AdStatus;
use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\HomeSectionItem;
use App\Models\HomeSectionSetting;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Support\ManualOrder;
use App\Support\Sql\PositionUpdate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Staff switch a home section between automatic and manual mode and pick,
 * remove and reorder its ads. A section holds at most as many picks as the
 * home rail shows. Every change is audited and drops the cached home feed
 * once committed, so the next visitor sees it.
 */
class HomeSectionCurator
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
        private readonly CatalogCache $catalog,
    ) {}

    public function setMode(User $admin, HomeSection $section, HomeSectionMode $mode): void
    {
        HomeSectionSetting::query()->updateOrCreate(
            ['section' => $section->value],
            ['mode' => $mode, 'updated_by' => $admin->id],
        );

        $this->changed($admin, 'mode', ['section' => $section->value, 'mode' => $mode->value]);
    }

    public function add(User $admin, HomeSection $section, Ad $ad): void
    {
        if ($ad->status !== AdStatus::ACTIVE) {
            throw new DomainException(ErrorCode::AD_NOT_ACTIVE);
        }

        try {
            DB::transaction(function () use ($admin, $section, $ad): void {
                $positions = $this->lockedOrder($section);

                if (count($positions) >= $section->limit()) {
                    throw new DomainException(ErrorCode::HOME_SECTION_FULL);
                }

                HomeSectionItem::query()->create([
                    'section' => $section->value,
                    'ad_id' => $ad->id,
                    'position' => count($positions) + 1,
                    'added_by' => $admin->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::HOME_SECTION_DUPLICATE);
        }

        $this->changed($admin, 'add', ['section' => $section->value, 'ad_id' => $ad->id]);
    }

    public function remove(User $admin, HomeSection $section, HomeSectionItem $item): void
    {
        DB::transaction(function () use ($section, $item): void {
            $order = $this->lockedOrder($section);
            HomeSectionItem::query()->whereKey($item->id)->where('section', $section->value)->delete();
            PositionUpdate::apply('home_section_items', 'id', 'position', array_values(array_diff($order, [$item->id])));
        });

        $this->changed($admin, 'remove', ['section' => $section->value, 'ad_id' => $item->ad_id]);
    }

    /**
     * @param array<array-key, mixed> $positions item id => typed position
     */
    public function reorder(User $admin, HomeSection $section, array $positions): void
    {
        $order = DB::transaction(function () use ($section, $positions): array {
            $order = ManualOrder::resolve($this->lockedOrder($section), $positions);
            PositionUpdate::apply('home_section_items', 'id', 'position', $order);

            return $order;
        });

        $this->changed($admin, 'reorder', ['section' => $section->value, 'order' => $order]);
    }

    /**
     * The section's item ids in their current order, locked for the caller's
     * transaction.
     *
     * @return list<int>
     */
    private function lockedOrder(HomeSection $section): array
    {
        /** @var list<int> */
        return HomeSectionItem::query()
            ->where('section', $section->value)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function changed(User $admin, string $action, array $properties): void
    {
        $this->audit->record($admin, 'admin.home-sections.' . $action, null, $properties);
        DB::afterCommit(fn () => $this->catalog->homeCurationChanged());
    }
}
