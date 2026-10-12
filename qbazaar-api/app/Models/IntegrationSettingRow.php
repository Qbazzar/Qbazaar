<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IntegrationSetting;
use Illuminate\Database\Eloquent\Model;

/**
 * Stored value of an {@see IntegrationSetting}. Secret rows hold
 * Crypt ciphertext; read and write them only through IntegrationSettings.
 *
 * @property string $key
 * @property string $value
 * @property bool $is_secret
 * @property string|null $updated_by
 */
class IntegrationSettingRow extends Model
{
    protected $table = 'integration_settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'value', 'is_secret', 'updated_by'];

    /**
     * @var list<string>
     */
    protected $hidden = ['value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
