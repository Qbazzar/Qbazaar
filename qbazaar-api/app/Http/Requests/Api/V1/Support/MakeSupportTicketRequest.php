<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Support;

use App\Enums\SupportTicketCategory;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MakeSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => [
                'required',
                'string',
                'min:' . (int) config('qbazaar.support.subject_min_length'),
                'max:' . (int) config('qbazaar.support.subject_max_length'),
            ],
            'category' => ['required', Rule::enum(SupportTicketCategory::class)],
            'body' => [
                'required',
                'string',
                'min:' . (int) config('qbazaar.support.body_min_length'),
                'max:' . (int) config('qbazaar.support.body_max_length'),
            ],
            'email' => [
                $this->submitter() === null ? 'required' : 'nullable',
                'email',
                'max:120',
            ],
        ];
    }

    /**
     * The route is public, so the default (session) guard never sees the
     * caller's Bearer token; resolve it through Sanctum explicitly.
     */
    public function submitter(): ?User
    {
        $user = $this->user('sanctum');

        return $user instanceof User ? $user : null;
    }
}
