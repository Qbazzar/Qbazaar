# MVP Gap Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the 4 remaining MVP gaps: pHash image dedup + signed original URLs, typing indicators, FCM push scaffold, Meilisearch production install + QA sweep report.

**Architecture:** Each gap is an independent work stream delivered on its own branch/PR, in this order: (1) `feat/phash-dedup-and-signed-originals`, (2) `feat/typing-indicators`, (3) `feat/fcm-push-scaffold`, (4) `chore/meili-production` + `docs/qa-sweep-report`. Backend follows the existing Controllers → Actions/Services → Models layering, ULID keys, `ApiResponseWrapper` envelope, Pest feature tests. Frontend follows the existing axios client + TanStack Query + Zustand + Echo hooks patterns.

**Tech Stack:** Laravel 12 (PHP 8.4, GD, Spatie MediaLibrary v11, Sanctum, Reverb), `laravel-notification-channels/fcm` v6 (already in composer.json), Next.js 16 + laravel-echo + firebase JS SDK, MySQL 8 (`BIT_COUNT`/`CONV` for Hamming distance), Meilisearch (systemd on the WHM server).

**Verified facts this plan relies on:**
- `config/qbazaar.php` already has `moderation.phash_distance_threshold = 8`.
- `.env` already has `FCM_PROJECT_ID` / `FCM_CREDENTIALS` placeholders; `laravel-notification-channels/fcm: ^6.1` already required.
- `ProcessAdImagesJob` docblock reserves Wave B for pHash; per-image try/catch pattern in place.
- Models use `HasUlids` (see `refresh_tokens` migration for the FK pattern).
- Moderation publish gate: `ModerateAdAction` → `ModerationResult(clean, flags, details)` → `PublishAdController` routes flagged ads to `AdStatus::PENDING`.
- Originals currently exposed as plain public URLs via `MediaResource::url`.
- Echo client (`qbazaar-web/lib/echo/client.ts`) uses the Reverb broadcaster; Reverb accepts `client-*` whispers on private channels natively — no backend change needed for typing.
- `qbazaar-web` has NO test framework — frontend changes are verified manually (documented per task).

---

## Work Stream 1 — pHash dedup + signed original URLs

**Branch:** `feat/phash-dedup-and-signed-originals` (off `main`)

### Task 1.1: PerceptualHashService (dHash, GD — no new dependency)

**Files:**
- Create: `qbazaar-api/app/Services/Media/PerceptualHashService.php`
- Test: `qbazaar-api/tests/Unit/Services/PerceptualHashServiceTest.php`

- [ ] **Step 1: Write the failing unit test**

```php
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
            imagefilledrectangle($img, $x, $y, $x + 7, $y + 7, $c);
        }
    }
    $path = sys_get_temp_dir() . '/phash-test-' . $seed . '.png';
    imagepng($img, $path);
    imagedestroy($img);

    return $path;
}

it('produces a 16-char hex hash', function (): void {
    $service = new PerceptualHashService();
    $hash = $service->hash(makeTestImage(1));

    expect($hash)->toMatch('/^[0-9a-f]{16}$/');
});

it('is deterministic for the same image', function (): void {
    $service = new PerceptualHashService();

    expect($service->hash(makeTestImage(2)))->toBe($service->hash(makeTestImage(2)));
});

it('survives a resize of the same image (distance 0..4)', function (): void {
    $service = new PerceptualHashService();
    $original = makeTestImage(3);

    $src = imagecreatefrompng($original);
    $resized = imagescale($src, 32, 32);
    $resizedPath = sys_get_temp_dir() . '/phash-test-resized.png';
    imagepng($resized, $resizedPath);

    $distance = $service->distance($service->hash($original), $service->hash($resizedPath));
    expect($distance)->toBeLessThanOrEqual(4);
});

it('returns a large distance for unrelated images', function (): void {
    $service = new PerceptualHashService();

    $distance = $service->distance(
        $service->hash(makeTestImage(4)),
        $service->hash(makeTestImage(5)),
    );
    expect($distance)->toBeGreaterThan((int) config('qbazaar.moderation.phash_distance_threshold'));
});

it('returns null for an unreadable file', function (): void {
    expect((new PerceptualHashService())->hash('/nonexistent.jpg'))->toBeNull();
});
```

