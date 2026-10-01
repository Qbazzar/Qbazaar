<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\SaveAddressRequest;
use App\Http\Resources\Api\V1\Account\AddressResource;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\Account\SavedAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in user's saved delivery addresses. The list is bounded by
 * `qbazaar.account.max_addresses`, so it is returned without pagination.
 *
 * @group Account
 */
class AddressController extends Controller
{
    public function __construct(private readonly SavedAddressService $addresses) {}

    /**
     * List saved addresses, the default first.
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $addresses = UserAddress::query()
            ->ownedBy($user)
            ->orderByDesc('is_default')
            ->latest()
            ->limit((int) config('qbazaar.account.max_addresses'))
            ->get();

        return response()->json(AddressResource::collection($addresses)->resolve($request));
    }

    /**
     * Save a new address. The first address always becomes the default.
     *
     * @authenticated
     */
    public function store(SaveAddressRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $address = $this->addresses->create($user, $request->addressAttributes(), $request->wantsDefault());

        return response()->json((new AddressResource($address))->resolve($request), Response::HTTP_CREATED);
    }

    /**
     * Change any subset of an address; `is_default: true` makes it the default.
     *
     * @authenticated
     */
    public function update(SaveAddressRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $address = $this->addresses->update($user, $id, $request->addressAttributes(), $request->wantsDefault());

        return response()->json((new AddressResource($address))->resolve($request));
    }

    /**
     * Delete an address. Deleting the default promotes the newest remaining one.
     *
     * @authenticated
     */
    public function destroy(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->addresses->delete($user, $id);

        return response()->noContent();
    }
}
