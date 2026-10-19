<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One time a staff member opened a private conversation, with the reason
 * they gave. Written by ConversationAccessService only.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string|null $staff_id
 * @property string $reason
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property User|null $staff
 * @property Conversation $conversation
 */
class ConversationAccessLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
