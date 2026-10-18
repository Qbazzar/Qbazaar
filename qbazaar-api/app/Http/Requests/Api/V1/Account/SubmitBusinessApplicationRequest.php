<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Multipart body for `POST /api/v1/account/business-application`.
 *
 * A first application needs at least one document; answering a request for
 * more information may add documents or only correct the details (the
 * action enforces both, since only it knows which case applies).
 *
 * @bodyParam business_name string required The trading name shown to buyers (2–120 chars).
 * @bodyParam legal_name string required The registered legal name (2–160 chars).
 * @bodyParam commercial_registration_number string required The commercial registration (CR) number: letters, digits, "-" and "/" (≤40 chars).
 * @bodyParam applicant_note string Optional note to the reviewers (≤1000 chars).
 * @bodyParam documents file[] Commercial registration, licence or ID: PDF, JPEG, PNG or WebP.
 */
class SubmitBusinessApplicationRequest extends FormRequest
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
            'business_name' => ['required', 'string', 'min:2', 'max:120'],
            'legal_name' => ['required', 'string', 'min:2', 'max:160'],
            'commercial_registration_number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'applicant_note' => ['nullable', 'string', 'max:1000'],
            'documents' => ['sometimes', 'array', 'max:' . (int) config('qbazaar.business.max_documents')],
            'documents.*' => [
                'file',
                'max:' . (int) config('qbazaar.business.document_max_size_kb'),
                'mimetypes:' . implode(',', (array) config('qbazaar.business.document_mime_types')),
            ],
        ];
    }

    /**
     * @return array{business_name: string, legal_name: string, commercial_registration_number: string, applicant_note: string|null}
     */
    public function details(): array
    {
        $note = trim(strip_tags((string) $this->validated('applicant_note')));

        return [
            'business_name' => $this->clean('business_name'),
            'legal_name' => $this->clean('legal_name'),
            'commercial_registration_number' => strtoupper($this->clean('commercial_registration_number')),
            'applicant_note' => $note === '' ? null : $note,
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function documents(): array
    {
        $files = $this->file('documents');

        if ($files === null) {
            return [];
        }

        return array_values(is_array($files) ? $files : [$files]);
    }

    private function clean(string $key): string
    {
        return trim(strip_tags((string) $this->validated($key)));
    }
}
