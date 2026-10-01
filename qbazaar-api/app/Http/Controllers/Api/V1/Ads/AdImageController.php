<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ads;

use App\Actions\Ads\AttachAdImagesAction;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Ads\ReorderImagesRequest;
use App\Http\Requests\Api\V1\Ads\UploadImagesRequest;
use App\Http\Resources\Api\V1\Media\MediaResource;
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
 * Uploads only store the files; the sizes, BlurHash and pHash are produced
 * on the queue.
 *
 * @group Ads
 */
class AdImageController extends Controller
{
    /**
     * POST /api/v1/ads/{ad}/images — attach images to the ad.
     *
     * New images on an active ad send it back to pending review.
     *
     * @authenticated
     *
     * @throws DomainException
     */
    public function store(UploadImagesRequest $request, AttachAdImagesAction $attachImages, string $adId): JsonResponse
    {
        $ad = $this->findAdOrFail($adId);
        $this->authorize('manage-images', $ad);

        /** @var list<UploadedFile> $files */
        $files = array_values((array) $request->file('images'));

        $created = $attachImages($ad, $files);

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

        // `has_images` is indexed and deleting an image does not save the ad.
        if ($ad->shouldBeSearchable()) {
            $ad->searchable();
        }

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
