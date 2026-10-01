<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataExportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $user_id
 * @property DataExportStatus $status
 * @property string|null $path
 * @property Carbon|null $expires_at
 * @property Carbon|null $downloaded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DataExport extends Model
{
    use HasUlids, Prunable;

    public const string DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DataExportStatus::class,
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now());
    }

    protected function pruning(): void
    {
        $this->deleteFile();
    }

    public function deleteFile(): void
    {
        if ($this->path !== null) {
            Storage::disk(self::DISK)->delete($this->path);
        }
    }
}
