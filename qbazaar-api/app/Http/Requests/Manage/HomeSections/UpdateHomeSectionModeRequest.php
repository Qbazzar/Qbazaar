<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\HomeSections;

use App\Enums\HomeSectionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Switches a home section between automatic and manual mode.
 */
class UpdateHomeSectionModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(HomeSectionMode::class)],
        ];
    }

    public function mode(): HomeSectionMode
    {
        return HomeSectionMode::from((string) $this->validated('mode'));
    }
}
