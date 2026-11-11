<?php

declare(strict_types=1);

namespace App\Services\Business;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a business application document from the private disk so the
 * reviewer's browser can show it, without letting a crafted file run script
 * on the admin origin.
 *
 * The type sent is the one sniffed from the content at upload, never a
 * guess from the name, and nosniff stops the browser from second-guessing
 * it. Images open inside a sandbox. PDFs cannot: Chromium's viewer refuses a
 * sandboxed document, and its PDF scripting never reaches the page's origin
 * anyway. Any other type is only offered as a download.
 */
class ApplicationDocumentResponse
{
    private const string PDF = 'application/pdf';

    /** Raster images render safely inside the sandbox. */
    private const array SANDBOXED_INLINE = ['image/jpeg', 'image/png', 'image/webp'];

    public function stream(Media $document): StreamedResponse
    {
        $type = (string) $document->mime_type;
        $inline = $type === self::PDF || in_array($type, self::SANDBOXED_INLINE, true);

        $headers = [
            'Content-Type' => $inline ? $type : 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if ($type !== self::PDF) {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        return Storage::disk($document->disk)->response(
            $document->getPathRelativeToRoot(),
            $document->file_name,
            $headers,
            $inline ? 'inline' : 'attachment',
        );
    }
}
