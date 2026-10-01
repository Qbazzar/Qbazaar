<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SanitizedHtmlTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $category_id
 * @property string $slug
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property array<string, string>|null $excerpt
 * @property bool $is_published
 * @property int $display_order
 * @property int $views_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property HelpCategory $category
 */
class HelpArticle extends Model
{
    use HasUlids;

    protected $table = 'help_articles';

    /** @var string */
    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'slug',
        'title',
        'body',
        'excerpt',
        'is_published',
        'display_order',
        'views_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => SanitizedHtmlTranslations::class,
            'excerpt' => 'array',
            'is_published' => 'boolean',
            'display_order' => 'integer',
            'views_count' => 'integer',
        ];
    }

    /** @return BelongsTo<HelpCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'category_id');
    }

    /**
     * @param Builder<HelpArticle> $query
     * @return Builder<HelpArticle>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