- [ ] **Step 2: Run to verify it fails** — `php artisan test --filter=PerceptualHashServiceTest` → FAIL (class not found)

- [ ] **Step 3: Implement the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 64-bit difference hash (dHash) for near-duplicate image detection.
 *
 * Why dHash instead of a DCT pHash library: GD is already the project's
 * image backend (see BlurHashGeneratorService), dHash needs no extra
 * dependency, and it is robust against the transforms we care about
 * (re-encode, resize, mild compression). The config key keeps the
 * historical name `phash_distance_threshold`.
 *
 * Hash format: 16 lowercase hex chars (64 bits). Stored on `media.phash`
 * as CHAR(16) so MySQL can compute Hamming distance with
 * BIT_COUNT(CONV(a,16,10) ^ CONV(b,16,10)) without PHP signed-int issues.
 */
class PerceptualHashService
{
    private const SAMPLE_W = 9;
    private const SAMPLE_H = 8;

    public function hash(string $path): ?string
    {
        try {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                return null;
            }

            $source = @imagecreatefromstring($contents);
            if ($source === false) {
                return null;
            }

            $sample = imagescale($source, self::SAMPLE_W, self::SAMPLE_H, IMG_BICUBIC);
            imagedestroy($source);
            if ($sample === false) {
                return null;
            }

            $bits = '';
            for ($y = 0; $y < self::SAMPLE_H; $y++) {
                $previous = $this->luminanceAt($sample, 0, $y);
                for ($x = 1; $x < self::SAMPLE_W; $x++) {
                    $current = $this->luminanceAt($sample, $x, $y);
                    $bits .= $current > $previous ? '1' : '0';
                    $previous = $current;
                }
            }
            imagedestroy($sample);

            return str_pad(
                strtolower(base_convert_64($bits)),
                16,
                '0',
                STR_PAD_LEFT,
            );
        } catch (Throwable $e) {
            Log::warning('phash.failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function distance(string $hexA, string $hexB): int
    {
        $a = str_pad((string) hex2bin($hexA), 8, "\0", STR_PAD_LEFT);
        $b = str_pad((string) hex2bin($hexB), 8, "\0", STR_PAD_LEFT);

        $distance = 0;
        for ($i = 0; $i < 8; $i++) {
            $distance += substr_count(decbin(ord($a[$i]) ^ ord($b[$i])), '1');
        }

        return $distance;
    }

    private function luminanceAt(\GdImage $image, int $x, int $y): float
    {
        $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));

        return 0.299 * $rgb['red'] + 0.587 * $rgb['green'] + 0.114 * $rgb['blue'];
    }
}
```

**Note:** `base_convert_64()` is NOT a PHP builtin — implement the 64-bit binary-string → hex conversion in 4×16-bit chunks inside the class (`base_convert` is float-unsafe above 2^53):

```php
    private function bitsToHex(string $bits): string
    {
        $hex = '';
        foreach (str_split($bits, 16) as $chunk) {
            $hex .= str_pad(dechex((int) bindec($chunk)), 4, '0', STR_PAD_LEFT);
        }

        return $hex;
    }
