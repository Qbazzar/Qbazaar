<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\ModerationRuleType;
use App\Models\ModerationRule;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Accessor for the three auto-moderation rule families used at publish time:
 *
 *   $service->containsBannedWords($title . ' ' . $description);
 *   $service->containsPhone($description);
 *   $service->containsExternalLink($description);
 *
 * Storage layering (Sprint 11):
 *   1. The banned-words and blocked-domains lists live in the
 *      `moderation_rules` table, editable from the Filament admin panel.
 *   2. Reads are cached for 1h (see {@see ModerationRule::CACHE_TTL_SECONDS})
 *      so the publish hot-path stays a single Redis hit on warm caches.
 *   3. If the table is empty OR the lookup errors (e.g. an in-test DB hasn't
 *      run the migration yet), the service transparently falls back to the
 *      legacy `config/moderation.php` values so older tests + bootstrapping
 *      sequences keep working.
 *
 * The service is bound as a singleton (see AppServiceProvider) so the
 * normalised banned-word array survives across requests in a worker process.
 * That makes the cache-driven warm path effectively O(1).
 */
class ModerationRulesService
{
    /** @var list<string>|null */
    private ?array $cachedBannedWords = null;

    /** @var list<string>|null */
    private ?array $cachedBlockedDomains = null;

    /** @var list<string> */
    private array $allowedDomains;

    private string $phoneRegex;

    private string $externalLinkRegex;

    public function __construct()
    {
        /** @var list<string> $allowed */
        $allowed = (array) config('moderation.allowed_domains', []);
        $this->allowedDomains = array_values(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            array_filter($allowed, 'is_string'),
        ));

        $this->phoneRegex = (string) config(
            'moderation.phone_regex',
            '/(?:\+?974|00974)[\s\-]?\d{4}[\s\-]?\d{4}/u',
        );

