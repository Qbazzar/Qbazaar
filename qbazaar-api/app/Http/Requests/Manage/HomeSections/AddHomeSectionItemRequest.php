<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\HomeSections;

use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * The ad staff add to a home section: its id, or any admin or public link
 * that contains the id (pasted from the browser). The ad is looked up once,
 * here, and handed to the controller.
 */
class AddHomeSectionItemRequest extends FormRequest
{
    private const string ULID_PATTERN = '/(?<![0-9A-Z])([0-9A-HJKMNP-TV-Z]{26})(?![0-9A-Z])/i';

    private ?Ad $resolvedAd = null;

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
            'ad' => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('ad')) {
                    return;
                }

                $id = preg_match(self::ULID_PATTERN, (string) $this->input('ad'), $match) === 1 ? strtolower($match[1]) : null;
                $this->resolvedAd = $id === null ? null : Ad::query()->find($id);

                if ($this->resolvedAd === null) {
                    $validator->errors()->add('ad', (string) __('admin.home_sections.validation.ad_not_found'));
                }
            },
        ];
    }

    public function ad(): Ad
    {
        return $this->resolvedAd ?? throw new LogicException('The ad is only resolved by a passing validation.');
    }
}
