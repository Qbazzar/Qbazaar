<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ads\ReorderImagesRequest;
use App\Http\Requests\Api\V1\Ads\UploadImagesRequest;
use App\Http\Resources\Api\V1\Media\MediaResource;
use App\Jobs\ProcessAdImagesJob;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Ad image management — upload, reorder, delete.
 *
 * Conversions (thumbnail/medium/large/original_webp) run synchronously at
 * upload time so the response carries every variant URL. BlurHashes are
 * generated asynchronously via {@see ProcessAdImagesJob}.
 *
 * @group Ads
 */
class AdImageController extends Controller
{
    /**
     * POST /api/v1/ads/{ad}/images — attach 1..10 images to the ad.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function store(UploadImagesRequest $request, string $adId): JsonResponse
    {
        $ad = $this->findAdOrFail($adId);
        $this->authorize('manage-images', $ad);

        /** @var array<int, UploadedFile> $files */
        $files = (array) $request->file('images');

        $created = [];
        foreach ($files as $file) {
            $media = $ad->addMedia($file->getPathname())
                ->usingFileName($file->getClientOriginalName())
                ->toMediaCollection('images');

            $created[] = $media;
        }

        // BlurHash + future pHash run async on the `low` queue.
        ProcessAdImagesJob::dispatch(array_map(
            static fn (Media $m): string => (string) $m->getKey(),
            $created,
        ));

        return response()
            ->json([
                'images' => array_map(
                    static fn (Media $m): array => (new MediaResource($m))->toArray($request),
                    $created,
                ),
            ])
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * POST /api/v1/ads/{ad}/images/reorder — set the new display order.
     *
     * Validates that every supplied media ID belongs to the ad before
     * calling Spatie's setNewOrder helper. Any foreign ID surfaces as
     * AD_IMAGE_NOT_FOUND so attackers can't reorder unrelated media.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function reorder(ReorderImagesRequest $request, string $adId): Response
    {
        $ad = $this->findAdOrFail($adId);
        $this->authorize('manage-images', $ad);

        /** @var array<int, int|string> $orderInput */
        $orderInput = (array) $request->validated('order', []);
        $order = array_map(static fn ($id): int => (int) $id, $orderInput);

        $ownedIds = $ad->getMedia('images')
            ->map(static fn (Media $m): int => (int) $m->getKey())
            ->all();

        foreach ($order as $candidate) {
            if (! in_array($candidate, $ownedIds, true)) {
                throw new DomainException(ErrorCode::AD_IMAGE_NOT_FOUND);
            }
        }

        Media::setNewOrder($order);

        return response()->noContent();
    }

    /**
     * DELETE /api/v1/media/{media} — remove a single image.
     *
     * Ownership is enforced by verifying the media row belongs to an Ad
     * the caller owns. We deliberately don't expose a "delete by ad" route
     * because the frontend already has the media row's stable ID.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function destroy(Request $request, int|string $mediaId): Response
    {
        $media = Media::query()->find($mediaId);

        if ($media === null || $media->model_type !== Ad::class) {
            throw new DomainException(ErrorCode::AD_IMAGE_NOT_FOUND);
        }

        /** @var Ad|null $ad */
        $ad = Ad::query()->find($media->model_id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_IMAGE_NOT_FOUND);
        }

        $this->authorize('manage-images', $ad);

        $media->delete();

        return response()->noContent();
    }

    /**
     * @throws DomainException
     */
    private function findAdOrFail(string $id): Ad
    {
        $ad = Ad::query()->find($id);

        if ($ad === null) {
            throw new DomainException(ErrorCode::AD_NOT_FOUND);
        }

        return $ad;
    }
}