```

Use `$this->bitsToHex($bits)` instead of the `base_convert_64`/`str_pad` block above.

- [ ] **Step 4: Run tests** — `php artisan test --filter=PerceptualHashServiceTest` → PASS
- [ ] **Step 5: Commit** — `feat(media): add GD-based dHash PerceptualHashService`

### Task 1.2: `phash` column + compute in ProcessAdImagesJob

**Files:**
- Create: `qbazaar-api/database/migrations/2026_06_10_000001_add_phash_to_media_table.php`
- Modify: `qbazaar-api/app/Jobs/ProcessAdImagesJob.php` (inject service, compute + persist after blurhash)
- Test: `qbazaar-api/tests/Feature/Ads/ProcessAdImagesJobTest.php` (extend existing test if present, else create)

- [ ] **Step 1: Migration** — `$table->char('phash', 16)->nullable()->index()` on `media` (index for exact-match fast path; distance scan filters by ads scope first).
- [ ] **Step 2: Failing test** — dispatch job on an uploaded fake image (`Storage::fake` per `AdImageUploadEndpointTest` conventions), assert `media.phash` matches `/^[0-9a-f]{16}$/`.
- [ ] **Step 3: Implement** — in the per-media try/catch where blurhash is computed: `$media->phash = $this->hasher->hash($media->getPath()); $media->save();` (inject `PerceptualHashService $hasher` into `handle()` alongside the blurhash service). A null hash is fine — same graceful-degradation contract as blurhash.
- [ ] **Step 4: Run** `php artisan test --filter=ProcessAdImagesJob` → PASS, and `vendor/bin/phpstan analyse` clean.
- [ ] **Step 5: Commit** — `feat(media): compute and store phash in ProcessAdImagesJob`

### Task 1.3: Duplicate-image flag in the moderation gate

**Files:**
- Create: `qbazaar-api/app/Services/Moderation/DuplicateImageDetector.php`
- Modify: `qbazaar-api/app/Actions/Ads/ModerateAdAction.php` (add the 4th check)
- Test: `qbazaar-api/tests/Feature/Moderation/DuplicateImageTest.php`

- [ ] **Step 1: Failing feature test** — seed: user A has an ACTIVE ad whose media has phash `X`; user B publishes a draft whose media has the same phash. Assert publish responds 200 with `data.status === 'pending'` and a `duplicate_image` flag is recorded. Also assert: same-user republish does NOT flag, and distance > threshold does NOT flag.
- [ ] **Step 2: Implement detector**

```php
<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\AdStatus;
use App\Models\Ad;
use Illuminate\Support\Facades\DB;

/**
 * Finds ACTIVE ads (other sellers only) whose images are perceptually
 * near-duplicates of the candidate ad's images.
 *
 * Distance is computed in MySQL: BIT_COUNT(CONV(a,16,10) ^ CONV(b,16,10)).
 * This is a scan over active ads' media — acceptable at MVP scale; if it
 * ever shows up in Pulse, add a BK-tree or stored bigint + generated
 * columns. Scope is constrained first (model_type, status) so the BIT_COUNT
 * runs on a small set.
 */
class DuplicateImageDetector
{
    /** @return list<string> ad ULIDs that contain a near-duplicate image */
    public function findDuplicateAdIds(Ad $ad): array
    {
        $hashes = $ad->media()->whereNotNull('phash')->pluck('phash')->all();
        if ($hashes === []) {
            return [];
        }

        $threshold = (int) config('qbazaar.moderation.phash_distance_threshold');

        $query = DB::table('media')
            ->join('ads', 'ads.id', '=', 'media.model_id')
            ->where('media.model_type', $ad->getMorphClass())
            ->where('ads.status', AdStatus::ACTIVE->value)
            ->where('ads.user_id', '!=', $ad->user_id)
            ->whereNotNull('media.phash')
            ->where(function ($q) use ($hashes, $threshold): void {
                foreach ($hashes as $hash) {
                    $q->orWhereRaw(
                        'BIT_COUNT(CONV(media.phash, 16, 10) ^ CONV(?, 16, 10)) <= ?',
                        [$hash, $threshold],
                    );
                }
            });

        return $query->distinct()->pluck('ads.id')->map(fn ($id) => (string) $id)->all();
    }
}
```

- [ ] **Step 3: Wire into `ModerateAdAction`** — after the three text checks, call the detector; on hits add flag `duplicate_image` with details `['duplicate_ad_ids' => $ids]`, merging into the existing `ModerationResult` composition (follow the exact pattern the action uses for `banned_words`).
- [ ] **Step 4: Run** `php artisan test --filter=DuplicateImage` then the whole Moderation + Ads suites → PASS.
- [ ] **Step 5: Commit** — `feat(moderation): hold ads with near-duplicate images for review`

### Task 1.4: Signed URLs for originals

**Files:**
- Create: `qbazaar-api/app/Http/Controllers/Api/V1/Media/MediaOriginalController.php`
- Modify: `qbazaar-api/routes/api_v1.php` (signed route), `qbazaar-api/app/Http/Resources/Api/V1/Media/MediaResource.php` (`url` → temporary signed URL), `qbazaar-api/config/qbazaar.php` (add `uploads.original_url_ttl_hours => 24`), `qbazaar-api/app/Models/Ad.php` (`imagesPayload()` same change)
- Test: `qbazaar-api/tests/Feature/Media/MediaOriginalSignedUrlTest.php`

- [ ] **Step 1: Failing test** — (a) `MediaResource` `url` field contains `signature=` and points to `api.v1.media.original`; (b) GET that signed URL → 200 + correct content-type; (c) GET with tampered signature → 403; (d) GET after expiry (`$this->travel(25)->hours()`) → 403.
- [ ] **Step 2: Route** — follow the existing data-export pattern (`routes/api_v1.php:198-200`):

```php
Route::get('media/{media}/original', MediaOriginalController::class)
    ->middleware('signed')
    ->name('api.v1.media.original');
