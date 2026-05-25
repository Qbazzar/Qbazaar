<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reports;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public Report payload.
 *
 * Admin-only fields (reviewed_by / reviewed_at / admin_notes) are
 * intentionally omitted — even the reporter doesn't see them, only the
 * Sprint 11 Filament UI does.
 *
 * @mixin Report
 */
class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->target_type->value,
            'target_id' => $this->target_id,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'description' => $this->description,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
