<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Services\Media\UploadedFileNamer;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sets or clears the business cover banner. The collection holds one file,
 * so a new upload replaces the old one; the crop runs on the queue.
 */
class ReplaceBusinessCoverAction
{
    public function __construct(
        private readonly UploadedFileNamer $fileNamer,
    ) {}

    /**
     * @throws DomainException
     */
    public function upload(User $owner, UploadedFile $file): Media
    {
        $this->ensureBusiness($owner);

        return $owner->addMedia($file)
            ->usingFileName($this->fileNamer->nameFor($file))
            ->toMediaCollection(User::BUSINESS_COVER_COLLECTION);
    }

    /**
     * @throws DomainException
     */
    public function remove(User $owner): void
    {
        $this->ensureBusiness($owner);

        $owner->clearMediaCollection(User::BUSINESS_COVER_COLLECTION);
    }

    /**
     * @throws DomainException
     */
    private function ensureBusiness(User $owner): void
    {
        if (! $owner->isBusiness()) {
            throw new DomainException(ErrorCode::BUSINESS_ACCOUNT_REQUIRED);
        }
    }
}
