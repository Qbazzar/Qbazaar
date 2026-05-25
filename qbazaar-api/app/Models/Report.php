<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * User-submitted abuse report.
 *
 * The polymorphic target is materialised by `target_type` + `target_id`
 * rather than Laravel's `morphTo` because the target column doesn't follow
 * the standard `…able_type` FQCN convention (we store short slugs like
 * "ad"/"user" for OpenAPI friendliness). Resolution to a concrete model
 * lives in {@see App\Services\Reports\ReportTargetResolver}.
 *
 * @property string $id
 * @property string $reporter_id
 * @property ReportTarget $target_type
 * @property string $target_id
 * @property ReportCategory $category
 * @property string|null $description
 * @property ReportStatus $status
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $admin_notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $reporter
 * @property User|null $reviewer
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUlids;

    protected $table = 'reports';

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reporter_id',
        'target_type',
        'target_id',
        'category',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => ReportTarget::class,
            'category' => ReportCategory::class,
            'status' => ReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Relations
     * ──────────────────────────────────────────────────────────────────*/

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Scopes
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * Reports filed by `$reporterId` within the last `$days` days against
     * the given target. Powers the duplicate-report guard.
     *
     * @param Builder<Report> $query
     * @return Builder<Report>
     */
    public function scopeRecentDuplicate(
        Builder $query,
        string $reporterId,
        ReportTarget $targetType,
        string $targetId,
        int $days,
    ): Builder {
        return $query
            ->where('reporter_id', $reporterId)
            ->where('target_type', $targetType->value)
            ->where('target_id', $targetId)
            ->where('created_at', '>=', now()->subDays($days));
    }
}
