<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Roles;

use App\Enums\StaffRole;
use App\Services\Admin\Roles\PermissionMatrix;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The permission matrix as submitted: role name => ticked permission names.
 * Only super_admin may submit it, whatever permissions their roles hold.
 */
class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(StaffRole::SUPER_ADMIN->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $matrix = app(PermissionMatrix::class);
        $editable = $matrix->editableRoles()->pluck('name')->all();

        return [
            'permissions' => [
                'sometimes',
                'array',
                function (string $attribute, mixed $value, Closure $fail) use ($editable): void {
                    if (is_array($value) && array_diff(array_keys($value), $editable) !== []) {
                        $fail(__('admin.permission_matrix.errors.unknown_role'));
                    }
                },
            ],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', Rule::in($matrix->assignable())],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public function matrix(): array
    {
        /** @var array<string, list<string>> $matrix */
        $matrix = $this->validated('permissions', []);

        return $matrix;
    }
}
