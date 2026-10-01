<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Enums\NotificationTopic;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Full replacement of the notification switches: every topic is required
 * under both `email` and `push`.
 *
 * @bodyParam email object required Email switch per topic. Example: {"user_messages": true, "offers": true, "listing_updates": true, "saved_search_alerts": false, "newsletters": false}
 * @bodyParam push object required Push switch per topic. Example: {"user_messages": true, "offers": true, "listing_updates": true, "saved_search_alerts": true, "newsletters": false}
 */
class UpdateNotificationPreferencesRequest extends FormRequest
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
        $rules = [
            'email' => ['required', 'array:' . implode(',', NotificationTopic::values())],
            'push' => ['required', 'array:' . implode(',', NotificationTopic::values())],
        ];

        foreach (NotificationTopic::values() as $topic) {
            $rules["email.{$topic}"] = ['required', 'boolean'];
            $rules["push.{$topic}"] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, bool>
     */
    public function switches(string $channel): array
    {
        return array_map('boolval', (array) $this->validated($channel));
    }
}