```

Public + signed (no `auth:sanctum`): ad images are public content; signing only adds expiry so originals can't be hotlinked permanently. Controller: resolve `Media`, abort 404 unless `collection_name === 'images'`, then `response()->file($media->getPath())`.
- [ ] **Step 3: MediaResource change** — replace line 34 `'url' => $this->resource->getUrl(),` with a private method:

```php
'url' => $this->signedOriginalUrl(),
// ...
private function signedOriginalUrl(): string
{
    return URL::temporarySignedRoute(
        'api.v1.media.original',
        now()->addHours((int) config('qbazaar.uploads.original_url_ttl_hours', 24)),
        ['media' => $this->resource->getKey()],
    );
}
```

Mirror the same change in `Ad::imagesPayload()`. The four `sizes.*` conversion URLs stay plain public URLs (unchanged contract for the grid/feed). Update `qbazaar-contracts/openapi/v1.yaml` media schema description for `url`.
- [ ] **Step 4: Run** the Media + Ads feature suites → PASS; `vendor/bin/pint --test` clean.
- [ ] **Step 5: Commit** — `feat(media): serve original-resolution images via expiring signed URLs`

> **Deliberately out of scope (documented):** physically relocating originals to the private disk. Conversions and originals share the public disk today; moving originals requires a file-relocation command on production. The signed URL closes the *contract* gap (no permanent original URL in API responses). Log a follow-up in the Post-MVP backlog: "move originals to private disk + relocation command".

- [ ] **Final:** push branch, open PR `feat: pHash dedup + signed original URLs`, CI green.

---

## Work Stream 2 — Typing indicators (frontend-only, Reverb whispers)

**Branch:** `feat/typing-indicators` (off `main`)

**No backend changes:** Reverb implements the Pusher protocol's `client-*` events on private channels; `conversation.{id}` auth already returns `{id, name}` for exactly this purpose (see `routes/channels.php` comment).

### Task 2.1: `useTypingIndicator` hook

**Files:**
- Create: `qbazaar-web/lib/echo/useTypingIndicator.ts`

- [ ] **Step 1: Implement the hook**

```typescript
'use client';

