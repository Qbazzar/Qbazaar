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
use Throwable;

/**
 * A user applies to become a verified business, or answers a request for
 * more information by resubmitting their open application. One open
 * application per user is enforced by the database, so two concurrent
 * submissions cannot both pass. Documents are stored after the row is saved
 * and outside any transaction, so a slow upload never holds a lock.
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

        $application = DB::transaction(function () use ($open, $details): BusinessApplication {
            /** @var BusinessApplication $locked */
            $locked = BusinessApplication::query()->whereKey($open->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(BusinessApplicationStatus::PENDING)) {
                throw new DomainException(ErrorCode::BUSINESS_APPLICATION_OPEN);
            }

            $locked->forceFill([...$details, 'status' => BusinessApplicationStatus::PENDING, 'submitted_at' => now()])->save();

            return $locked;
        });

        $this->attach($application, $documents);

        activity('user')
            ->performedOn($application)
            ->causedBy($user)
            ->event('business_application_resubmitted')
            ->log('Business application resubmitted');

        return $application;
    }

    /**
     * @param list<UploadedFile> $documents
     */
    private function attach(BusinessApplication $application, array $documents): void
    {
        foreach ($documents as $document) {
            $application->addMedia($document)
                ->usingFileName($this->fileNamer->nameFor($document))
                ->toMediaCollection(BusinessApplication::DOCUMENTS_COLLECTION);
        }
    }
}
