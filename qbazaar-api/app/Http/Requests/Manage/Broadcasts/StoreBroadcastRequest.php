<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Broadcasts;

use App\Enums\BroadcastSegment;
use App\Enums\LocationType;
use App\Rules\NoMarkup;
use App\Services\Admin\Broadcasts\BroadcastDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A staff broadcast: the audience, the Arabic copy (required) and the
 * English copy (optional; English-language users get the Arabic copy
 * without it). Plain text only, since apps render it verbatim.
 */
class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.broadcast') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $title = ['string', 'max:' . (int) config('qbazaar.admin.broadcasts.title_max_length'), new NoMarkup];
        $body = ['string', 'max:' . (int) config('qbazaar.admin.broadcasts.body_max_length'), new NoMarkup];

        return [
            'segment' => ['required', Rule::enum(BroadcastSegment::class)],
            'location_id' => [
                Rule::requiredIf(fn (): bool => $this->input('segment') === BroadcastSegment::CITY->value),
                'nullable',
                'string',
                Rule::exists('locations', 'id')->where('type', LocationType::CITY->value),
            ],
            'title_ar' => ['required', ...$title],
            'body_ar' => ['required', ...$body],
            'title_en' => ['nullable', 'required_with:body_en', ...$title],
            'body_en' => ['nullable', 'required_with:title_en', ...$body],
            'send_email' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'segment' => (string) __('admin.broadcasts.fields.segment'),
            'location_id' => (string) __('admin.broadcasts.fields.city'),
            'title_ar' => (string) __('admin.broadcasts.fields.title_ar'),
            'body_ar' => (string) __('admin.broadcasts.fields.body_ar'),
            'title_en' => (string) __('admin.broadcasts.fields.title_en'),
            'body_en' => (string) __('admin.broadcasts.fields.body_en'),
        ];
    }

    public function draft(): BroadcastDraft
    {
        return new BroadcastDraft(
            segment: BroadcastSegment::from((string) $this->validated('segment')),
            locationId: $this->filled('location_id') ? (string) $this->validated('location_id') : null,
            titleAr: trim((string) $this->validated('title_ar')),
            bodyAr: trim((string) $this->validated('body_ar')),
            titleEn: $this->optionalText('title_en'),
            bodyEn: $this->optionalText('body_en'),
            sendEmail: $this->boolean('send_email'),
        );
    }

    private function optionalText(string $key): ?string
    {
        $value = trim((string) $this->validated($key));

        return $value === '' ? null : $value;
    }
}