/**
 * Typing indicator over Reverb client events (whispers) on the private
 * `conversation.{id}` channel. No server round-trip: whispers go
 * socket→socket, so there is nothing to persist and nothing to throttle
 * server-side. We self-throttle the outgoing whisper to one per 2s and
 * decay the incoming "is typing" state after 3s of silence.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho } from './client';
import { useAuthStore } from '@/store/auth';

const WHISPER_THROTTLE_MS = 2_000;
const TYPING_DECAY_MS = 3_000;

interface TypingWhisper {
  user_id: string;
  name: string;
}

export function useTypingIndicator(conversationId: string | null | undefined): {
  typingName: string | null;
  notifyTyping: () => void;
} {
  const me = useAuthStore((s) => s.user);
  const [typingName, setTypingName] = useState<string | null>(null);
  const lastWhisperAt = useRef(0);
  const decayTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const channelRef = useRef<ReturnType<Awaited<ReturnType<typeof getEcho>>['private']> | null>(null);

  useEffect(() => {
    if (!conversationId) return;
    let cancelled = false;

    (async () => {
      const echo = await getEcho();
      if (!echo || cancelled) return;

      const channel = echo.private(`conversation.${conversationId}`);
      channelRef.current = channel;

      channel.listenForWhisper('typing', (payload: TypingWhisper) => {
        if (!payload?.user_id || payload.user_id === me?.id) return;
        setTypingName(payload.name ?? '');
        if (decayTimer.current) clearTimeout(decayTimer.current);
        decayTimer.current = setTimeout(() => setTypingName(null), TYPING_DECAY_MS);
      });
    })();

    return () => {
      cancelled = true;
      if (decayTimer.current) clearTimeout(decayTimer.current);
      channelRef.current?.stopListeningForWhisper?.('typing');
      channelRef.current = null;
      setTypingName(null);
    };
  }, [conversationId, me?.id]);

  const notifyTyping = useCallback(() => {
    const now = Date.now();
    if (now - lastWhisperAt.current < WHISPER_THROTTLE_MS) return;
    lastWhisperAt.current = now;
    channelRef.current?.whisper?.('typing', {
      user_id: me?.id,
      name: me?.full_name ?? '',
    } satisfies Partial<TypingWhisper>);
  }, [me?.id, me?.full_name]);

  return { typingName, notifyTyping };
}
```

(Adjust the auth-store selector/field names to the actual `store/auth.ts` user shape — check `full_name` vs `name` before coding.)

- [ ] **Step 2: Commit** — `feat(messaging): typing-indicator hook over Reverb whispers`

### Task 2.2: Wire into chat UI + i18n

**Files:**
- Modify: `qbazaar-web/components/messaging/ConversationView.tsx` (call hook, render indicator above input / under header)
- Modify: `qbazaar-web/components/messaging/ChatInput.tsx` (accept `onTyping?: () => void`, call on input change)
- Modify: `qbazaar-web/i18n/ar.json` + `qbazaar-web/i18n/en.json` (key `messaging.typing`: ar `"يكتب الآن…"`, en `"typing…"`)

- [ ] **Step 1: Wire** — in `ConversationView`: `const { typingName, notifyTyping } = useTypingIndicator(conversationId);` pass `onTyping={notifyTyping}` to `ChatInput`; render `{typingName && <p className="...muted small">{t('messaging.typing', 'يكتب الآن…')}</p>}` (reuse existing muted-text classes from `MessageList`; animate with the project's existing pulse/opacity utility if one exists).
- [ ] **Step 2: Manual verification (no FE test framework)** — run API + Reverb + web locally (Reverb needs its own terminal on Windows per project quirks), open the same conversation as two users in two browsers, type in one → "يكتب الآن…" appears in the other within ~1s and disappears ~3s after typing stops. Verify RTL + dark mode rendering.
- [ ] **Step 3: Commit** — `feat(messaging): show typing indicator in conversation view`; push, PR, CI green.

---

## Work Stream 3 — FCM push scaffold (works the moment Firebase creds land)

**Branch:** `feat/fcm-push-scaffold` (off `main`)

**Gating principle:** every moving part no-ops cleanly when config is absent. Backend gate: `config('services.fcm.credentials')` file exists. Frontend gate: `NEXT_PUBLIC_FCM_*` env vars present.

### Task 3.1: `device_tokens` table + model

**Files:**
- Create: `qbazaar-api/database/migrations/2026_06_10_000002_create_device_tokens_table.php`
- Create: `qbazaar-api/app/Models/DeviceToken.php`
- Create: `qbazaar-api/database/factories/DeviceTokenFactory.php`

- [ ] **Step 1: Migration** (mirrors `refresh_tokens` ULID pattern):

```php
Schema::create('device_tokens', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('token', 512)->unique();
    $table->string('platform', 16)->default('web'); // web|android|ios
    $table->timestamp('last_used_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'platform']);
});
```

- [ ] **Step 2: Model** — `final class DeviceToken extends Model` with `HasUlids`, `$fillable = ['user_id','token','platform','last_used_at']`, `belongsTo(User::class)`. Factory: random 152-char token string, platform `web`.
- [ ] **Step 3: User relation + FCM routing** — on `User`:

```php
/** @return HasMany<DeviceToken, $this> */
public function deviceTokens(): HasMany
{
    return $this->hasMany(DeviceToken::class);
}

