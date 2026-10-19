<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SanctionAction;
use App\Enums\SanctionReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One staff sanction, written once and never edited. Only SanctionService
 * creates rows; the preset reason reaches the affected user, the note stays
 * with the staff.
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property SanctionAction $action
 * @property SanctionReason $reason
 * @property string|null $note
 * @property string|null $staff_id
 * @property string|null $report_id
 * @property Carbon $created_at
 * @property User|null $staff
 * @property Report|null $report
 */
class ModerationSanction extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'moderation_sanctions';

    /** @var string */
    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'subject_type',
        'subject_id',
        'action',
        'reason',
        'note',
        'staff_id',
        'report_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => SanctionAction::class,
            'reason' => SanctionReason::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Newest first for one ad or account, served by moderation_sanctions_subject_idx.
     *
     * @param Builder<ModerationSanction> $query
     * @return Builder<ModerationSanction>
     */
    public function scopeForSubject(Builder $query, string $subjectType, string $subjectId): Builder
    {
        return $query
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest('created_at')
            ->latest('id');
    }
}
