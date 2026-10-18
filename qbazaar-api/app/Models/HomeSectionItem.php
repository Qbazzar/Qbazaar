<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An ad staff picked for a home section, at a position (1 = first).
 *
 * @property int $id
 * @property string $section
 * @property string $ad_id
 * @property int $position
 * @property string|null $added_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Ad|null $ad soft-deleted ads read as null
 */
class HomeSectionItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['section', 'ad_id', 'position', 'added_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
