<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessApplicationStatus;
use Database\Factories\BusinessApplicationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A user asking to become a verified business. Staff approve it (the
 * account becomes a verified business), reject it with a reason, or ask
 * for more information, which the applicant answers by resubmitting.
 *
 * `open_user_id` is kept equal to `user_id` while the application is open
 * and NULL otherwise; its unique index allows one open application per user.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $open_user_id
 * @property BusinessApplicationStatus $status
 * @property string $business_name
 * @property string $legal_name
 * @property string $commercial_registration_number
 * @property string|null $applicant_note
 * @property string|null $review_note
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $submitted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $user
 * @property User|null $reviewer
 */
class BusinessApplication extends Model implements HasMedia
{
    /** @use HasFactory<BusinessApplicationFactory> */
    use HasFactory, HasUlids, InteractsWithMedia;

    public const string DOCUMENTS_COLLECTION = 'documents';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BusinessApplicationStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BusinessApplication $application): void {
            $application->open_user_id = $application->status->isOpen() ? $application->user_id : null;
        });
    }

    /**
     * Commercial registrations and IDs are legal documents: they stay on a
     * private disk and only reviewing staff open them, through an
     * authorised admin route.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::DOCUMENTS_COLLECTION)
            ->useDisk((string) config('qbazaar.business.documents_disk'));
    }

    /**
     * The uploaded documents, oldest first. Reads the loaded `media`
     * relation when the caller eager-loaded it.
     *
     * @return MediaCollection<int, Media>
     */
    public function documents(): MediaCollection
    {
        return $this->getMedia(self::DOCUMENTS_COLLECTION);
    }

    /**
     * One document of this application, so a document id from another
     * application can never be opened through this one.
     */
    public function document(int $id): ?Media
    {
        /** @var Media|null */
        return $this->media()
            ->where('collection_name', self::DOCUMENTS_COLLECTION)
            ->whereKey($id)
            ->first();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
