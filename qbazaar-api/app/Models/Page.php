<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SanitizedHtmlTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $slug
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property array<string, string>|null $meta_description
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property int $display_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Page extends Model
{
    use HasUlids;

    protected $table = 'pages';

    /** @var string */
    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'title',
        'body',
        'meta_description',
        'is_published',
        'published_at',
        'display_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => SanitizedHtmlTranslations::class,
            'meta_description' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'display_order' => 'integer',
        ];
    }

    /**
     * @param Builder<Page> $query
     * @return Builder<Page>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
