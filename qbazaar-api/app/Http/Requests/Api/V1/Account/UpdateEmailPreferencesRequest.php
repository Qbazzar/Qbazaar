<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Enums\NotificationTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Partial update of the email switches: any subset of the topics, at least one.
 *
 * @bodyParam newsletters boolean News and announcements by email. Example: false
 * @bodyParam user_messages boolean New chat messages by email. Example: true
 */
class UpdateEmailPreferencesRequest extends FormRequest
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
        $rules = [];

        foreach (NotificationTopic::values() as $topic) {
            $rules[$topic] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->safe()->collect()->isEmpty()) {
                    $validator->errors()->add('preferences', __('validation.required', ['attribute' => 'preferences']));
                }
            },
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function switches(): array
    {
        return array_map('boolval', $this->validated());
    }
}
