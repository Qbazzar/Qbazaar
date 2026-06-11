# QBazaar — QA Sweep Report (2026-06-10)

> Covers the automatable portion of the MILESTONES QA checklist (QA-1..QA-12)
> plus the dependency-audit fixes applied on `docs/qa-sweep-report`.
> Status legend: ✅ done · 🟡 partial (evidence below) · ⏸️ needs the deployed app · 👤 needs a human session.

## Summary

| # | Item | Status | Evidence / What remains |
|---|------|--------|-------------------------|
| QA-1 | Bug bash (all Sprint 1–12 user stories, manual) | 👤 | Script below. Needs a human session against the deployed app. |
| QA-2 | Lighthouse audit (every page, mobile+desktop) | ⏸️ | Run against `https://qbazaar.taqat.space` once DNS/SSL final. Target > 85 mobile (FE-M6.7 aims > 90). |
| QA-3 | Laravel Pulse review (slow queries/jobs) | ⏸️ | Needs production traffic; check after 48h of real use. |
| QA-4 | Accessibility (axe DevTools per page) | 👤 | Manual browser sweep. Note: new push/typing UI shipped with `aria-live` / `role=status` from day one. |
| QA-5 | Security audit | 🟡 | Automatable parts done — see below. OWASP checklist walk pending. |
| QA-5a | `composer audit` | ✅ | **5 advisories found → 0.** Fixed by `composer update symfony/*` (2026-06-10): CVE-2026-48761 + CVE-2026-48760 (html-sanitizer), CVE-2026-48736 (http-foundation SSRF), CVE-2026-46644 (polyfill-intl-idn, low), CVE-2026-48784 (routing). `composer audit` now: "No security vulnerability advisories found." |
| QA-5b | `npm audit` | 🟡 | 2 moderate remain, **no fix available**: postcss < 8.5.10 bundled inside `next` (GHSA-qx2v-qp2m-jg93, XSS via unescaped `</style>` in stringified CSS). Latest stable next (16.2.9) is still inside the affected range; the only "fix" npm offers is a downgrade to next@9 (nonsense). **Accepted + tracked**: app never stringifies user-supplied CSS, so practical exposure ≈ none. Re-check on each next release: `npm view next dist-tags.latest && npm audit`. |
| QA-5c | Rate limiting verified | ✅ | Limiters defined in `AppServiceProvider` (auth 5/min, otp 3/min, search 60/min, publish daily cap, messages, api 120/min) and covered by existing feature tests; new device-token + signed-media endpoints sit behind `throttle:api`. |
| QA-5d | SQLi / XSS / CSRF spot checks | 👤 | Eloquent bindings + ApiResponseWrapper give baseline; manual probes pending. Note: ad images restricted to jpg/jpeg/png/webp (no SVG vector); signed-original endpoint path-traversal-safe (DB-resolved paths, reviewed 2026-06-10). |
| QA-6 | RTL audit (`/ar/*` every page) | 👤 | Manual sweep. New UI from the gap-closure streams used logical properties / symmetric padding (reviewed). |
| QA-7 | i18n audit (no missing keys in console) | 👤 | Manual; all new strings shipped in both `ar.json`/`en.json`. |
| QA-8 | Dark mode per page | 👤 | Manual sweep. |
| QA-9 | Bundle size analysis | 🟡 | `npm run build` clean (38 routes). Firebase SDK confirmed in its own async chunk — zero main-bundle cost while push is unconfigured. Full `next-bundle-analyzer` pass pending. |
| QA-10 | DB query analysis (Telescope) | ⏸️ | Needs running traffic. Known accepted scan: duplicate-image detector is O(active media) per publish — bounded by the 10/day publish cap, escape hatch documented in `DuplicateImageDetector`. |
| QA-11 | Sentry sample errors verified | ⏸️ | Blocked on production Sentry DSN (launch-prep secret). |
| QA-12 | Backup restore drill | 👤⏸️ | Needs the production DB + a staging target. Procedure: nightly `mysqldump` → restore into a scratch schema → `php artisan migrate:status` + row-count sanity. |

## Quality gates at the time of this report (branch `docs/qa-sweep-report`, after the Symfony bump)

- API: full Feature suite after the Symfony bump — **270 passed (1203 assertions, 120s)**, run locally with Meilisearch up (`--testsuite=Feature`; `tests/Unit` only exists on the pHash branch until it merges). Pint + PHPStan level 8 clean.
- Web: `npm run typecheck` ✅, `npm run build` ✅ (exit 0, all routes).

## Dependency changes in this branch

- `qbazaar-api/composer.lock`: Symfony components bumped to the 7.4.13-era patched set (`composer update "symfony/*" --with-all-dependencies`, Windows flags `--ignore-platform-req=ext-pcntl,ext-posix`). App code untouched.

## QA-1 bug-bash script (for the human session)

Walk each flow on the deployed app, AR + EN, mobile viewport + desktop, light + dark:

1. **Auth**: register (OTP), wrong OTP ×3 (throttle message), login, refresh-survival (hard reload), logout.
2. **Account**: profile edit, avatar upload, sessions list, password change, deactivate + reactivate, data export (file arrives, link expires).
3. **Ads**: create draft → upload 10 images (11th rejected) → reorder → publish; banned-word ad → goes pending; duplicate-image ad (same photo from a second account) → goes pending; edit, mark sold, delete.
4. **Search**: typo query (after Meili lands), filters, saved search cap, suggestions.
5. **Engagement**: favorite/unfavorite, recently-viewed appears, conversation from an ad, realtime message both directions, typing indicator (new), offer create→accept (ad → sold path), unread badges.
6. **Notifications**: in-app feed, mark all read, enable browser push (after Firebase creds) → background push arrives with working click.
7. **Reports/CMS**: report an ad (weekly cap), help center pages, support ticket, static pages.
8. **Admin (Filament)**: approve/reject a pending ad (incl. a duplicate_image-flagged one — flag details visible in activity), moderation rules CRUD invalidates cache, announcement broadcast.

## Outcome

No blocking findings. The two open items that gate launch remain the launch-prep secrets
(Twilio/Sentry/Firebase) and the deployed-app audits (Lighthouse/axe/RTL/bug bash) —
tracked in ROADMAP Milestone 7.