/** @return list<string> FCM registration tokens for this user's devices */
public function routeNotificationForFcm(): array
{
    return $this->deviceTokens()->pluck('token')->all();
}
```

- [ ] **Step 4: Run** `php artisan migrate` + `php artisan test` (no regressions) → commit `feat(push): device_tokens table + model + FCM routing`.

### Task 3.2: Register/unregister endpoints

**Files:**
- Create: `qbazaar-api/app/Http/Controllers/Api/V1/Account/DeviceTokenController.php`
- Create: `qbazaar-api/app/Http/Requests/Api/V1/Account/RegisterDeviceTokenRequest.php`
- Modify: `qbazaar-api/routes/api_v1.php` (under the `account/*` auth group)
- Test: `qbazaar-api/tests/Feature/Account/DeviceTokenEndpointsTest.php`
- Modify: `qbazaar-contracts/openapi/v1.yaml` (two endpoints + schemas)

- [ ] **Step 1: Failing tests** — Pest, `Sanctum::actingAs`:
  - `POST /api/v1/account/device-tokens {token, platform}` → 201, row exists.
  - Same token re-registered by the same user → 200, no duplicate row (upsert touches `last_used_at`).
  - Same token re-registered by a DIFFERENT user → token row moves to the new user (device changed owner — last login wins).
  - Missing/over-long token → 422. Unauthenticated → 401.
  - `DELETE /api/v1/account/device-tokens {token}` → 204, row gone; deleting someone else's token → 204 but row untouched (silent no-op, no enumeration).
- [ ] **Step 2: Implement** — Request rules: `'token' => ['required','string','max:512']`, `'platform' => ['sometimes', Rule::in(['web','android','ios'])]`. Controller store: `DeviceToken::updateOrCreate(['token' => $validated['token']], ['user_id' => $request->user()->id, 'platform' => ..., 'last_used_at' => now()])`; status 201 when `wasRecentlyCreated` else 200. Destroy: `$request->user()->deviceTokens()->where('token', $token)->delete(); return response()->noContent();` Routes under the existing `auth:sanctum` + `active.user` account group.
- [ ] **Step 3: Run** → PASS → commit `feat(push): device-token register/unregister endpoints`.

### Task 3.3: FCM channel on existing notifications + stale-token pruning

**Files:**
- Modify: `qbazaar-api/config/services.php` (add `fcm` block)
- Create: `qbazaar-api/app/Notifications/Concerns/SendsFcmPush.php`
- Modify: `app/Notifications/Ads/AdApprovedNotification.php`, `AdRejectedNotification.php`, `AdExpiredNotification.php`, `AdExpiringSoonNotification.php`, `SystemAnnouncementNotification.php`, `DataExportReadyNotification.php`
- Create: `qbazaar-api/app/Listeners/PruneStaleDeviceTokens.php` (+ register in `AppServiceProvider::boot()` next to the existing `NotificationSent` listener wiring)
- Test: `qbazaar-api/tests/Feature/Notifications/FcmChannelTest.php`

- [ ] **Step 1: services config**

```php
'fcm' => [
    'project_id' => env('FCM_PROJECT_ID'),
    'credentials' => env('FCM_CREDENTIALS') ? base_path(env('FCM_CREDENTIALS')) : null,
],
```

(Package v6 reads kreait credentials; confirm the exact config keys the installed `laravel-notification-channels/fcm` + `kreait/laravel-firebase` versions expect — check `vendor/.../config` once on the branch — and align names.)
- [ ] **Step 2: Trait**

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use Illuminate\Notifications\AnonymousNotifiable;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * Opt-in FCM delivery for database notifications. The push payload is
 * derived from the same toArray() data so web push and the in-app feed
 * never disagree. No-ops (channel not added) unless FCM credentials are
 * configured AND the notifiable has at least one registered device.
 */
