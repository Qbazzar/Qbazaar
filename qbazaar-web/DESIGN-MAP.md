# Design map: Next.js routes → new design

Every route in `qbazaar-web/app` mapped to its pixel reference for the M3 reskin (FE-16.3 and later).

- **Reference page**: a file in [`Qbazzar/Qbazaar-front`](https://github.com/Qbazzar/Qbazaar-front) (local copy `D:\Doc\q-bazaar`).
- **Figma frames**: node ids in file `Cvn7hexeu07FIOyJhDDCLd`, at 1440 (desktop), 744 (tablet) and 390 (phone). The pixel-true render of a frame is `D:\Doc\q-bazaar-refs\render\<d|t|p>-<name>--<id with "-">.html`; the frame inventory is `D:\Doc\q-bazaar\docs\superpowers\coverage-matrix.md`.
- **Status** says how well the reference covers what the route does today:
  - **matches**: the reference designs this screen and its states.
  - **partial**: the reference designs the screen, but some states, sections or breakpoints the route needs have no frame. The notes name the missing part and the closest reference to use for it.
  - **no reference**: nothing in Figma designs this screen. The notes propose the closest reference to build it from.

`—` in a frame column means Figma has no frame at that width; build that width from the nearest designed width with the shared responsive rules (gutter 40/30/16 px, cards stack in one column under 600 px).

## Shared chrome

| Next.js file | Reference | 1440 | 744 | 390 | Status | Notes |
|---|---|---|---|---|---|---|
| `app/layout.tsx` (site header, footer) | header and footer of every page, e.g. `index.html` | 728:44663 | 561:22024 · menu 648:39022 · guest menu 741:45470 · language 736:54497 | 584:25249 · menu 648:47458 · guest menu 741:46578 · language 741:39398 | matches | Phone/tablet header opens the menu sheet (`assets/mobilemenu.js`). |
| `app/(auth)/layout.tsx` | auth shell (`assets/auth.css`, `.qb-ahead` header) | 736:65208 | 779:39974 | 779:40299 | matches | Logo + language button only, card centred. |
| `app/account/layout.tsx` (account sidebar) | `account.html` settings sidebar (`.qb-navitem`) | 394:9270 | 561:30374 | 613:28184 (settings hub) | matches | On phone the sidebar becomes the settings hub, then one panel per screen. |

## Browse and listings

| Route | Next.js file | Reference | 1440 | 744 | 390 | Status | Notes |
|---|---|---|---|---|---|---|---|
| `/` | `app/page.tsx` | `index.html` | 728:44663 · guest 741:38067 · search open 489:14809 | 561:22024 · guest 741:47591 | 584:25249 · guest 741:48614 | partial | The recently viewed strip has no frame: build it as one more "Best Selling" style slider section of the same page. "Featured Companies" waits for the companies API (FE-16.6). |
| `/categories` | `app/categories/page.tsx` | `all-categories.html` | 185:6576 | 536:32620 | 621:27193 | matches | |
| `/c/[slug]` | `app/c/[slug]/page.tsx` | `parent-category.html` (sub-category tiles) + `category.html` (its listings) | 69:467 · 81:1629 | 539:35503 · 544:38513 | 623:28688 · 623:30012 | matches | |
| `/ads` | `app/ads/page.tsx` | `category.html` (list/grid + filter sidebar) | 18:912 · filter 250:4405 · filter open 264:4818 | 655:54431 · filters 244:3508 | 654:50760 · filter sheet 618:26974 | matches | Filters become a bottom sheet under 1000 px. |
| `/search` | `app/search/page.tsx` | `category.html` (search state) | 492:20208 · not found 655:55973 | 655:54431 · not found 655:55238 | 654:50760 · not found 608:26579 · distance 654:50586 | matches | "Save Search" is the pill next to "Add to Favorite" on 18:912. |
| `/ads/[id]` | `app/ads/[id]/page.tsx` | `product.html` | 88:776 · organisation 190:8469 | 547:39923 · organisation 534:32277 | 618:27221 · 623:29490 | partial | Seller reviews and the report action have no frame: reviews go under the seller card as rows in the notification-row style (455:14636), report is a ghost button in the seller card. Buy Now / Make an Offer wait for FE-16.8. |
| `/u/[id]` | `app/u/[id]/page.tsx` | `seller-individual.html` | 136:1562 | 548:42879 | 625:31486 | partial | Reviews and the block/report actions have no frame: reuse the seller card actions row and the notification-row style for reviews. Organisation sellers use `seller-organization.html` (145:1063, 532:27700, 616:26793) once companies exist (FE-16.6). |
| `/p/[slug]` | `app/p/[slug]/page.tsx` | none | — | — | — | no reference | CMS page. Closest: page shell of `notifications.html` (breadcrumb + 48 px title, 455:14636) with the body in the white r24 panel of `wishlist.html` (376:7817). |

## Posting an ad

| Route | Next.js file | Reference | 1440 | 744 | 390 | Status | Notes |
|---|---|---|---|---|---|---|---|
| `/post-ad` | `app/post-ad/page.tsx` | `add-ads.html` → `preview.html` → `publish.html` | category 335:7242 · form 323:10693 · delivery 324:12419 · draft 324:13009 · preview 355:6093 · publish 355:7297 | category 528:20007 · form 520:18205 · preview 532:21978 · publish 532:23641 | category 639:36814 · form 633:33029 · preview 638:35892 · publish 638:36533 | matches | The wizard steps map to the three reference pages (FE-16.5). |
| `/account/ads/[id]/edit` | `app/account/ads/[id]/edit/page.tsx` | `add-ads.html` (opened from "Complete" on My Ads) | 324:13009 | 528:19541 | 638:36172 | matches | Same form as posting, pre-filled. |

## Account

| Route | Next.js file | Reference | 1440 | 744 | 390 | Status | Notes |
|---|---|---|---|---|---|---|---|
| `/account` | `app/account/page.tsx` | `account.html` + `my-ads.html` profile header | 394:9270 · profile header 518:20536 | 561:30374 | 613:28184 | partial | The stat cards and quick actions have no frame. Closest: the stat tiles of `sales-overview.html` (502:21437, 573:33241, 600:30639). |
| `/account/profile` | `app/account/profile/page.tsx` | `account.html` Profile Settings | 393:8828 · edit name 401:11108 · photo 401:13493 | 561:26108 · photo 561:28324 | 613:28843 · photo 613:29596 | matches | |
| `/account/security` | `app/account/security/page.tsx` | `account.html` Account Settings, Edit Password | 411:9755 · done 411:9908 | 561:30374 | 613:32391 | partial | Edit Password is designed at 1440 only; tablet/phone use the Account Settings frames with the desktop form. |
| `/account/sessions` | `app/account/sessions/page.tsx` | none | — | — | — | no reference | Closest: the Account Settings rows of `account.html` (394:9270): one row per session with a ghost "Sign out" button, Modal for the confirmation. |
| `/account/verification` | `app/account/verification/page.tsx` | `enter-number.html` + `enter-code.html` | 741:37200 · 741:37607 · verify identity 736:66642 | 779:40048 | 779:40384 | partial | Designed as a standalone auth screen; here it sits inside the account shell. |
| `/account/privacy` | `app/account/privacy/page.tsx` | `account.html` Data Protection | 397:10175 | 563:29920 | 614:28775 | matches | |
| `/account/blocked-users` | `app/account/blocked-users/page.tsx` | none | — | — | — | no reference | Closest: the Following list of `users.html` (376:9268, 514:18424, 600:25354) with "Unblock" in place of "Unfollow"; empty state 376:8854. |
| `/account/data` | `app/account/data/page.tsx` | `account.html` Delete Account | 401:10866 · confirm 438:18762 | 563:28208 | 616:26253 | partial | The data-export request has no frame: one more row in the same panel. |
| `/account/ads` | `app/account/ads/page.tsx` | `my-ads.html` | 518:20536 · empty 361:9811 | 516:19505 · empty 516:19325 | 600:29942 · empty 600:28918 | partial | The status tabs have no frame on this page: use the pill tabs of `notifications.html` (455:14636). |
| `/account/messages` | `app/account/messages/page.tsx` | `messages.html` | 370:18776 · chat 365:14788 · offer buyer 667:30685 · offer seller 667:31850 · search 467:16572 | 532:24741 · chat 532:25317 · empty 532:27566 | 604:33590 · chat 604:33673 · empty 604:32472 | matches | Block/report/delete confirmations: 369:17179, 369:17437, 367:16294. |
| `/account/notifications` | `app/account/notifications/page.tsx` | `notifications.html` | 455:14636 · empty 461:14541 | 548:45200 · empty 551:45919 | 597:27743 · empty 597:29622 | matches | |
| `/account/favorites` | `app/account/favorites/page.tsx` | `wishlist.html` | 376:8322 · empty 376:7817 | — | — | partial | No tablet/phone frames: use the grids of `category.html` at 744/390 (655:54431, 654:50760). |
| `/account/recently-viewed` | `app/account/recently-viewed/page.tsx` | none | — | — | — | no reference | Closest: `wishlist.html` fill state (376:8322) with a "Clear" ghost button in place of the remove hearts. |
| `/account/saved-searches` | `app/account/saved-searches/page.tsx` | `saved-search.html` | 381:8815 · empty 381:8657 | empty 504:25797 · 512:18005 | 600:25864 · empty 600:25765 | matches | |
| `/account/support` | `app/account/support/page.tsx` | none | — | — | — | no reference | Closest: the rows of `my-ads.html` (518:20536) with a status Badge per ticket. |
| `/account/support/[id]` | `app/account/support/[id]/page.tsx` | none | — | — | — | no reference | Closest: the chat thread of `messages.html` (365:14788); the ticket timeline is the message list. |

## Auth

| Route | Next.js file | Reference | 1440 | 744 | 390 | Status | Notes |
|---|---|---|---|---|---|---|---|
| `/login` | `app/(auth)/login/page.tsx` | `login.html` | 736:65208 | 779:39974 | 779:40299 | matches | V2 passwordless login adds `enter-number.html` / `enter-code.html`. |
| `/register` | `app/(auth)/register/page.tsx` | `signup.html` | 741:36327 | — | — | partial | No tablet/phone frames: use the login card at 744/390 (779:39974, 779:40299). |
| `/verify-otp` | `app/(auth)/verify-otp/page.tsx` | `signup-verify.html` / `enter-code.html` | 741:36808 · 741:37607 | 779:40048 | 779:40384 | matches | |
| `/forgot-password` | `app/(auth)/forgot-password/page.tsx` | `forgot-password.html` | 739:36152 | 779:40111 | 779:40422 | matches | |
| `/reset-password` | `app/(auth)/reset-password/page.tsx` | `new-password.html` | 745:38739 | 779:39915 | 779:40473 | matches | |
| `/verify-email` | `app/verify-email/page.tsx` | `send-code.html` ("Check your email") | 739:36548 | 779:40163 | 779:40344 | partial | The verified / expired-link results have no frame: same card with the EmptyState icon tile. |

## Help, support and system pages

| Route | Next.js file | Reference | 1440 | 744 | 390 | Status | Notes |
|---|---|---|---|---|---|---|---|
| `/help` | `app/help/page.tsx` | none | — | — | — | no reference | Closest: home hero search (728:44663) for the help search, `all-categories.html` tiles (185:6576) for help categories. |
| `/help/c/[slug]` | `app/help/c/[slug]/page.tsx` | none | — | — | — | no reference | Closest: `parent-category.html` (69:467) with article rows in place of sub-category tiles. |
| `/help/articles/[slug]` | `app/help/articles/[slug]/page.tsx` | none | — | — | — | no reference | Same shell as `/p/[slug]`. |
| `/help/search` | `app/help/search/page.tsx` | none | — | — | — | no reference | Closest: search result / not found of `category.html` (492:20208, 655:55973) with text rows. |
| `/support` | `app/support/page.tsx` | none | — | — | — | no reference | Closest: `all-categories.html` tile cards (185:6576) for the two entry points. |
| `/support/new` | `app/support/new/page.tsx` | none | — | — | — | no reference | Closest: the add/edit form of `account.html` Billing Info (412:10263, 563:27380, 614:28114). |
| `/impersonate` | `app/impersonate/page.tsx` | none | — | — | — | no reference | Admin hand-off that only shows a status line while it signs in and redirects. Closest: the auth card shell (736:65208). |
| error boundary | `app/error.tsx`, `app/global-error.tsx` | none | — | — | — | no reference | Closest: "Search Not Found" (655:55973, 655:55238, 608:26579) built with `EmptyState`. |
| 404 | Next.js default (no `not-found.tsx`) | none | — | — | — | no reference | Same as the error boundary. |

Routes without UI, so nothing to design: `app/api/auth/refresh/route.ts`, `app/api/auth/session/route.ts`, `app/manifest.ts`, `app/robots.ts`, `app/sitemap.ts`.

## Summary

| | Count |
|---|---|
| Rows (39 `page.tsx` routes, the error boundaries, the 404 page) | 41 |
| matches | 15 |
| partial | 11 |
| no reference (closest reference proposed for each) | 15 |

Plus the 3 layouts, which all match.

## Reference pages with no Next.js route yet

Future work. These are **not** built in FE-16.1/16.2; the task that owns each is in brackets.

| Reference page | Figma frames (1440 / 744 / 390) | What it is |
|---|---|---|
| `buy-now.html` | 659:58417 / 709:32483 / 709:33450 | Buy Now request [FE-16.8] |
| `offer.html` | 657:57378 / 709:32645 / 709:33285 | Make an Offer page (today offers exist only inside chat) [FE-16.8] |
| `checkout.html` | 682:32513, 689:33725, 684:33194, 689:33489 / 710:36152 / 710:34891 | Checkout, cash and QNB [FE-16.8] |
| `payment-method.html` | 504:25438 / 573:34759 / 603:31189 | Payment methods [FE-16.8] |
| `wallet.html` | 502:22401 / 563:31406 / 613:31879 | Wallet [FE-16.8] |
| `sales-overview.html` | 502:21437 / 573:33241 / 600:30639 | Seller sales dashboard [FE-16.8] |
| `companies.html` | 179:4492 / 532:31445 / 620:28373 | Companies list [FE-16.6] |
| `seller-organization.html` | 145:1063, 167:1827, 167:2754 / 532:27700 / 616:26793 | Company seller page [FE-16.6] |
| `users.html` | 376:9466, 376:9268 / 515:18203, 514:18424 / 600:25084, 600:25354 | Followers / following [FE-16.6] |
| `premium.html` | 397:9995 / — / — | Premium subscription [not planned in M3] |
| `financing.html` | no frame (prototype-only page) | Flexible financing [not planned in M3] |
| `account.html` Billing Info | 397:9793 / 563:27165 / 614:28078 | Billing details [FE-16.8] |
| `account.html` Marketplace Info | 401:10641 / 563:30518 / 614:28948 | Marketplace info panel [FE-16.4] |
| `account.html` Edit Email / Edit Number | 403:14778, 402:13904 / 563:24091 / 613:32419, 614:26724 | Change email / phone [FE-16.4] |
| Insurance request | 125:1208 / — / — | No reference page either; not planned |
