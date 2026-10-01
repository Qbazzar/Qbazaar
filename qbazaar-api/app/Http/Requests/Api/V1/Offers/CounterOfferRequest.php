<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Offers;

/**
 * Body for `POST /api/v1/offers/{id}/counter`; same bounds as a new offer.
 */
class CounterOfferRequest extends MakeOfferRequest {}
