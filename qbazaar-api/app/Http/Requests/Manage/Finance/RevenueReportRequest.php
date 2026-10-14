<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Finance;

use App\Services\Admin\Dashboard\DashboardPeriod;
use App\Services\Finance\Revenue\RevenueRange;
use App\Services\Finance\Revenue\RevenueWindow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The period of the revenue page and its export: a preset, or a custom range
 * of local (Qatar) days that is ordered, not in the future and no longer than
 * two years.
 */
class RevenueReportRequest extends FormRequest
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
            'period' => ['nullable', Rule::enum(RevenueRange::class)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateCustomRange($validator)];
    }

    public function range(): RevenueRange
    {
        return RevenueRange::fromRequest($this->validated('period'));
    }

    public function window(): RevenueWindow
    {
        $range = $this->range();
        $custom = $range === RevenueRange::CUSTOM;

        return $range->window(
            CarbonImmutable::now(DashboardPeriod::TIMEZONE),
            $custom ? $this->day('from') : null,
            $custom ? $this->day('to') : null,
        );
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    private function validateCustomRange(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || $this->input('period') !== RevenueRange::CUSTOM->value) {
            return;
        }

        $from = $this->day('from');
        $to = $this->day('to');
        $today = CarbonImmutable::now(DashboardPeriod::TIMEZONE)->startOfDay();

        match (true) {
            $from->greaterThan($to) => $validator->errors()->add('to', __('admin.revenue.errors.order')),
            $from->greaterThan($today) => $validator->errors()->add('from', __('admin.revenue.errors.future')),
            $from->diffInDays($to) >= RevenueRange::MAX_CUSTOM_DAYS => $validator->errors()->add('to', __('admin.revenue.errors.too_long')),
            default => null,
        };
    }

    private function day(string $key): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->input($key), DashboardPeriod::TIMEZONE)->startOfDay();
    }
}
