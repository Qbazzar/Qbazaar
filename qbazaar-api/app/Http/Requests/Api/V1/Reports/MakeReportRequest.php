<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reports;

use App\Enums\ReportCategory;
use App\Enums\ReportTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body for `POST /api/v1/reports`.
 *
 * Cross-field business rules (self-report refusal, target-existence,
 * duplicate-report rate limit) live in {@see App\Actions\Reports\MakeReportAction}
 * because they involve database lookups and emit specific ErrorCodes that
 * Form Requests can't carry cleanly. The Form Request only enforces shape
 * and enum membership.
 */
class MakeReportRequest extends FormRequest
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
            'target_type' => ['required', 'string', Rule::enum(ReportTarget::class)],
            'target_id' => ['required', 'string', 'size:26'],
            'category' => ['required', 'string', Rule::enum(ReportCategory::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