trait SendsFcmPush
{
    protected function fcmEnabledFor(object $notifiable): bool
    {
        $credentials = config('services.fcm.credentials');

        return is_string($credentials)
            && is_file($credentials)
            && ! $notifiable instanceof AnonymousNotifiable
            && method_exists($notifiable, 'deviceTokens')
            && $notifiable->deviceTokens()->exists();
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        $data = $this->toArray($notifiable);

        return (new FcmMessage(notification: new FcmNotification(
            title: (string) ($data['title'] ?? config('app.name')),
            body: (string) ($data['body'] ?? ''),
        )))->data([
            'category' => (string) ($data['category'] ?? ''),
            'cta_url' => (string) ($data['cta_url'] ?? ''),
        ]);
    }
}
```

In each notification's `via()`: `if ($this->fcmEnabledFor($notifiable)) { $channels[] = FcmChannel::class; }` (keep the existing mail/database array intact).
- [ ] **Step 3: Pruning listener** — listen to `Illuminate\Notifications\Events\NotificationFailed`; when `$event->channel === FcmChannel::class` and the report marks the token unregistered/invalid, delete that `DeviceToken` row. (v6 puts kreait `SendReport`s in `$event->data['report']` — verify the exact shape in vendor and adapt.)
- [ ] **Step 4: Tests** — with `Notification::fake()` you can't exercise channel selection, so test `via()` directly: (a) no credentials file → `via()` lacks `FcmChannel::class`; (b) fake a credentials file (`config()->set` + `tempnam`) + user with a DeviceToken → `via()` contains it; (c) `toFcm()` title/body match `toArray()` payload. Run full Notifications suite → PASS.
- [ ] **Step 5: Commit** — `feat(push): FCM channel on user notifications, gated by credentials`.

### Task 3.4: Frontend — service worker + token registration

**Files:**
- Create: `qbazaar-web/public/firebase-messaging-sw.js`
- Create: `qbazaar-web/lib/push/fcm.ts`
- Create: `qbazaar-web/components/notifications/EnablePushButton.tsx` (rendered in `NotificationsClient.tsx`)
- Modify: `qbazaar-web/lib/api/notifications.ts` (add `registerDeviceToken` / `unregisterDeviceToken` calls)
- Modify: `qbazaar-web/i18n/ar.json` + `en.json` (`notifications.enable_push`, `notifications.push_enabled`, `notifications.push_denied`)
- Modify: `qbazaar-web/.env.example` — `NEXT_PUBLIC_FCM_API_KEY`, `NEXT_PUBLIC_FCM_PROJECT_ID`, `NEXT_PUBLIC_FCM_SENDER_ID`, `NEXT_PUBLIC_FCM_APP_ID`, `NEXT_PUBLIC_FCM_VAPID_KEY`

- [ ] **Step 1:** `npm install firebase` (modular SDK).
- [ ] **Step 2: `lib/push/fcm.ts`** — `isPushConfigured()` (all 5 env vars present + `'Notification' in window`); `enablePush()`: request permission → `initializeApp` → `getMessaging` → `getToken({vapidKey, serviceWorkerRegistration})` after `navigator.serviceWorker.register('/firebase-messaging-sw.js')` → `registerDeviceToken({token, platform:'web'})` via the shared axios client; `onMessage` foreground handler → increment Zustand unread + invalidate notification queries (same code path as the Echo `notification.created` handler — reuse it).
- [ ] **Step 3: Service worker** — classic importScripts compat build (`firebase/compat`), config inlined from the same public values; `messaging.onBackgroundMessage` → `self.registration.showNotification(title, {body, data:{cta_url}})`; `notificationclick` → `clients.openWindow(cta_url)`.
- [ ] **Step 4: UI** — `EnablePushButton` on the notifications page: hidden when `!isPushConfigured()`; states: default → enabled (persist a localStorage flag) → denied (explain re-enable via browser settings). Strings via `t()` in both locales.
- [ ] **Step 4b: Logout cleanup (review finding)** — on user-initiated logout, the FE must call `unregisterDeviceToken` + Firebase `deleteToken()` before clearing auth; otherwise a shared computer keeps showing the previous user's pushes. (Server-side revocation events — deactivate/password-reset/deletion — delete device_tokens rows in the backend, added in Task 3.3.)
- [ ] **Step 5: Manual verification** — without env vars: button absent, no SW registered, zero console errors (the no-creds path is the one that ships). With a throwaway Firebase project: permission prompt → token row appears in `device_tokens` → trigger `AdApprovedNotification` via tinker → browser push received with correct deep link.
- [ ] **Step 6: Commit** — `feat(push): web push registration UI + FCM service worker, env-gated`; push, PR.

**Handover note for the user (goes in PR description):** to activate push in production: create Firebase project → Project Settings → Service accounts → generate private key JSON → upload to `storage/app/firebase-credentials.json` on the server + set `FCM_PROJECT_ID` → Cloud Messaging → Web Push certificates → copy VAPID pair into the web app's Vercel env (`NEXT_PUBLIC_FCM_*`) → redeploy. Zero code changes.

---

## Work Stream 4 — Meilisearch on production + QA sweep

**Branch:** `chore/meili-production` (repo changes) — server steps executed over SSH/WHM.

### Task 4.1: Meilisearch install assets

**Files:**
- Create: `deploy/systemd/meilisearch.service`
- Modify: `deploy/README.md` (install runbook section)

- [ ] **Step 1: systemd unit**

```ini
[Unit]
Description=Meilisearch (QBazaar)
After=network.target

