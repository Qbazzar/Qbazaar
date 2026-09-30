<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * Builds the on-disk name for user uploads. Client-supplied names are never
 * trusted: a name like "shell.php.jpg" or "x.html" on the public disk can end
 * up executed or rendered by the web server. The extension is derived from
 * the sniffed content type instead.
 */
class UploadedFileNamer
{
    public function nameFor(UploadedFile $file): string
    {
        $extension = $file->guessExtension()
            ?? throw new UnexpectedValueException('Cannot determine the extension of an uploaded file.');

        return Str::ulid() . '.' . $extension;
    }
}
