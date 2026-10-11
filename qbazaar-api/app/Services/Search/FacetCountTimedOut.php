<?php

declare(strict_types=1);

namespace App\Services\Search;

use RuntimeException;

/**
 * A facet count ran past its statement time limit. Thrown, not returned, so
 * the cache around the count never stores the empty answer in its place.
 */
class FacetCountTimedOut extends RuntimeException {}
