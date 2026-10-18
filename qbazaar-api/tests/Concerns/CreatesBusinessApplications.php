<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\BusinessApplication;
use Illuminate\Http\UploadedFile;

/**
 * Business applications with real documents on the (faked) private disk.
 */
trait CreatesBusinessApplications
{
    /** A small file with a real PDF signature, so the stored document has a real type. */
    public function pdfDocument(string $name = 'commercial-registration.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function applicationWithDocument(array $attributes = []): BusinessApplication
    {
        $application = BusinessApplication::factory()->create($attributes);
        $application->addMedia($this->pdfDocument())->toMediaCollection(BusinessApplication::DOCUMENTS_COLLECTION);

        return $application;
    }
}
