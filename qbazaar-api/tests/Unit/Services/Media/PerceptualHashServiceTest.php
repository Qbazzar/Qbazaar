<?php

declare(strict_types=1);

use App\Services\Media\PerceptualHashService;

function makeTestImage(int $seed): string
{
    $img = imagecreatetruecolor(64, 64);
    mt_srand($seed);
    for ($x = 0; $x < 64; $x += 8) {
        for ($y = 0; $y < 64; $y += 8) {
            $c = imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            if ($c !== false) {
                imagefilledrectangle($img, $x, $y, $x + 7, $y + 7, $c);
            }
        }
    }
    $path = sys_get_temp_dir() . '/phash-test-' . $seed . '.png';
    imagepng($img, $path);
    imagedestroy($img);

    return $path;
}

it('produces a 16-char hex hash', function (): void {
    $service = new PerceptualHashService;
    $hash = $service->hash(makeTestImage(1));

    expect($hash)->toMatch('/^[0-9a-f]{16}$/');
});

it('is deterministic for the same image', function (): void {
    $service = new PerceptualHashService;

    expect($service->hash(makeTestImage(2)))->toBe($service->hash(makeTestImage(2)));
});

it('survives a resize of the same image (distance 0..4)', function (): void {
    $service = new PerceptualHashService;
    $original = makeTestImage(3);

    $src = imagecreatefrompng($original);
    assert($src !== false);
    $resized = imagescale($src, 32, 32);
    assert($resized !== false);
    $resizedPath = sys_get_temp_dir() . '/phash-test-resized.png';
    imagepng($resized, $resizedPath);

    $hashOriginal = $service->hash($original);
    $hashResized = $service->hash($resizedPath);
    assert($hashOriginal !== null && $hashResized !== null);

    $distance = $service->distance($hashOriginal, $hashResized);
    expect($distance)->toBeLessThanOrEqual(4);
});

it('returns a large distance for unrelated images', function (): void {
    $service = new PerceptualHashService;

    $hashA = $service->hash(makeTestImage(4));
    $hashB = $service->hash(makeTestImage(5));
    assert($hashA !== null && $hashB !== null);

    $distance = $service->distance($hashA, $hashB);
    expect($distance)->toBeGreaterThan((int) config('qbazaar.moderation.phash_distance_threshold'));
});

it('returns null for an unreadable file', function (): void {
    expect((new PerceptualHashService)->hash('/nonexistent.jpg'))->toBeNull();
});
