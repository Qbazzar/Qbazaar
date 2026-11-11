<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Enums\BusinessApplicationStatus;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\BusinessApplication;
use App\Models\User;
use App\Services\Business\BusinessApplicationQueue;
use App\Services\Media\UploadedFileNamer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * A user applies to become a verified business, or answers a request for
 * more information by resubmitting their open application. One open
 * application per user is enforced by the database, so two concurrent
 * submissions cannot both pass. Documents are stored outside any
 * transaction, so a slow upload never holds a lock, and a failed upload
 * leaves nothing behind: a new application is deleted again, a resubmitted
 * one stays waiting for information.
 */
class SubmitBusinessApplicationAction
{
    public function __construct(
        private readonly UploadedFileNamer $fileNamer,
        private readonly BusinessApplicationQueue $queue,
    ) {}

    /**
     * @param array{business_name: string, legal_name: string, commercial_registration_number: string, applicant_note: string|null} $details
     * @param list<UploadedFile> $documents
     */
    public function execute(User $user, array $details, array $documents): BusinessApplication
    {
        if ($user->isVerifiedBusiness()) {
            throw new DomainException(ErrorCode::BUSINESS_ALREADY_VERIFIED);
        }

        $open = BusinessApplication::query()->where('open_user_id', $user->id)->first();

        if ($open?->status === BusinessApplicationStatus::PENDING) {
            throw new DomainException(ErrorCode::BUSINESS_APPLICATION_OPEN);
        }

        $application = $open === null
            ? $this->create($user, $details, $documents)
            : $this->resubmit($user, $open, $details, $documents);

        $this->queue->forgetPendingCount();

        return $application;
    }

    /**
     * @param array{business_name: string, legal_name: string, commercial_registration_number: string, applicant_note: string|null} $details
     * @param list<UploadedFile> $documents
     */
    private function create(User $user, array $details, array $documents): BusinessApplication
    {
        if ($documents === []) {
            throw ValidationException::withMessages(['documents' => __('business.api.documents_required')]);
        }

        $application = new BusinessApplication;
        $application->forceFill([...$details, 'user_id' => $user->id, 'status' => BusinessApplicationStatus::PENDING, 'submitted_at' => now()]);

        try {
            $application->save();
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(ErrorCode::BUSINESS_APPLICATION_OPEN);
        }

        try {
            $this->attach($application, $documents);
        } catch (Throwable $exception) {
            $application->delete();

            throw $exception;
        }

        activity('user')
            ->performedOn($application)
            ->causedBy($user)
            ->event('business_application_submitted')
            ->log('Business application submitted');

        return $application;
    }

    /**
     * @param array{business_name: string, legal_name: string, commercial_registration_number: string, applicant_note: string|null} $details
     * @param list<UploadedFile> $documents
     */
    private function resubmit(User $user, BusinessApplication $open, array $details, array $documents): BusinessApplication
    {
        $max = (int) config('qbazaar.business.max_documents');

        if ($open->media()->count() + count($documents) > $max) {
            throw ValidationException::withMessages(['documents' => __('business.api.documents_max', ['max' => $max])]);
        }

        // The documents are stored before the application goes back to the
        // queue, so staff never review it with its answer half uploaded. When
        // the status cannot move (a concurrent resubmission won), the
        // documents stored here are removed again.
        $stored = $this->attach($open, $documents);

        try {
            $application = DB::transaction(function () use ($open, $details): BusinessApplication {
                /** @var BusinessApplication $locked */
                $locked = BusinessApplication::query()->whereKey($open->id)->lockForUpdate()->firstOrFail();

                if (! $locked->status->canTransitionTo(BusinessApplicationStatus::PENDING)) {
                    throw new DomainException(ErrorCode::BUSINESS_APPLICATION_OPEN);
                }

                $locked->forceFill([...$details, 'status' => BusinessApplicationStatus::PENDING, 'submitted_at' => now()])->save();

                return $locked;
            });
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        activity('user')
            ->performedOn($application)
            ->causedBy($user)
            ->event('business_application_resubmitted')
            ->log('Business application resubmitted');

        return $application;
    }

    /**
     * Stores every document or none: when one fails, those stored before it
     * are removed.
     *
     * @param list<UploadedFile> $documents
     * @return list<Media> the stored documents
     */
    private function attach(BusinessApplication $application, array $documents): array
    {
        $stored = [];

        try {
            foreach ($documents as $document) {
                $stored[] = $application->addMedia($document)
                    ->usingFileName($this->fileNamer->nameFor($document))
                    ->toMediaCollection(BusinessApplication::DOCUMENTS_COLLECTION);
            }
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        return $stored;
    }

    /**
     * Bounded by max_documents, and only run when a submission fails.
     *
     * @param list<Media> $documents
     */
    private function discard(array $documents): void
    {
        foreach ($documents as $document) {
            $document->delete();
        }
    }
}
