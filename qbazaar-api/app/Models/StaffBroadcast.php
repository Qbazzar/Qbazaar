<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastSegment;
use App\Enums\BroadcastStatus;
use App\Notifications\SystemAnnouncementNotification;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A notification a staff member sent to a segment of users. Written by
 * BroadcastComposer and BroadcastDelivery only.
 *
 * @property string $id
 * @property string|null $sender_id
 * @property BroadcastSegment $segment
 * @property string|null $location_id
 * @property string $title_ar
 * @property string $body_ar
 * @property string|null $title_en
 * @property string|null $body_en
 * @property bool $send_email
 * @property BroadcastStatus $status
 * @property int $recipients_count
 * @property int $delivered_count
 * @property int|null $chunks_total
 * @property int $chunks_done
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $sender
 * @property Location|null $location
 */
class StaffBroadcast extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment' => BroadcastSegment::class,
            'status' => BroadcastStatus::class,
            'send_email' => 'boolean',
            'recipients_count' => 'integer',
            'delivered_count' => 'integer',
            'chunks_total' => 'integer',
            'chunks_done' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The notification each recipient gets: the Arabic copy, plus English for
     * English-language users when staff wrote it.
     */
    public function toNotification(): SystemAnnouncementNotification
    {
        $translations = ['ar' => ['title' => $this->title_ar, 'body' => $this->body_ar]];

        if ($this->title_en !== null && $this->body_en !== null) {
            $translations['en'] = ['title' => $this->title_en, 'body' => $this->body_en];
        }

        return new SystemAnnouncementNotification(
            $this->title_ar,
            $this->body_ar,
            $translations,
            (bool) $this->send_email,
            $this->id,
        );
    }
}
