<?php

declare(strict_types=1);

use App\Http\Middleware\ApiResponseWrapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A response that fails the test if the wrapper decodes its JSON body.
 */
function undecodableJson(mixed $data): JsonResponse
{
    return new class($data) extends JsonResponse
    {
        public function getData($assoc = false, $depth = 512): mixed
        {
            throw new LogicException('The wrapper decoded a payload it already had as an array.');
        }
    };
}

/**
 * @return array<string, mixed>
 */
function wrapped(JsonResponse $response): array
{
    $result = (new ApiResponseWrapper)->handle(Request::create('/api/v1/anything'), fn () => $response);

    return json_decode((string) $result->getContent(), true);
}

it('wraps a plain array without decoding the body', function (): void {
    expect(wrapped(undecodableJson(['id' => 'a1'])))->toBe(['success' => true, 'data' => ['id' => 'a1']]);
});

it('flattens a paginated resource without decoding the body', function (): void {
    $body = wrapped(undecodableJson([
        'data' => [['id' => 'a1']],
        'links' => ['next' => null],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1],
    ]));

    expect($body['data'])->toBe([['id' => 'a1']])
        ->and($body['meta']['total'])->toBe(1)
        ->and($body['meta']['has_more'])->toBeFalse();
});

it('leaves an already shaped envelope alone', function (): void {
    expect(wrapped(undecodableJson(['success' => true, 'data' => []])))->toBe(['success' => true, 'data' => []]);
});