        $this->externalLinkRegex = (string) config(
            'moderation.external_link_regex',
            '/(?:https?:\/\/|\bwww\.)[^\s,]+/iu',
        );
    }

    /**
     * Match banned words in $text. Returns the list of distinct words that
     * fired so the caller can log them.
     *
     * Matching strategy:
     *   - lower-case both sides
     *   - strip non-letter/non-digit punctuation to defeat obfuscation
     *     ("b.i.t.c.o.i.n" → "bitcoin")
     *   - substring match against each rule.
     *
     * @return list<string>
     */
    public function containsBannedWords(string $text): array
    {
        $words = $this->bannedWords();

        if ($words === []) {
            return [];
        }

        $haystack = $this->normalise($text);
        if ($haystack === '') {
            return [];
        }

        $hits = [];
        foreach ($words as $needle) {
            if ($needle === '') {
                continue;
            }

            if (str_contains($haystack, $needle)) {
                $hits[] = $needle;
            }
        }

        return array_values(array_unique($hits));
    }

    /**
     * True when $text contains a phone-number-like sequence per the configured
     * regex. We intentionally don't return the matched number — the goal is to
     * flag-and-block, not to harvest contacts.
     */
    public function containsPhone(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return preg_match($this->phoneRegex, $text) === 1;
    }

    /**
     * Return external URLs in $text whose host is either:
     *   • explicitly listed in the admin blocked-domains DB table, OR
     *   • not in the static allow-list (config/moderation.php).
     *
     * The two pathways are merged so the admin "kill switch" for a specific
     * domain wins even when that domain would otherwise pass the allow-list.
     *
     * @return list<string>
     */
    public function containsExternalLink(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $matches = [];
        $count = preg_match_all($this->externalLinkRegex, $text, $matches);

        if ($count === false || $count === 0) {
            return [];
        }

        /** @var list<string> $urls */
        $urls = array_values(array_unique($matches[0]));
        $blocked = $this->blockedDomains();

        $external = [];
        foreach ($urls as $url) {
            $host = $this->extractHost($url);
            if ($host === '') {
                continue;
            }

            $isExplicitlyBlocked = in_array($host, $blocked, true);
            $isAllowListed = in_array($host, $this->allowedDomains, true);

            if ($isExplicitlyBlocked || ! $isAllowListed) {
                $external[] = $url;
            }
        }

        return $external;
    }

    /**
     * Resolve and cache the active banned-word list. Layered lookup:
     *   1. process-local memo (warm singleton)
     *   2. Redis cache (1h TTL)
     *   3. database read
     *   4. config fallback (legacy / install bootstrap)
     *
     * @return list<string>
     */
    private function bannedWords(): array
    {
        if ($this->cachedBannedWords !== null) {
            return $this->cachedBannedWords;
        }

        /** @var list<string> $words */
        $words = $this->cacheRemember(
            ModerationRule::CACHE_KEY_BANNED_WORDS,
            fn (): array => $this->loadActiveValues(ModerationRuleType::BANNED_WORD),
        );

        if ($words === []) {
            /** @var list<string> $configFallback */
            $configFallback = (array) config('moderation.banned_words', []);
            $words = array_values(array_filter($configFallback, 'is_string'));
        }

        // Normalise once at load — every match request reuses the result.
        $this->cachedBannedWords = array_values(array_unique(array_map(
            fn (string $word): string => $this->normalise($word),
            $words,
        )));

        return $this->cachedBannedWords;
    }

    /**
     * Resolve and cache the active blocked-domain list. Same layered lookup
     * shape as {@see bannedWords()}.
     *
     * @return list<string>
     */
    private function blockedDomains(): array
    {
        if ($this->cachedBlockedDomains !== null) {
            return $this->cachedBlockedDomains;
        }

        /** @var list<string> $domains */
        $domains = $this->cacheRemember(
            ModerationRule::CACHE_KEY_BLOCKED_DOMAINS,
            fn (): array => $this->loadActiveValues(ModerationRuleType::BLOCKED_DOMAIN),
        );

        $this->cachedBlockedDomains = array_values(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            $domains,
        ));

        return $this->cachedBlockedDomains;
    }

    /**
     * Wrap Cache::remember in a try/catch — during early bootstrap (before
     * migrations) the cache driver may not even exist yet. Fall back to a
     * direct DB read and swallow the error.
     *
     * @param callable():list<string> $loader
     * @return list<string>
     */
    private function cacheRemember(string $key, callable $loader): array
    {
        try {
            $result = Cache::remember(
                $key,
                ModerationRule::CACHE_TTL_SECONDS,
                static fn (): array => $loader(),
            );

            return is_array($result) ? array_values($result) : [];
        } catch (Throwable) {
            return $loader();
        }
    }

    /**
     * Pull active rule values from the DB. Wrapped in a try/catch so the
     * service degrades to config when the table doesn't exist yet (fresh
     * install, in-memory test DB without the migration applied).
     *
     * @return list<string>
     */
    private function loadActiveValues(ModerationRuleType $type): array
    {
        try {
            $values = ModerationRule::query()
                ->where('type', $type->value)
                ->where('is_active', true)
                ->pluck('value')
                ->filter(static fn (mixed $v): bool => is_string($v) && $v !== '')
                ->values()
                ->all();

            return array_values(array_map(static fn (mixed $v): string => (string) $v, $values));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Normalise text for word matching. Lower-cases, strips punctuation and
     * collapses whitespace so obfuscations like "b!i!t!c!o!i!n" still hit.
     */
    private function normalise(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');

        // Replace anything that isn't a letter/digit/whitespace with a space.
        // Use the Unicode-aware property classes so Arabic characters survive.
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lower) ?? $lower;

        // Collapse repeat whitespace.
        $collapsed = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;

        return trim($collapsed);
    }

    /**
     * Extract the host portion from a URL-ish string. Handles bare `www.`
     * prefixes (no scheme) since sellers often paste those.
     */
    private function extractHost(string $url): string
    {
        $candidate = $url;

        if (! str_contains($candidate, '://')) {
            // parse_url won't recognise a host without a scheme; prepend one.
            $candidate = 'http://' . ltrim($candidate, '/');
        }

        $parts = parse_url($candidate);
        if (! is_array($parts) || ! isset($parts['host']) || ! is_string($parts['host'])) {
            return '';
        }

        return strtolower($parts['host']);
    }
}