[Service]
User=meilisearch
ExecStart=/usr/local/bin/meilisearch --config-file-path /etc/meilisearch.toml
Restart=always
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
```

- [ ] **Step 2: Runbook in deploy/README.md** (server, as root):

```bash
curl -L https://install.meilisearch.com | sh && mv ./meilisearch /usr/local/bin/
useradd -r -s /sbin/nologin meilisearch
mkdir -p /var/lib/meilisearch && chown meilisearch: /var/lib/meilisearch
cat > /etc/meilisearch.toml <<EOF
env = "production"
master_key = "$(openssl rand -hex 32)"   # SAVE THIS — goes into .env MEILISEARCH_KEY
db_path = "/var/lib/meilisearch"
http_addr = "127.0.0.1:7700"
EOF
chmod 600 /etc/meilisearch.toml
# copy deploy/systemd/meilisearch.service to /etc/systemd/system/ then:
systemctl daemon-reload && systemctl enable --now meilisearch
curl -s http://127.0.0.1:7700/health   # {"status":"available"}
```

Then in the app `.env`: `SCOUT_DRIVER=meilisearch`, `MEILISEARCH_HOST=http://127.0.0.1:7700`, `MEILISEARCH_KEY=<master_key>`, followed by `php artisan config:cache && php artisan scout:sync-index-settings && php artisan scout:import "App\Models\Ad"` (run as the site user). Verify: search endpoint returns typo-tolerant results (`curl 'https://qbazaar.taqat.space/api/v1/search?q=iphnoe'`).
- [ ] **Step 3: Commit** — `chore(deploy): meilisearch systemd unit + production runbook`; PR.

### Task 4.2: QA sweep (automatable portion) + report

**Branch:** `docs/qa-sweep-report`
**Files:** Create `qbazaar-contracts/QA-REPORT-2026-06.md`

- [ ] **Step 1: Run audits locally, capture real output:**
  - `cd qbazaar-api && composer audit`
  - `cd qbazaar-web && npm audit --omit=dev` (and full `npm audit` recorded separately)
  - `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`
  - Full Pest suite (Meilisearch must be running locally — known project quirk)
  - `cd qbazaar-web && npm run lint && npm run build`
- [ ] **Step 2: Fix what the audits surface** — dependency bumps for high/critical advisories only (each as its own commit with the advisory ID in the message). Anything requiring a major-version migration → record in the report as a tracked item instead.
- [ ] **Step 3: Write `QA-REPORT-2026-06.md`** — table per QA-1..QA-12 item from MILESTONES: status (✅ done / 🟡 partial / ⏸️ needs deployed app / 👤 needs human), evidence (command + output summary), and the concrete remaining manual checklist: bug bash script (the user-story walk), Lighthouse runs against the deployed URL, axe sweep, RTL page-by-page list, backup-restore drill.
- [ ] **Step 4: Update `qbazaar-contracts/ROADMAP.md`** — new dated entry: gaps 1–4 closed (with PR links), hosting decision logged (WHM `qbazaar.taqat.space` replaces the CloudPanel VPS), Meili runbook reference, stale "7 open PRs" status corrected.
- [ ] **Step 5: Commit + PR** — `docs(qa): 2026-06 QA sweep report + roadmap refresh`.

---

## Execution order & verification matrix

| # | Branch | Backend tests | Manual verification |
|---|--------|--------------|---------------------|
| 1 | `feat/phash-dedup-and-signed-originals` | PerceptualHash unit + DuplicateImage + SignedUrl feature suites, full Pest, PHPStan, Pint | Upload same photo from 2nd account → ad goes PENDING in Filament |
| 2 | `feat/typing-indicators` | none (FE-only) | 2 browsers, typing appears/decays; RTL + dark mode |
| 3 | `feat/fcm-push-scaffold` | DeviceToken endpoints + FcmChannel via()/toFcm() suites | No-env: silent no-op. With throwaway Firebase: end-to-end push |
| 4 | `chore/meili-production` + `docs/qa-sweep-report` | full suite still green | typo-tolerant search on production URL; QA report committed |

Each PR merges to `main` independently; deploy to the server after streams 1+3 merge (migrations: `media.phash`, `device_tokens`).
