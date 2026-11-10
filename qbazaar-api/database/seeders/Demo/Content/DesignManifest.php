<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Content;

use RuntimeException;

/**
 * The sample content of the original HTML design (listings, sellers,
 * photos, chats, reviews), read from design/manifest.json. Every title and
 * description exists in Arabic and English; photos are optimised copies of
 * the design's own images in design/images.
 *
 * @phpstan-type Bilingual array{en: string, ar: string}
 * @phpstan-type DesignAd array{
 *     key: string, seller: string, category: string, title: Bilingual, description: Bilingual,
 *     price: int|null, currency: string, price_type: string, condition: string|null, location: string,
 *     posted_minutes_ago: int, badges: list<string>, images: list<string>, custom_fields?: array<string, mixed>
 * }
 * @phpstan-type Persona array<string, mixed>
 */
final class DesignManifest
{
    public const string DEFAULT_PATH = __DIR__ . '/../design/manifest.json';

    /** @var array<string, array<string, mixed>> parsed manifests by path */
    private static array $cache = [];

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(private readonly string $path = self::DEFAULT_PATH)
    {
        $this->data = self::$cache[$path] ??= $this->read($path);
    }

    /**
     * @return list<DesignAd>
     */
    public function ads(): array
    {
        return $this->data['ads'];
    }

    /**
     * @return list<Persona>
     */
    public function personas(): array
    {
        return $this->data['personas'];
    }

    /**
     * @return Persona|null the persona the nth demo member plays, in account order (buyer, seller, member01…)
     */
    public function personaAt(int $memberIndex): ?array
    {
        return $this->personas()[$memberIndex] ?? null;
    }

    /**
     * @return Persona|null
     */
    public function persona(string $key): ?array
    {
        foreach ($this->personas() as $persona) {
            if ($persona['key'] === $key) {
                return $persona;
            }
        }

        return null;
    }

    public function personaIndex(string $key): ?int
    {
        foreach ($this->personas() as $index => $persona) {
            if ($persona['key'] === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companies(): array
    {
        return $this->data['companies'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->data['categories'];
    }

    /**
     * @return array<string, string> design city => district slug
     */
    public function locations(): array
    {
        return $this->data['locations'];
    }

    public function districtSlug(string $designCity): string
    {
        return $this->locations()[$designCity] ?? throw new RuntimeException("Design city [{$designCity}] has no district mapping.");
    }

    /**
     * Photo keys that fit a leaf category, empty when the design has none.
     *
     * @return list<string>
     */
    public function pool(string $leafSlug): array
    {
        return $this->data['photo_pools'][$leafSlug] ?? [];
    }

    /**
     * @return list<string> leaf slugs that have design photos
     */
    public function pooledLeafSlugs(): array
    {
        return array_map(strval(...), array_keys($this->data['photo_pools']));
    }

    /**
     * Chat scripts (buyer line first) for a language, those written for the
     * leaf category and the generic ones.
     *
     * @return list<list<string>>
     */
    public function chatScripts(string $language, ?string $leafSlug): array
    {
        $scripts = [];

        foreach ($this->data['chats'] as $chat) {
            if ($chat['leaf'] === null || $chat['leaf'] === $leafSlug) {
                $scripts[] = array_map(strval(...), array_values($chat['lines'][$language]));
            }
        }

        return $scripts;
    }

    public function reviewText(int $rating, string $language): ?string
    {
        foreach ($this->data['reviews'] as $review) {
            if ($review['rating'] === $rating) {
                return $review['text'][$language];
            }
        }

        return null;
    }

    public function imagePath(string $key): string
    {
        $file = $this->data['images'][$key]['file'] ?? throw new RuntimeException("Design image [{$key}] is not in the manifest.");

        return dirname($this->path) . '/images/' . $file;
    }

    /**
     * @return list<string> what is wrong with the manifest; empty when it is sound
     */
    public function problems(): array
    {
        $problems = [];

        foreach ($this->data['images'] as $key => $image) {
            if (! is_file(dirname($this->path) . '/images/' . $image['file'])) {
                $problems[] = "Image file missing for [{$key}].";
            }
        }

        foreach ($this->data['ads'] as $ad) {
            array_push($problems, ...$this->adProblems($ad));
        }

        foreach ($this->personas() as $persona) {
            array_push($problems, ...$this->personaProblems($persona));
        }

        foreach ($this->data['photo_pools'] as $leaf => $keys) {
            array_push($problems, ...$this->missingImages("Pool [{$leaf}]", $keys));
        }

        return $problems;
    }

    /**
     * @param DesignAd $ad
     * @return list<string>
     */
    private function adProblems(array $ad): array
    {
        $problems = $this->missingImages("Ad [{$ad['key']}]", $ad['images']);
        $needsNullPrice = in_array($ad['price_type'], ['free', 'contact'], true);

        if ($ad['images'] === []) {
            $problems[] = "Ad [{$ad['key']}] has no photos.";
        }

        if ($this->persona($ad['seller']) === null) {
            $problems[] = "Ad [{$ad['key']}] has an unknown seller [{$ad['seller']}].";
        }

        if (! isset($this->locations()[$ad['location']])) {
            $problems[] = "Ad [{$ad['key']}] has an unmapped location [{$ad['location']}].";
        }

        foreach (['title', 'description'] as $field) {
            if (trim($ad[$field]['en']) === '' || trim($ad[$field]['ar']) === '') {
                $problems[] = "Ad [{$ad['key']}] misses its {$field} in one language.";
            }
        }

        if ($needsNullPrice !== ($ad['price'] === null)) {
            $problems[] = "Ad [{$ad['key']}] price does not fit its price type.";
        }

        return $problems;
    }

    /**
     * @param Persona $persona
     * @return list<string>
     */
    private function personaProblems(array $persona): array
    {
        $images = array_filter([$persona['avatar'], $persona['business']['cover'] ?? null]);

        return $this->missingImages("Persona [{$persona['key']}]", array_values($images));
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     */
    private function missingImages(string $owner, array $keys): array
    {
        $missing = array_filter($keys, fn (string $key): bool => ! isset($this->data['images'][$key]));

        return array_values(array_map(static fn (string $key): string => "{$owner} uses unknown image [{$key}].", $missing));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $path): array
    {
        $json = is_file($path) ? file_get_contents($path) : false;

        if ($json === false) {
            throw new RuntimeException("Design manifest not found at [{$path}].");
        }

        /** @var array<string, mixed> */
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
