<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Moderation;

use App\Enums\SanctionAction;
use Illuminate\Validation\Rule;

/**
 * Act on a report in one step: the sanction to apply to its target, with its
 * reason. The route needs reports.action; the chosen sanction also needs its
 * own permission (ads.suspend, ads.delete or users.ban).
 */
class SanctionFromReportRequest extends SanctionRequest
{
    public function authorize(): bool
    {
        $action = SanctionAction::tryFrom((string) $this->input('action'));

        // An unknown action fails validation below, with a message, instead of a bare 403.
        return $action === null || ($this->user()?->can($action->permission()) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::enum(SanctionAction::class)],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['action' => (string) __('admin.moderation.fields.action'), ...parent::attributes()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => (string) __('admin.moderation.validation.action_required'),
            'action.enum' => (string) __('admin.moderation.validation.action_required'),
            ...parent::messages(),
        ];
    }

    public function action(): SanctionAction
    {
        return SanctionAction::from((string) $this->validated('action'));
    }
}
