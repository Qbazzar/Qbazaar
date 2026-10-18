<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HomeSectionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The mode of one home section. A section without a row is automatic.
 *
 * @property string $section
 * @property HomeSectionMode $mode
 * @property string|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $editor
 */
class HomeSectionSetting extends Model
{
    protected $primaryKey = 'section';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['section', 'mode', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => HomeSectionMode::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
