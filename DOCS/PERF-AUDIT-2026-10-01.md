# مراجعة أداء وتحمّل الضغط — QBazaar API

> **التاريخ:** 2026-10-01 · **النطاق:** `qbazaar-api` على `main` الحالي (بعد #160–#166) + `deploy/` · **النوع:** قراءة فقط، ما تغيّر ولا ملف.
> **البيئة المستهدفة:** سيرفر cPanel واحد: PHP-FPM + MySQL + Redis + Horizon (`default`/`low`) + Meilisearch + Reverb + Next.js، و٢٠ صورة للإعلان.
> **ترقيم الملاحظات:** `PERF2-xx` (منفصل عن `PERF-xx` تبع `DOCS/AUDIT-2026-09-30.md`). وين في مهمة مفتوحة أصلاً بـ `MILESTONES-V2.md` بذكرها.

---

## 1. الملخص التنفيذي

النظام مبني بشكل نظيف: الـ Actions والـ Services واضحة، وفي `lockForUpdate` بالأماكن الحساسة، والعدّادات denormalized، وScout مع `after_commit`. المشكلة إنو **في كم نقطة كلفتها بتكبر مع حجم البيانات كلها (O(N))، وبتشتغل جوّا الطلب نفسه**. هاد بيمشي منيح بالتجربة، بس تحت الضغط الحقيقي ممكن يوقّع السيرفر.

**أخطر ٥ أشياء:**

1. **فحص الصور المكررة (pHash)** بيمسح **كل صور كل الإعلانات الفعّالة** بـ PHP، وبيشتغل **جوّا transaction** وهو قافل صف المستخدم وصف الإعلان (`FOR UPDATE`) وقت النشر والتعديل. مع ٥٠ ألف إعلان يعني مئات آلاف الصفوف بكل نشر. *(PERF2-01)*
2. **كاش الصفحة الرئيسية وعدّادات التصنيفات بينمسح مع كل تغيير بحالة أي إعلان** (نشر، موافقة، بيع، انتهاء…)، وبعدها كل الطلبات اللي بتجي سوا بتعيد بناءه بنفس اللحظة (cache stampede). وإعادة البناء فيها `GROUP BY` على كل الإعلانات الفعّالة وفرز على `favorites_count` بدون index. *(PERF2-02)*
3. **تجديد التوكن (refresh) بيستعمل bcrypt مرتين** (`Hash::check` و`Hash::make` بـ 12 rounds) كل ١٥ دقيقة لكل مستخدم فاتح. مع ٥ آلاف مستخدم نشط بيروح تقريباً ٣ cores بس على هالشغلة. *(PERF2-03)*
4. **طابور واحد بيخلط الشات الفوري مع معالجة الصور**: الـ broadcasts تبع الرسائل وإشعارات الموبايل وScout وتحويلات الصور كلهم على `default`، والإعدادات `tries=1` و`timeout=60`. يعني لو حدا رفع ٢٠ صورة ممكن تتأخر رسائل الشات دقايق، وأي فشل مؤقت (SMTP، FCM، Meili) بيضيع الشغلة نهائياً. *(PERF2-06)*
5. **السيرفر نفسه:** إعدادات PHP-FPM الافتراضية بـ cPanel هي `max_children=5`. كمان Reverb ماشي عبر Apache، وكل WebSocket بيحجز worker من Apache، والـ unit ما فيها `LimitNOFILE` (يعني الحد تقريباً ١٠٢٤ اتصال). وRedis واحد للطوابير والكاش بدون سياسة eviction موثّقة. *(PERF2-08/09/10)*

**النقاط القوية الموجودة:** الـ ULIDs، والفهارس الأساسية للـ feed والـ dashboard والانتهاء، و`lazyById` بالـ sweeps، و`primaryImage` بـ `ofMany` بدل تحميل كل المعرض، و`Cache::lock` على رفع الصور، والـ idempotency على النشر، و`ShouldHandleEventsAfterCommit`، و`afterCommit` للأحداث، وعدّادات المفضلة والمشاهدات denormalized.

**رأيي بالأولوية:** ٦ مهام لازم يخلصوا قبل أي إطلاق عام (PERF2-01، 02، 03، 06، 08، 09)، و٤ مهام قبل الانتقال لـ Cloudflare (PERF2-04، 10، 24 ومعهم OPS-18.9).

---

## 2. جدول الملاحظات

> الخطورة: **critical** = ممكن يوقّع الخدمة أو يقفل الـ DB تحت ضغط عادي · **high** = تدهور واضح أو خسارة بيانات/شغل تحت الضغط · **medium** = كلفة بتكبر مع الحجم · **low** = تحسين أو مخاطرة محدودة.

| ID | الخطورة | الملف:السطر | المشكلة | الأثر تحت الضغط | الحل المقترح |
|---|---|---|---|---|---|
| PERF2-01 | **critical** | `app/Services/Moderation/DuplicateImageDetector.php:62-90` (+ `app/Actions/Ads/ModerateAdAction.php:59`, `app/Services/Ads/AdLifecycleService.php:44`, `app/Actions/Ads/PublishAdAction.php:34-37`) | `findDuplicateAdIds` بيعمل join بين `media` و`ads` وبيمشي على **كل** صور الإعلانات الفعّالة بـ `chunkById(500)`، وبيحسب Hamming distance بـ PHP. هالشي بيصير جوّا `submitForReview`، يعني جوّا transaction قافلة `users` و`ads` بـ `FOR UPDATE`. وكمان بيصير بكل تعديل لإعلان فعّال وبكل رفع صور (`ResubmitActiveAdAction`). وفوق هيك، الـ `phash` بينحسب على طابور `low`، فوقت النشر غالباً بيكون لسا `null` وما بيفيد الفحص | ٥٠ ألف إعلان × ٨ صور = ٤٠٠ ألف صف بكل نشر، يعني ثواني لدقايق. صف المستخدم بيضل مقفول هالوقت كله، فبيعلقوا login وrefresh وأي تحديث عليه، وممكن يطلع `Lock wait timeout`. وworker الـ FPM بيضل محجوز | تنتقل لـ `ModerateAdJob` على الطابور (ShouldBeUnique لكل إعلان، وبعد commit، ومربوطة بعد `ProcessAdImagesJob` بـ `Bus::chain`). منضيف عمود `phash_int BIGINT UNSIGNED` ومنعمل المقارنة بـ SQL: `BIT_COUNT(phash_int ^ ?) <= 8`، ومنحصرها بنفس التصنيف الجذر وبآخر ٩٠ يوم. القرار بيتخزّن على الإعلان. *(بتكبّر BE-13.21 المفتوحة وبتخليها P0)* |
| PERF2-02 | **critical** | `app/Observers/AdListingCacheObserver.php` + `app/Services/Catalog/CatalogCache.php:39-43` + `app/Http/Controllers/Api/V1/Home/HomeController.php:30` + `app/Services/Catalog/CategoryAdCounts.php:47` + `app/Actions/Catalog/GetHomeFeedAction.php:59,73,91` | `listingsChanged()` بيمسح `home.feed` وعدّادات التصنيفات **مع كل** تغيير بـ status أو published_at أو category أو location لأي إعلان. وبعدها `Cache::remember` بدون قفل، فكل الطلبات المتزامنة بتعيد البناء بنفس الوقت. إعادة البناء فيها: `ORDER BY favorites_count` على كل الإعلانات الفعّالة (بدون index)، و`views_count` على آخر ٣٠ يوم، و`withCount` للبائعين، واستعلامين `GROUP BY` كاملين مع `EXISTS users`. و`ExpireOldAdsJob` لما ينهي ألف إعلان بيعمل ألف مسح | الصفحة الرئيسية (أكتر endpoint عليه ضغط) بتصير عملياً **بدون كاش** وقت النشاط. تخيّل ٥٠ طلب بنفس الثانية، كلهم بيعملوا ٦ استعلامات تقيلة، فبيوصل الـ CPU تبع MySQL لـ 100% وبيصير تأثير متسلسل على كل شي | (١) منوقف المسح المرتبط بالأحداث للـ listings، ومنقبل إنو البيانات تكون قديمة شوي (TTL). (٢) منستعمل `Cache::flexible('home.feed', [120, 600], …)` (Laravel 12 فيه SWR مع قفل). (٣) أو أحسن: `WarmCatalogCacheJob` كل دقيقتين من الـ scheduler بيعمل `Cache::put`، والطلب **ما بيبني الكاش أبداً**. التغييرات بتعلّم flag "dirty" بس. (٤) indexes من القسم ٣ |
| PERF2-03 | **high** (بتصير critical مع الحجم) | `app/Services/Auth/RefreshTokenService.php:72, 268` | `token_hash` = `Hash::make` (bcrypt بـ 12 rounds)، والتحقق `Hash::check`. وبما إنو الـ access token مدته ١٥ دقيقة، كل client بيعمل refresh كل ١٥ دقيقة وبيدفع تقريباً ٢×(١٥٠–٣٠٠ms) CPU | ٥ آلاف client نشط = ٥.٥ refresh بالثانية × ٠.٥ ثانية = **تقريباً ٣ cores** بس على تجديد التوكنات. ووقت الذروة (الكل بيفتح التطبيق الصبح) الطابور بيعلق | التوكن أصلاً random وفيه أكتر من 128-bit entropy، فما بيحتاج bcrypt. منستعمل `hash_hmac('sha256', $raw, APP_KEY)` و`hash_equals`. للتوافق: الصفوف القديمة (بتبلّش بـ `$2y$`) منتحقق منها بـ bcrypt مرة وحدة، وبعدها منصدر توكن جديد بـ sha256 |
| PERF2-04 | **high** | `routes/api_v1.php:116-147` (`throttle:auth` على login/refresh/forgot/reset) + `app/Providers/AppServiceProvider.php:60,77,81` + غياب `trustProxies` بـ `bootstrap/app.php` | الحدود بالـ IP بس: `auth` = ٥ بالدقيقة لكل IP، وفيه `/refresh`. شبكات الموبايل بقطر بتستعمل CGNAT، يعني مئات المستخدمين بيشاركوا نفس الـ IP. وبعد OPS-18.9 (Cloudflare) وبدون TrustProxies، كل الطلبات رح تجي من IPs تبع Cloudflare | مستخدمين حقيقيين رح يطلعلهم 429 بـ refresh فبيطلعوا من حسابهم. ولما ننتقل لـ Cloudflare، **الموقع كله** ممكن ينضرب بـ 429 | `/refresh` ياخد limiter خاص فيه، مفتاحه الـ ULID تبع التوكن (`rt_<ULID>`)، ومعه سقف أعلى للـ IP (مثلاً ٦٠ بالدقيقة). منضيف `$middleware->trustProxies(at: [Cloudflare ranges], headers: …)` أو منقرا `CF-Connecting-IP`. وحد الـ guests بالـ `api` بيصير ٣٠٠ بالدقيقة، مع قواعد WAF بـ Cloudflare |
| PERF2-05 | **high** | `app/Models/Ad.php:395-398` (thumbnail `nonQueued`) + `app/Actions/Ads/AttachAdImagesAction.php:29,47` + `routes/api_v1.php:314-315` | الطلب الواحد فيه لحد ١٠ ملفات × ١٠MB. الـ thumbnail بينعمل جوّا الطلب بـ GD، وGD ما فيه shrink-on-load، يعني صورة 12MP بتفك لتقريباً ٤٨MB RAM وبتاخد ٠.٣–١ ثانية للصورة. وفي `Cache::lock->block(15)` بيضل ماسك الـ worker لحد ١٥ ثانية. والـ endpoint عليه `throttle:api` (١٢٠ بالدقيقة). والأصل بينحفظ بحجمه الكامل، يعني لحد ٢٠٠MB للإعلان | رفع ٢٠ صورة = ٢ طلبات × ٣–١٠ ثواني لكل worker. ومع ٥ workers (إعدادات cPanel الافتراضية) بس ٥ مستخدمين عم يرفعوا صور بيوقّفوا الـ API كله. وكمان التخزين والـ egress بيكبروا كتير | (١) **resize بالـ client** (الويب والموبايل) لـ 2048px تقريباً بجودة ٨٠، يعني ٣٠٠–٦٠٠KB للصورة، وهاد أكبر مكسب. (٢) `IMAGE_DRIVER=imagick`، أو الـ thumbnail كمان ينتقل للطابور ومنرجّع blurhash أو placeholder. (٣) limiter اسمه `uploads` (مثلاً ٢٠ بالدقيقة لكل مستخدم). (٤) انتظار القفل ٢–٣ ثواني بدل ١٥. (٥) job بتصغّر الأصل لـ 2560px أو بتنقله لـ bucket بارد. (٦) نخفّض `max_image_size_kb` لـ 5MB بعد ما يصير الـ resize بالـ client |
| PERF2-06 | **high** | `config/horizon.php:203-224` + كل الـ Jobs، والـ Notifications، والـ Events اللي `ShouldBroadcast` | supervisor واحد لـ `default` و`low`، فيه `tries=1` و`timeout=60`، وما في `$tries` ولا `$backoff` ولا `$timeout` على مستوى الـ job. وعلى `default`: الـ broadcasts (MessageSent وOffer* وNotificationCreated)، وpush الشات، والإشعارات (تقريباً ٣ jobs لكل إشعار: database ثم broadcast ثم FCM)، وScout، وتحويلات الصور (`MEDIA_QUEUE` فاضي، فبتروح على `default`) | رفع ١٠ إعلانات × ٢٠ صورة = ٢٠٠ PerformConversionsJob قدام رسائل الشات، يعني **الشات الفوري بيتأخر دقايق**. وانقطاع SMTP أو FCM أو Meili لثواني = jobs **ضايعة** (ما في retry). وjobs الـ sweep الطويلة بتنقتل عند ٦٠ ثانية | الطوابير بتصير `realtime` و`notifications` و`default` و`search` و`media` و`low`، وكل وحدة إلها supervisor (التفاصيل بالقسم ٤). `broadcastQueue()` يرجّع `realtime`، و`MEDIA_QUEUE=media`، و`scout.queue` يصير `['connection'=>'redis','queue'=>'search']`. وكل job بيصير إلها `$tries` و`$backoff` و`$timeout`، و`retry_after` لازم يكون أكبر من أطول timeout (منعمل connection اسمه `redis-long`) |
| PERF2-07 | **high** | `app/Listeners/Search/NotifySavedSearchMatches.php:35-37` | مع كل إعلان بيتنشر أو بيتوافق عليه، الكود بيمرّ على **كل** البحوثات المحفوظة لكل المستخدمين ومعها الـ users (`chunkById(200)`)، وبيعمل matching بـ PHP. و`in_array` على `$notified` كلفته O(n). وما في queue محددة، فبيروح على `default` | ٢٠ ألف مستخدم × ٥ بحوثات = ١٠٠ ألف صف لكل موافقة × ألف موافقة باليوم = **١٠⁸ صف باليوم**. وكل تطابق بيولّد ٣–٤ jobs | منحط حقول الفلتر بأعمدة (`category_id` و`location_id` و`price_min` و`price_max` و`condition` و`q`) عليها indexes. وقبل الـ matching منفلتر المرشحين بـ SQL: `category_id IN (ancestors)`، و`location_id IS NULL OR IN (path)`، ونطاق السعر. أو منعمل digest كل ساعة لكل مستخدم (`saved_search_check_interval_minutes=60` موجود بالـ config). ومنحطها على طابور `notifications` مع `ShouldBeUnique` |
| PERF2-08 | **high** | `deploy/env.production.template:39,51,56` + `config/database.php` (redis) | `REDIS_CLIENT=predis` (PHP صافي، أبطأ وبياكل CPU أكتر). الطوابير وHorizon والـ sessions على db0، والكاش على db1، **بنفس الـ instance**، وما في `maxmemory` ولا `maxmemory-policy` موثّقين | إذا الـ policy `allkeys-lru`: Redis ممكن **يمسح jobs من الطابور**. وإذا `noeviction` والكاش امتلى: كل كتابة كاش بتفشل، يعني 500 بكل مكان. وpredis بيزيد latency على كل طلب | **instance-ين**: (أ) `redis-queue` بـ `noeviction` و`appendonly yes` و`appendfsync everysec`. (ب) `redis-cache` بـ `maxmemory 512mb` و`allkeys-lru`. ومنثبّت `phpredis` (`ea-php84-php-redis` أو pecl)، و`REDIS_CLIENT=phpredis`، و`REDIS_PERSISTENT=true` |
| PERF2-09 | **high** | `deploy/README.md:110-120`، `deploy/scripts/deploy-api.sh:58-71`، وما في أي توثيق لـ FPM أو OPcache أو MySQL | pool الـ FPM الافتراضي بـ cPanel هو `pm.max_children=5` و`pm.max_requests=20`. وOPcache ما إلو إعدادات موثقة. وسكريبت الـ deploy ما بيعمل reload لـ PHP-FPM. وإعدادات MySQL (buffer pool، slow log، max_connections) غايبة | ٥ طلبات بطيئة بس بتسكّر الـ API. و`max_requests=20` بيعني إعادة تشغيل الـ worker كل ٢٠ طلب، وهي كلفة كبيرة. وإذا عملنا `validate_timestamps=0` بدون reload، رح يضل الكود القديم شغّال | الأرقام بالقسم ٥. ومنضيف `sudo /scripts/restartsrv_apache_php_fpm` (أو `systemctl reload ea-php84-php-fpm`) لـ `deploy-api.sh` بعد `config:cache` |
| PERF2-10 | **high** | `deploy/apache/api.qbazaar.fleeteye.de.include.conf:53-57` + `deploy/systemd/qbazaar-reverb.service:17` | الـ WebSocket ماشي عبر `mod_proxy_wstunnel` بـ Apache، يعني thread أو worker لكل اتصال مفتوح. والـ unit تبع Reverb ما فيها `LimitNOFILE`، فالافتراضي ١٠٢٤ fd. وما في `config/reverb.php` (ما في حد للاتصالات ولا للـ message size مضبوط) | كم مية مستخدم فاتح الشات = كم مية worker بـ Apache محجوزين، فبيصير **جوع HTTP** للموقع والـ API. وعند تقريباً ١٠٠٠ اتصال، Reverb بيرفض الجديد (`Too many open files`) | `LimitNOFILE=65535` بالـ unit، وApache لازم يكون على `event` MPM ومعه `MaxRequestWorkers` كافي. والأفضل إنو الـ ws يروح على subdomain (`ws.`) عبر Cloudflare لـ Reverb مباشرة بـ TLS (أو nginx stream) بدل Apache. وكمان `vendor:publish --tag=reverb-config` ومنضبط `max_request_size` و`apps.*.max_message_size` و`activity_timeout` |
| PERF2-11 | **high** | Sanctum `Guard` (vendor) + `qbazaar-web/lib/queries/messaging.ts:141`، `notifications.ts:78` | Sanctum بيعمل `UPDATE personal_access_tokens SET last_used_at` **بكل طلب** فيه مصادقة. والويب بيعمل polling كل ٦٠ ثانية لعدد الرسائل غير المقروءة وعدد الإشعارات، يعني طلبين بالدقيقة لكل تاب، وكل واحد فيه UPDATE واستعلام unread تقيل | ٥ آلاف تاب = تقريباً ١٧٠ طلب بالثانية بس للـ badges، ومعهم ١٧٠ UPDATE بالثانية على نفس الجدول. وهالشي بيعمل ضغط على الـ redo log والـ row locks | (١) نعمل custom `PersonalAccessToken` model عبر `Sanctum::usePersonalAccessTokenModel`، وما بيحفظ `last_used_at` إلا إذا صارله أكتر من ٥ دقايق. (٢) الـ badges تتحدّث عبر Reverb (الأحداث موجودة أصلاً)، ويصير الـ polling كل ٥ دقايق كـ fallback بس. (٣) عدّادات بـ Redis (PERF2-12) |
| PERF2-12 | **medium** (high مع الحجم) | `app/Models/Message.php:106-112`، `app/Models/Conversation.php:163-187`، `app/Http/Controllers/Api/V1/Messaging/ConversationController.php:139`، `app/Events/Messaging/MessageSent.php:81`، `app/Actions/Account/GetAccountSummaryAction.php` | `unreadFor` = `IN (subquery buyer_id=? OR seller_id=?)` على `messages`. والـ inbox بيعمل `forUser` بـ OR مع `ORDER BY last_message_at`، فبيصير index_merge مع filesort، وفوقهم `COUNT` للـ paginate. وكل broadcast رسالة بيعمل `COUNT` إضافي | بيكبر مع عدد المحادثات لكل مستخدم والرسائل. وبيتنفذ بكل poll وبكل رسالة | `conversations` ياخد عمودين `buyer_unread_count` و`seller_unread_count`، بيزيدوا بـ `ConversationMessageWriter` وبيرجعوا صفر بـ `MarkConversationReadAction` (atomic `increment`/`update`). أو جدول `conversation_participants(user_id, conversation_id, last_message_at, unread_count, archived_at)`. والـ inbox بيصير `WHERE user_id=? ORDER BY last_message_at DESC` على index واحد. والعدد الكلي يكون `SUM` على هالجدول أو key بـ Redis |
| PERF2-13 | **medium** | `app/Http/Controllers/Api/V1/Ads/AdController.php:52-85`، `app/Models/Ad.php:221-226` | `/ads` (الـ feed العام): `whereHas('user', status=active)` = `EXISTS` لكل صف، و`paginate()` = `COUNT(*)` على كل الإعلانات الفعّالة بكل طلب. وفرز السعر ما إلو index. والـ OFFSET pagination للصفحات العميقة | مع ١٠٠ ألف إعلان فعّال، الـ COUNT مع EXISTS بياخد مئات الـ ms بكل صفحة، والـ OFFSET العميق بيكبر خطياً | منحط flag مخزّن على الإعلان (`seller_active` أو `is_listed`) بيتحدّث من `SellerListingsVisibilityService` (المكان موجود أصلاً). والاستعلام العام بيصير بدون EXISTS. ومنستعمل `simplePaginate` أو `cursorPaginate` لـ infinite scroll، أو منجيب الـ total من `CategoryAdCounts` المكيّشة. ومنضيف index للسعر |
| PERF2-14 | **medium** | `app/Actions/Catalog/GetCategoryPageAction.php:79-91`، `app/Http/Controllers/Api/V1/Reference/CategoryController.php` (show) | صفحة التصنيف **بدون كاش**، وفيها `ROW_NUMBER() OVER (PARTITION BY category_id …)` على **كل** إعلانات الشجرة الفرعية، بس كرمال ٦ إعلانات لكل فرع | تصنيف "سيارات" فيه ٣٠ ألف إعلان = window function على ٣٠ ألف صف بكل فتحة صفحة | منكيّشها لكل slug (`Cache::flexible`، ١٢٠ ثانية). أو منعمل استعلام صغير لكل فرع `LIMIT 6` على `(category_id,status,published_at)` (١٠ index range scans أسرع بكتير من window على كل الشجرة) |
| PERF2-15 | **medium** | `app/Services/Search/AdSearchService.php:57-82`، `routes/api_v1.php:339-341` | كل بحث = **استدعاءين لـ Meili** (hits وبعدها facets بـ `hitsPerPage=0`)، وبعدهم hydration من DB. وفي limiter اسمه `search` (٦٠ بالدقيقة) معرّف بس **مش مستعمل**، والـ route عليه `api` | ضعف الحمل على Meilisearch (CPU مشترك مع PHP)، وlatency إضافية | `rawSearch` واحد بيرجّع الـ hits والـ facets سوا، وبعده `Ad::whereIn(ids)` مرة وحدة مع الحفاظ على الترتيب (أو مناخد الحقول من `attributesToRetrieve` مباشرة بدون DB). ومنحط `throttle:search`. ومنكيّش الـ facets للبحث الفاضي حسب التصنيف لمدة ٦٠ ثانية |
| PERF2-16 | **medium** | `app/Models/Ad.php:300-314, 327-331` | `toSearchableArray` بيعمل استعلام `media()->exists()` **لكل إعلان**، يعني N+1 بـ `scout:import` وبالـ batches (٥٠٠ استعلام لكل chunk). و`shouldBeSearchable` بيحمّل `user` lazy مع كل save. وأي `save()` للإعلان (ولو لحقل مش مفهرس) بيبعت job لإعادة الفهرسة. و`has_images` ما بيتحدّث لما تنضاف صورة | إعادة الفهرسة الكاملة أبطأ بـ ٥–١٠ مرات من اللازم، وفي jobs Scout زيادة على الطابور | `makeAllSearchableUsing` و`makeSearchableUsing` يعملوا `withExists(['media as has_images_flag' => …])`. ومنضيف `searchIndexShouldBeUpdated()` اللي بيرجّع false إذا ما تغيّر ولا حقل مفهرس. ومنحدّث المستند بعد `AttachAdImagesAction` |
| PERF2-17 | **medium** | `app/Actions/Recents/TrackAdViewAction.php:63-75` | كل مشاهدة = transaction فيها INSERT لـ `recently_viewed` وUPDATE لـ `ads.views_count` (صف ساخن) و`capUserHistory` (SELECT ٥٠ وDELETE NOT IN). وصفوف الضيوف (`session_id`) **ما بتنمسح أبداً** | إعلان منتشر فيه آلاف المشاهدات بالدقيقة بيعمل row lock contention على `ads`. وجدول `recently_viewed` بيكبر بلا حدود ومعه ٣ indexes | `HINCRBY ads:views {id} 1` بـ Redis، وjob كل دقيقة بيعمل flush بـ `UPDATE … CASE`. و`capUserHistory` بشكل احتمالي (١ من ١٠)، أو بالـ prune المجدول. وتاريخ الضيوف بيصير على الـ client أو Redis list مع TTL |
| PERF2-18 | **medium** | `app/Notifications/PasswordResetNotification.php:23`، `app/Notifications/EmailVerificationNotification.php:20`، `routes/api_v1.php:142-143` | إيميل استرجاع كلمة السر وإيميل التحقق **مش ShouldQueue**، يعني SMTP بيصير جوّا الطلب. و`forgot-password` ما عليه Turnstile، وحدّه بالـ IP بس | كل طلب بيمسك worker لـ ١–٥ ثواني. وبوت بيقدر يعمل email bombing ويستنزف الـ workers والـ SMTP quota | `ShouldQueue` على `notifications` مع `tries=3` و`backoff`. ومنحط `turnstile` middleware على `/auth/forgot-password` (قرار صاحب المشروع). ومنضيف limiter لكل إيميل (٣ بالساعة) غير الـ IP. *(بيتداخل مع BE-13.19)* |
| PERF2-19 | **medium** | `config/pulse.php:48, 62, 86, 143-231` + `deploy/env.production.template` (ما فيه `PULSE_*`) | Pulse **شغّال بالـ production** بشكل افتراضي، والـ ingest `storage` (كتابة على MySQL)، و`sample_rate=1` لكل الـ recorders، وفيهم cache interactions وuser requests | INSERTs على MySQL مع كل طلب وكل job، يعني ضغط كتابة إضافي بدون فايدة حقيقية | `PULSE_INGEST_DRIVER=redis` مع `pulse:work` كـ systemd unit، و`sample_rate` بين ٠.١ و٠.٢، والـ cache interactions نطفيها. أو `PULSE_ENABLED=false` لحد ما نحتاجه |
| PERF2-20 | **medium** | `bootstrap/app.php:79-97` (الـ scheduler فيه ٢ jobs بس)، `app/Models/Ad.php:70` + `app/Observers/AdObserver.php:39-62` | ما في prune لـ `activity_log` (وكل تعديل على الإعلان بيكتب **مرتين**: مرة من trait `LogsActivity` ومرة من `AdObserver`)، ولا لـ `notifications`، ولا لـ `personal_access_tokens`، ولا لـ `refresh_tokens`، ولا لـ `otp_codes`، ولا لـ `recently_viewed`، ولا لـ `failed_jobs`، ولا لـ `password_reset_tokens`. وما في `horizon:snapshot` | الجداول بتكبر بلا حدود، والـ buffer pool بيمتلي بداتا ميتة، والـ backups بتكبر | (BE-13.9 مفتوحة) منضيف: `activitylog:clean`، و`sanctum:prune-expired --hours=24`، و`model:prune` (OTP وRefreshToken وRecentView للضيوف أقدم من ٣٠ يوم، والإشعارات المقروءة أقدم من ٩٠ يوم)، و`queue:prune-failed --hours=168`، و`auth:clear-resets`، و`horizon:snapshot` كل ٥ دقايق. وبيبقى سطر واحد بس لكل تغيير بالـ activity log |
| PERF2-21 | **medium** | `app/Jobs/Ads/ExpireOldAdsJob.php:52-82`، `app/Jobs/Offers/ExpireOldOffersJob.php`، `bootstrap/app.php:83-96` | Job وحدة بتنهي كل الإعلانات اللي خلصت مدتها، وكل إعلان بـ transaction لحاله مع events وإشعارات وScout وسمح كاش (PERF2-02)، وكل هاد تحت `timeout=60` و`tries=1`. و`withoutOverlapping()` على `$schedule->job` ما بيمنع تداخل الـ job نفسها (الـ mutex بينفك أول ما تنبعت) | أول تشغيل بعد توقف، أو يوم فيه كتير إعلانات منتهية: الـ job **بتنقتل بالنص** وما بترجع غير بكرة. والانتهاء بيتأخر لحد ٢٤ ساعة | `Bus::batch` لـ chunks من ٢٠٠ إعلان على `low`، أو `$timeout=900` على `redis-long`. ومنشغّلها **كل ساعة** بدل يومياً، مع `ShouldBeUnique` |
| PERF2-22 | **low/medium** | `app/Http/Resources/Api/V1/Users/PublicUserResource.php:45`، `app/Http/Controllers/Api/V1/Ads/AdController.php:100-118` | `ads()->active()->count()` بكل عرض لإعلان وبكل محادثة. وتفاصيل الإعلان بدون كاش، وفيها ٢٠ media × ٥ روابط موقعة (HMAC) | استعلام إضافي على أكتر صفحة بتنفتح | `users.active_ads_count` denormalized (بيتحدّث من `AdLifecycleService`)، أو كاش لكل بائع ٥ دقايق. وكاش لتفاصيل الإعلان (الجزء العام) ٦٠ ثانية بمفتاح `id+updated_at` |
| PERF2-23 | **low/medium** | `app/Http/Controllers/Api/V1/Account/NotificationsController.php:53` + migration `2026_06_10_000000:40` | الإشعارات بتنعرض `ORDER BY created_at DESC`، والـ index الموجود `(notifiable_type, notifiable_id, read_at)` بس، فبيصير filesort لكل مستخدم مع COUNT | بتكبر مع عدد الإشعارات لكل مستخدم | index `(notifiable_type, notifiable_id, created_at)`. *(BE-13.22)* |
| PERF2-24 | **medium** | `app/Services/Media/MediaStorage.php:26-51`، `app/Http/Controllers/Api/V1/Media/MediaOriginalController.php:45`، `deploy/apache/…include.conf:40-46` | كل `MediaResource` فيه رابط أصلي موقّع جديد (expiry بيتغيّر مع كل طلب)، والمقاسات اللي لسا ما انعملت بترجع لنفس الرابط. والأصل على القرص المحلي بينخدم **عبر PHP**. و`/storage` ما عليه `Cache-Control` ولا `Expires` | الرد ما بينكيّش بالـ CDN أو المتصفح (الـ URL كل مرة غير). وكل صورة أصلية = طلب PHP بينحسب من `throttle:api`. والصور بتنجاب من السيرفر بدل الـ edge | (مرتبطة بقرار `cdn.`) المقاسات العامة على `cdn.` (R2 custom domain) مع `Cache-Control: public, max-age=31536000, immutable` (اسم الملف فيه UUID). وبالقوائم ما منرجع غير `thumbnail` و`medium`. والرابط الأصلي الموقّع بالتفاصيل بس، والـ expiry بيتقرّب لأول الساعة (bucket) فبيضل الـ URL نفسه ساعة كاملة. ومنضيف `Header set Cache-Control` على `/storage` |
| PERF2-25 | **low** | `app/Jobs/DeleteAccountJob.php:69` | `Storage::files('exports')` بيعرض **كل** الملفات بكل حذف حساب. و`forceDelete` بيحذف الإعلانات بـ cascade على مستوى الـ DB، فبيضلوا ملفات الصور ومستندات Meili | O(عدد الـ exports) لكل حذف، وملفات يتيمة بـ R2 بتكلف مصاري | `exports/{userId}/…` و`deleteDirectory`. والإعلانات بتنحذف عبر Eloquent بـ chunks (`clearMediaCollection` و`unsearchable`). *(BE-13.5)* |
| PERF2-26 | **low** | `app/Providers/AppServiceProvider.php:33` | `ModerationRulesService` معرّف `singleton`، فبيحفظ الكلمات الممنوعة **طول عمر الـ worker** بـ Horizon | الكلمات الممنوعة الجديدة ما بتنطبق على الشات المفحوص بالطابور لحد ما Horizon يرجع يشتغل | `scoped()` بدل `singleton()` (بيرجع يتصفّر بين الـ jobs)، والاعتماد على الـ Cache بس |
| PERF2-27 | **low** | `app/Http/Middleware/ApiResponseWrapper.php` | `getData(true)` بعده `setData()`، يعني decode وencode للـ JSON مرة تانية لكل رد | الـ CPU تبع JSON بيتضاعف على الردود الكبيرة (home وsearch) | إذا الرد فيه `success` أصلاً، ما بنعمل decode (منفحص header أو attribute على الـ response)، أو macro `response()->api()` |
| PERF2-28 | **low** | `app/Http/Controllers/Manage/AdController.php:46`، `UserController.php:51`، `NotificationController.php:24`، `DashboardController.php:38-87` | لوحة الأدمن: `latest()` على `created_at` بدون index، و`LIKE '%q%'`، و`data->title` على عمود TEXT، و١١ COUNT بدون كاش بالـ dashboard | full scans، بس عدد مستخدمي الأدمن قليل | indexes على `created_at` و`(status, created_at)`، وكاش للـ dashboard ٦٠ ثانية، والبحث بالـ ID المطابق أو عبر Meili |
| PERF2-29 | **low** | `app/Actions/Messaging/StartConversationAction.php` | race: طلبين متزامنين لفتح محادثة، فبيصير UniqueConstraintViolation على `(ad_id,buyer_id)` ومنه 500 | أخطاء متفرقة تحت الضغط | `catch (UniqueConstraintViolationException)`، وبعدها منقرأ المحادثة الموجودة ومنرجّعها |
| PERF2-30 | **low** | `config/broadcasting.php:46` | `client_options` فاضي، يعني ما في timeout للاتصال بـ Reverb | إذا Reverb علق، الـ broadcast jobs بتضل معلّقة لحد ٦٠ ثانية وبتسدّ طابور الـ realtime | `['timeout' => 3, 'connect_timeout' => 1]` |
| PERF2-31 | **low** | `app/Http/Controllers/Api/V1/Reference/CategoryController.php` (filters/fields) + `CatalogCache::taxonomyChanged` | `categories.filters.{slug}` و`fields.{slug}` ما بينمسحوا لما يتغيّر التصنيف | بيانات قديمة لساعة | *(BE-13.12 مفتوحة)* |

---

## 3. الفهارس (indexes) المقترحة

> التفاصيل قبل ما نضيف أي شي: جدول `ads` القراءة عليه أكتر بكتير من الكتابة، فمنقبل ٣–٤ indexes زيادة. بس **ما منضيف** indexes للعدّادات إذا انتقلت للـ warmer المجدول (PERF2-02).

| # | الجدول | الأعمدة | السبب | ملاحظة |
|---|---|---|---|---|
| I-1 | `ads` | `(status, favorites_count, published_at)` | `bestSelling` بالرئيسية: `ORDER BY favorites_count DESC, published_at DESC` | بيلغي full scan مع filesort |
| I-2 | `ads` | `(status, price)` | `/ads?sort=price_asc|desc` | موجودة بـ BE-13.22 |
| I-3 | `ads` | `(user_id, status, published_at)` | `UserAdsController` (بيفرز حسب `published_at`)، و`ads_count` للبائع، وصفحات الشركات (BE-14.29/30) | الموجود `(user_id,status,created_at)` ما بيخدم الفرز |
| I-4 | `ads` | `(featured, status, published_at)` بدل `ads_featured_published_idx` | `FeaturedAdsController` بيفلتر الحالة | منحذف القديم |
| I-5 | `ads` | `(status, seller_active, published_at)` | إذا اعتمدنا PERF2-13 (flag مخزّن بدل `EXISTS users`) | مع عمود جديد |
| I-6 | `ads` | `(status, created_at)` | قوائم الأدمن (`latest()` مع فلتر الحالة) | |
| I-7 | `media` | `(model_type, model_id, collection_name, order_column)` | `primaryImage` (`ofMany` min order)، و`count`، و`exists` بالـ images | بيغطي الـ subquery تبع ofMany |
| I-8 | `media` | عمود `phash_int BIGINT UNSIGNED` + index `(model_type, phash_int)` | `BIT_COUNT(phash_int ^ ?)` بـ SQL بدل PHP (PERF2-01) | الـ index covering بيسرّع الـ scan |
| I-9 | `notifications` | `(notifiable_type, notifiable_id, created_at)` | قائمة الإشعارات مفروزة | BE-13.22 |
| I-10 | `messages` | `(conversation_id, read_at, sender_id)` بدل `(conversation_id, sender_id, read_at)` | `read_at IS NULL` (equality) قبل `sender_id != ?` (range) | بيقلّ الـ scan بـ unread counts |
| I-11 | `conversation_participants` (جدول جديد) أو أعمدة unread | `(user_id, last_message_at)` + unique `(conversation_id, user_id)` | inbox بدون OR، وعدّاد unread | PERF2-12 |
| I-12 | `offers` | `(ad_id, status)` | `assertAdIsOpenForOffers` و`expireOpenOffersOnAd` | الـ FK index على `ad_id` بس |
| I-13 | `saved_searches` | `(category_id)` و`(location_id)` (بعد الـ denormalize) | فلترة مسبقة للـ fan-out | PERF2-07 |
| I-14 | `users` | `(account_type, status)` | `featuredSellers`، ودليل الشركات BE-14.29 | |
| I-15 | `users` | `(created_at)` و`(last_login_at)` | dashboard وقوائم الأدمن | BE-13.22 |
| I-16 | `refresh_tokens` | `(user_id, device_fingerprint)` + `(expires_at)` | `isKnownForUser` + prune | |
| I-17 | `recently_viewed` | `(viewed_at)` | prune صفوف الضيوف القديمة | |
| I-18 | `activity_log` | `(created_at)` + `(causer_type, causer_id, created_at)` | `activitylog:clean` وقائمة الأدمن والـ export | |
| I-19 | `password_reset_tokens` | `(created_at)` | `auth:clear-resets` | |

**بعد ما نضيف الـ indexes:** منشغّل `ANALYZE TABLE ads, media, messages, notifications`، ومنتحقق من كل استعلام ساخن بـ `EXPLAIN ANALYZE`. والأفضل نعمل seed لـ ١٠٠ ألف إعلان ومليون media بـ staging.

---

## 4. خطة الطوابير والكاش

### 4.1 الطوابير (Horizon)

| الطابور | شو عليه | supervisor | الإعدادات |
|---|---|---|---|
| `realtime` | كل `ShouldBroadcast` (`broadcastQueue(): 'realtime'`)، و`SendChatPushNotifications`، و`BroadcastDatabaseNotificationCreated` | `sv-realtime` | `minProcesses=2`، `maxProcesses=4`، `timeout=15`، `tries=3`، `backoff=[1,5,15]`، `nice=0` |
| `notifications` | كل الـ Notifications اللي `ShouldQueue` (mail وdatabase وFCM وSMS)، وfan-out البحوثات المحفوظة، والأدمن | `sv-default` | `timeout=60`، `tries=5`، `backoff=[10,60,300,900]` |
| `default` | باقي الـ listeners، و`ScreenChatMessage` | `sv-default` | `maxProcesses=4`، `balance=auto` |
| `search` | Scout (`config('scout.queue')` = `['connection'=>'redis','queue'=>'search']`) | `sv-default` | `tries=5`، `backoff=[5,30,120]` (Meili ممكن يكون عم يعيد التشغيل) |
| `media` | `MEDIA_QUEUE=media` (PerformConversionsJob)، و`ProcessAdImagesJob`، و`ModerateAdJob` | `sv-media` | `maxProcesses=2` (CPU-bound)، `timeout=300`، `memory=512`، `nice=10`، `tries=3` |
| `low` | expiry sweeps، وexports، وحذف الحسابات، وwarm الكاش، وflush المشاهدات | `sv-low` على connection `redis-long` (`retry_after=1900`) | `maxProcesses=1`، `timeout=1800`، `tries=3`، `ShouldBeUnique` |

قواعد عامة:
- كل Job أو Listener لازم يكون فيه **`$tries` و`$backoff` و`$timeout` صريحين**، وما منعتمد على Horizon defaults (`tries=1`).
- **Idempotency:** الـ jobs اللي بتنعاد لازم تكون آمنة. `ExpireOldAdsJob` آمنة لأنها بتعيد الفحص جوّا القفل. لازم نضيف `ShouldBeUnique` لـ `ModerateAdJob($adId)` و`WarmCatalogCacheJob` و`NotifySavedSearchMatches` (`uniqueId = ad id`).
- **`after_commit`:** منفعّلها على connection تبع `redis` (`'after_commit' => true`) كرمال نتأكد إنو أي job بتنبعت من جوّا transaction ما بتشتغل قبل الـ commit. Scout والـ media library عاملين هيك أصلاً.
- **`retry_after` لازم يكون أكبر من أطول `timeout`** على نفس الـ connection، وإلا الـ job بتنعاد وهي لسا شغّالة (تكرار).
- **الذاكرة:** `memory` لكل supervisor، و`maxJobs=500` لإعادة تدوير الـ workers (بيحمي من memory leaks بـ GD أو Imagick).
- **مراقبة:** `horizon:snapshot` كل ٥ دقايق، و`waits` لكل طابور (`redis:realtime => 10`، `redis:media => 600`)، وتنبيه Sentry على `LongWaitDetected`.
- **حجم الـ fan-out:** إشعار واحد = job لكل channel، وبعدها job للـ broadcast. لما نبعت لكتير مستخدمين منستعمل `Notification::send($users, …)` بـ chunks جوّا jobs، ومنبعد عن الحلقات اللي فيها `notify()` جوّا listener واحد.

### 4.2 الكاش

| المفتاح | الآن | المقترح |
|---|---|---|
| `home.feed` | `remember` ٥ دقايق + مسح مع كل تغيير بإعلان | `WarmCatalogCacheJob` كل دقيقتين (`put` بدون TTL قصير) + `Cache::flexible([120, 900])` كـ fallback. **الطلب ما بيبني الكاش أبداً** |
| `categories.ad_counts.{date}` | `remember` ١٠ دقايق + مسح مع كل تغيير | نفس الـ warmer، والمسح بس عند تغيير التصنيفات (taxonomy) |
| `ads.featured.v1`، `ads.similar.v1.{id}` | ٥ دقايق | منخليهم، ومنحوّل لـ `flexible` |
| صفحة التصنيف | ما في كاش | `category.page.{slug}` بـ `flexible([120, 600])` |
| تفاصيل الإعلان | ما في كاش | `ad.public.{id}.{updated_at_ts}` ٦٠ ثانية للجزء العام (بدون أي حقل خاص بالمشاهد، مثل `is_favorited`) |
| `search.suggestions:{sha1}` | ٥ دقايق | منخليه، وبيحتاج maxmemory LRU على redis-cache (المفاتيح كتيرة) |
| facets البحث الفاضي حسب التصنيف | ما في | ٦٠ ثانية |
| unread counts | استعلام كل مرة | عدّادات DB denormalized، أو Redis `HINCRBY unread:{userId} {convId}` |
| `views_count` | UPDATE بكل مشاهدة | Redis `HINCRBY ads:views`، وflush كل دقيقة |
| `platform_settings`، الكلمات الممنوعة، الشجرات | ممتاز | منخليهم، و`ModerationRulesService` بيصير scoped |

قواعد:
- **ممنوع أي بيانات خاصة بمستخدم بمفتاح مشترك.** راجعت الموجود هلأ: `home.feed` والـ featured والـ similar وصفحات التصنيفات كلها عامة، وما لقيت تسريب. بس انتبهوا: لما ينضاف `is_favorited` أو `is_following` (شغل الـ agents التانيين)، **ما بينحط بالـ payload المكيّش**. منجيبه بطلب منفصل أو بـ `whereIn` واحد بعد الكاش وبنضيفه للرد.
- **التواقيع والروابط الموقّعة جوّا الكاش:** `home.feed` بيكيّش روابط موقعة مدتها ٢٤ ساعة لـ ٥ دقايق. هاد مقبول، بس بعد PERF2-24 الروابط بالقوائم بتصير عامة (`cdn.`) وما بتحتاج توقيع.
- **ضد الـ stampede:** كل `remember` على حساب تقيل بيتحوّل لـ `flexible` أو لـ warmer، أو منلفّه بـ `Cache::lock(...)->block(5)`.

---

## 5. ضبط السيرفر

> الأرقام بتفترض VPS فيه **8 vCPU و16GB RAM** (السيرفر الجديد `srv1977263`). وإذا كان 8GB، منقسم الذاكرة على ٢ تقريباً.

### 5.1 توزيع الذاكرة التقريبي (16GB)
| المكوّن | الحصة |
|---|---|
| MySQL (buffer pool + overhead) | 5–6 GB |
| PHP-FPM (API) | 3–4 GB |
| Horizon workers | 1.5 GB |
| Meilisearch | 1.5–2 GB |
| Redis (queue + cache) | 0.75 GB |
| Next.js | 1 GB |
| Reverb + Apache + نظام + cPanel | 1.5 GB |

### 5.2 PHP-FPM (MultiPHP Manager، pool الـ API)
```
pm = dynamic
pm.max_children = 40            ; ≈ 3.2GB / 80MB متوسط RSS (منقيسه بـ ps بعد الإطلاق)
pm.start_servers = 10
pm.min_spare_servers = 8
pm.max_spare_servers = 16
pm.max_requests = 1000          ; بدل 20 الافتراضي بـ cPanel
request_terminate_timeout = 60s ; والرفع بس ممكن يحتاج 120s، وهون الـ resize بالـ client بيساعد
request_slowlog_timeout = 3s
slowlog = /home/fleeteye/logs/php-fpm-slow.log
```
`memory_limit=256M` بيضل للرفع. **وبعد PERF2-05 منرجّعه لـ 128M.**

### 5.3 OPcache وrealpath (`ea-php84`، INI)
```
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=30000
opcache.validate_timestamps=0     ; ← ولازم reload للـ FPM بـ deploy-api.sh
opcache.save_comments=1           ; ضروري لـ attributes و#[Scoped]
opcache.jit=tracing
opcache.jit_buffer_size=64M
realpath_cache_size=4096K
realpath_cache_ttl=600
```
وبـ `deploy-api.sh`، بعد `event:cache`، منضيف: `sudo /scripts/restartsrv_apache_php_fpm --graceful`. وهالأمر لازم ينضاف لـ sudoers.

### 5.4 MySQL 8 (`/etc/my.cnf`)
```
innodb_buffer_pool_size = 5G
innodb_buffer_pool_instances = 4
innodb_redo_log_capacity = 1G
innodb_flush_log_at_trx_commit = 1     ; أو 2 إذا منقبل خسارة ثانية وحدة وقت انهيار
innodb_flush_method = O_DIRECT
innodb_io_capacity = 1000              ; SSD/NVMe
max_connections = 200                  ; FPM 40 + Horizon 12 + scheduler + web + هامش
thread_cache_size = 64
table_open_cache = 4000
tmp_table_size = 64M
max_heap_table_size = 64M
slow_query_log = 1
long_query_time = 0.5
log_queries_not_using_indexes = 0      ; بيعمل ضجة كتير. pt-query-digest أسبوعياً بيكفي
performance_schema = ON
```

### 5.5 Redis (instance-ين)
- `redis-queue` (port 6379): `maxmemory 256mb`، `maxmemory-policy noeviction`، `appendonly yes`، `appendfsync everysec`، `requirepass`.
- `redis-cache` (port 6380): `maxmemory 512mb`، `maxmemory-policy allkeys-lru`، `save ""` (بدون persistence)، `requirepass`.
- بالـ `.env`: `REDIS_CLIENT=phpredis`، و`REDIS_CACHE_HOST`/`PORT` للـ cache connection، و`SESSION_CONNECTION=cache`، و`REDIS_PERSISTENT=true`.

### 5.6 Meilisearch (`/etc/meilisearch.toml`)
```
max_indexing_memory = "1 GiB"
max_indexing_threads = 2          ; ما بيسرق كل الـ CPU من PHP وقت reindex
schedule_snapshot = 86400
```
وبـ `scout.php` → `ads_index`: منضيف `displayedAttributes` (الحقول اللازمة بس)، و`proximityPrecision: 'byAttribute'` (أسرع بالفهرسة)، و`pagination: {maxTotalHits: 1000}`، و`faceting: {maxValuesPerFacet: 50}`، ومنحصر `custom_fields` القابلة للفلترة بالمفاتيح المعرّفة بس (`custom_fields.make`، `custom_fields.year`…) بدل كل الـ object.

### 5.7 Reverb وApache
- `qbazaar-reverb.service`: `LimitNOFILE=65535`، و`Environment=REVERB_...`، و`Nice=-2` (أولوية للشات).
- Apache: `event` MPM مع `ServerLimit 16` و`ThreadsPerChild 64` و`MaxRequestWorkers 1024`. **أو أحسن:** الـ ws يمرق عبر Cloudflare لـ `ws.` → Reverb مباشرة (TLS أو nginx stream)، وهيك Apache ما بيحمل الـ WebSockets أبداً.
- `config/reverb.php` (publish): `max_request_size=10_000`، `max_message_size=10_000`، `activity_timeout=30`، `ping_interval=60`.

### 5.8 Cloudflare (لما يصير OPS-18.9)
- `TrustProxies` بـ Laravel + `CF-Connecting-IP`، **وبعدها** نراجع كل الـ limiters (PERF2-04).
- Cache Rule لـ `cdn.*`: `Edge TTL 1 month`، و`Browser TTL 1 year`. والـ API ما بينكيّش (Bypass)، إلا `/api/v1/categories/tree` و`/locations/qatar` و`/home` (ممكن `s-maxage=60` إذا ضفنا الـ header).
- قواعد Rate Limiting على `/api/v1/auth/*` و`/search` و`/ads/*/images` كخط دفاع أول.
- حد الرفع بخطة Free/Pro هو 100MB. هالشي بيأكّد ضرورة الـ resize بالـ client (PERF2-05).

---

## 6. المهام المقترحة

> الـ IDs بتكمّل من `BE-13.23` و`OPS-18.10` بـ `MILESTONES-V2.md`. وين في تداخل مع مهمة مفتوحة، منوسّعها بدل ما نكررها.

| ID | العنوان | الأولوية | المرحلة | الملاحظات (PERF2) |
|---|---|---|---|---|
| BE-13.24 | فحص الصور المكررة والمراجعة الآلية بالطابور بعد commit (`ModerateAdJob` مربوطة بعد `ProcessAdImagesJob`)، ومقارنة `BIT_COUNT` بـ SQL على `phash_int`، والقرار بيتخزّن على الإعلان | [P0] | M1 | 01. بتحل محل BE-13.21 أو بتغلقها |
| BE-13.25 | Warmer للصفحة الرئيسية وعدّادات التصنيفات والأماكن (`WarmCatalogCacheJob` كل دقيقتين + `Cache::flexible`)، ومنلغي المسح مع كل تغيير بإعلان | [P0] | M1 | 02، 14 |
| BE-13.26 | Refresh tokens بـ HMAC-SHA256 بدل bcrypt مع انتقال تدريجي، وlimiter خاص لـ `/refresh` مفتاحه التوكن، وlimiters ما بتعتمد على الـ IP بس | [P0] | M1 | 03، 04 |
| BE-13.27 | هيكلة الطوابير: `realtime` و`notifications` و`search` و`media` و`low`، و`$tries`/`$backoff`/`$timeout` لكل job، و`broadcastQueue`، و`MEDIA_QUEUE`، وScout queue، وconnection `redis-long`، و`after_commit`، وHorizon supervisors | [P0] | M1 | 06، 21، 30 |
| BE-13.28 | خط الصور: Imagick أو thumbnail بالطابور، وlimiter `uploads`، وانتظار القفل ٢–٣ ثواني، وتصغير الأصل بالطابور، وتخفيض `max_image_size_kb` بعد ما يخلص FE وMB | [P1] | M1 | 05 |
| FE-16.x / MB-15.x | resize وضغط الصور بالـ client قبل الرفع (2048px، جودة 0.8، WebP أو JPEG) | [P1] | M2/M3 | 05 (أكبر مكسب بأقل كلفة) |
| BE-13.29 | البحوثات المحفوظة: الفلاتر بتصير أعمدة عليها indexes + matching بـ SQL + digest كل ساعة + `ShouldBeUnique` | [P1] | M1 | 07 |
| BE-13.30 | الشات: عدّادات unread denormalized أو جدول `conversation_participants`، وinbox بدون OR، والـ badge عبر Reverb والـ polling كل ٥ دقايق | [P1] | M1 | 11، 12. **لازم ينسّق مع agent الشات** |
| BE-13.31 | الـ feed العام: flag `seller_active` مخزّن + `cursorPaginate` أو `simplePaginate` + كل الـ indexes من القسم ٣ (migration وحدة) | [P1] | M1 | 13، 23، 28. بتكمّل BE-13.22 |
| BE-13.32 | تخفيف كتابة `last_used_at` تبع Sanctum (مرة كل ٥ دقايق) | [P1] | M1 | 11 |
| BE-13.33 | البحث: `rawSearch` واحد للـ hits والـ facets، و`throttle:search`، وإصلاح N+1 بـ `toSearchableArray`، و`searchIndexShouldBeUpdated`، وإعدادات index أخف | [P1] | M1 | 15، 16 |
| BE-13.34 | روابط الصور جاهزة للـ CDN: المقاسات العامة على `cdn.` مع `immutable`، والرابط الأصلي بالتفاصيل بس مع expiry مقرّب لأول الساعة، وheaders الكاش على `/storage` | [P1] | M1 | 24. مرتبطة بقرار `cdn.` |
| BE-13.35 | نسيت كلمة السر: Turnstile (قرار صاحب المشروع)، وإيميل الاسترجاع والتحقق بالطابور، وlimiter لكل إيميل | [P0] | M1 | 18. بتغطي جزء الإيميلات من BE-13.19 |
| BE-13.36 | المشاهدات بـ Redis وflush كل دقيقة، و`capUserHistory` احتمالي، وتاريخ الضيوف على الـ client | [P2] | M1 | 17 |
| BE-13.37 | جدولة الانتهاء: batches كل ساعة + `ShouldBeUnique` | [P2] | M1 | 21 |
| BE-13.38 | تنظيف صغير: `ads_count` مخزّن للبائع، وكاش لتفاصيل الإعلان العامة، و`ModerationRulesService` بيصير scoped، والـ wrapper بدون decode مكرر، وrace بـ StartConversation، وسطر activity log واحد للإعلان | [P2] | M1 | 20، 22، 26، 27، 29 |
| BE-13.9 *(موجودة)* | **منوسّعها:** `model:prune` للإشعارات المقروءة القديمة، و`queue:prune-failed`، و`auth:clear-resets`، و`horizon:snapshot` | [P1] | M1 | 20 |
| OPS-18.11 | PHP-FPM pool (`max_children` و`max_requests`) + OPcache + realpath + reload للـ FPM بـ `deploy-api.sh` + slowlog | [P0] | M5 (والسيرفر الحالي هلأ) | 09 |
| OPS-18.12 | Redis على instance-ين (queue `noeviction` مع AOF، وcache LRU) + phpredis | [P0] | M5 | 08 |
| OPS-18.13 | ضبط MySQL + slow query log + `pt-query-digest` أسبوعياً | [P1] | M5 | 09 |
| OPS-18.14 | سعة Reverb: `LimitNOFILE`، وApache event MPM أو `ws.` عبر Cloudflare، و`config/reverb.php` | [P0] | M5 | 10 |
| OPS-18.15 | حدود موارد Meilisearch (`max_indexing_memory` و`threads`) + snapshots يومية | [P1] | M5 | 16 |
| OPS-18.16 | Pulse: ingest على Redis + `pulse:work` + sampling (أو نطفيه) | [P1] | M5 | 19 |
| OPS-18.17 | اختبار ضغط بـ k6 (home، feed، search، تفاصيل إعلان، شات ١٠٠ رسالة بالثانية، رفع ٢٠ صورة، refresh) على staging فيها ١٠٠ ألف إعلان ومليون media + خط أساس للسعة قبل الإطلاق | [P0] | M5 | الكل |
| OPS-18.18 | Cloudflare cache rules لـ `cdn.` وrate-limit rules للمسارات الغالية | [P1] | M5 | 04، 24. بتكمّل OPS-18.9 |

**ترتيب التنفيذ اللي بنصح فيه:** BE-13.27 (الطوابير) ← BE-13.24 ← BE-13.25 ← BE-13.26 ← OPS-18.11/12 ← BE-13.35 ← الباقي. الطوابير أول شي لأنو BE-13.24 وBE-13.29 وBE-13.36 كلهم بيعتمدوا عليها.

---

## 7. مخاطر تصميمية للـ agents اللي شغالين بالتوازي

| الـ agent | الخطر | القاعدة |
|---|---|---|
| **Auth** (الدخول بدون كلمة سر، OTP، magic links) | bcrypt لكل token أو رابط، وlimiters بالـ IP بس | الـ tokens العشوائية بتنحفظ HMAC-SHA256، وbcrypt لكلمات السر والـ OTP بس (قصيرة ومحدودة). والـ limiters مفتاحها identifier مع IP. والإيميل والـ SMS **دايماً** بالطابور. وTurnstile على كل endpoint بيبعت رسالة |
| **Search / Geo** | Haversine بـ MySQL على كل الإعلانات، أو استدعاء Meili تاني | منستعمل `_geo` بـ Meilisearch (`_geoRadius` و`_geoPoint` بالفرز)، ومنضيف `_geo` لـ `toSearchableArray` و`filterableAttributes` و`sortableAttributes`. وإذا لازم نستعمل MySQL: bounding box على index `(status, latitude, longitude)` قبل الحساب. ومنحافظ على استدعاء Meili **واحد** لكل بحث |
| **Chat + Favorites** | `COUNT` على `messages` لكل محادثة أو لكل broadcast، و`is_favorited` بحلقة، وصور الشات بالطلب | عدّادات unread مخزّنة (BE-13.30)، وbroadcasts على `realtime`، وما في `OR` بالـ inbox. و`is_favorited` بيجي من `whereIn` واحد لكل صفحة، و**ممنوع** ينحط بأي payload مكيّش مشترك. وصور الشات بتمشي على خط الصور نفسه (resize بالـ client، وطابور `media`) |
| **Account** | exports أو حذف بيحمّل كل شي بالذاكرة، و`Storage::files` | streaming أو chunked للـ exports (`lazyById`)، ومجلد لكل مستخدم، والحذف عبر Eloquent بـ chunks. وقائمة الجلسات مبنية على `personal_access_tokens` بس (مع الانتباه لـ PERF2-11 بخصوص `last_used_at` التقريبي) |
| **Social + Selling** (متابعات، شركات، بيع) | fan-out on write لما الشركة تنشر إعلان (إشعار لكل المتابعين بحلقة)، وعدّادات المتابعين بـ COUNT | fan-out **on read** للـ feed (`follows(follower_id, followee_id)` unique + index عكسي)، و`followers_count` مخزّن. وإشعارات المتابعين بـ jobs مقسّمة chunks (١٠٠٠) على `notifications` مع `ShouldBeUnique` لكل إعلان، أو digest. ودليل الشركات بيعتمد على `users(account_type, status)` + I-3 |
| **الكل** | `Cache::remember` لحسابات تقيلة، و`notify()` بحلقات، وjobs بدون tries | `flexible` أو warmer، و`Notification::send` بـ chunks، و`$tries`/`$backoff`/`$timeout` لكل job، و`ShouldBeUnique` للـ jobs اللي ممكن تتكرر، و`afterCommit` لكل حدث بيطلع من transaction |

---

## 8. ملاحظة على طلبات صاحب المشروع بهالجولة

- **رابط `cdn.` للصور:** هي PERF2-24 / BE-13.34 / OPS-18.18. الربح بالأداء كبير، لأنو كل الصور العامة بتنخدم من edge تبع Cloudflare وما بتلمس PHP ولا Apache.
- **رسائل النظام بالعربي:** موجودة كمهمة BE-14.44. من ناحية الأداء: لازم نخزّن `message_key` و`params` ونترجم وقت الـ render (بالـ Resource)، وما نعمل join لجدول ترجمات لكل رسالة.
- **Turnstile على "نسيت كلمة السر":** هي BE-13.35 مع الإيميل بالطابور. هاد "الصح"، لأنو الحماية لحالها ما بتكفي إذا الإرسال لسا sync.
