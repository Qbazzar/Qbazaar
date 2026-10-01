<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\AdStatus;
use App\Enums\Condition;
use App\Enums\Language;
use App\Enums\OfferStatus;
use App\Enums\PriceType;
use App\Enums\ReportCategory;
use App\Enums\ReportStatus;
use App\Enums\ReportTarget;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\UserStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Location;
use App\Models\Message;
use App\Models\Offer;
use App\Models\RecentView;
use App\Models\Report;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Populate the database with realistic demo data so the running site doesn't
 * look empty during development + UAT walkthroughs. Idempotent because we
 * always run inside `migrate:fresh --seed` — every row is a fresh insert.
 *
 * Volumes are tuned for a useful tour without overwhelming MySQL:
 *   • 18 users (1 dev login + 17 personas)
 *   • 60 ads (35 active inc. 6 featured + 6 sold + 5 pending + 4 expired + 5 drafts + 5 rejected)
 *   • 12 conversations × ~8 messages
 *   • 7 offers across statuses
 *   • 40 favorites + 80 recent-view rows
 *   • 8 reports (5 pending + 2 reviewed + 1 actioned)
 *   • 5 support tickets across statuses
 *
 * The dev login `demo@qbazaar.qa / password` exists so QA + designers can
 * sign in without provisioning a fresh account every time.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::query()->whereNotNull('parent_id')->get();
        $locations = Location::query()->where('type', 'district')->get();

        if ($categories->isEmpty() || $locations->isEmpty()) {
            $this->command->warn('DemoDataSeeder skipped — categories/locations not seeded yet.');

            return;
        }

        $users = $this->seedUsers();
        $ads = $this->seedAds($users, $categories, $locations);
        $this->seedFavoritesAndViews($users, $ads);
        $this->seedConversationsAndOffers($users, $ads);
        $this->seedReports($users, $ads);
        $this->seedSupportTickets($users);
    }

    /**
     * @return list<User>
     */
    private function seedUsers(): array
    {
        $names = [
            ['Ahmed Al Mansoori', '+97455100001'],
            ['Fatima Al Sulaiti', '+97455100002'],
            ['Khalid Al Kuwari', '+97455100003'],
            ['Mariam Al Thani', '+97455100004'],
            ['Yousef Al Attiyah', '+97455100005'],
            ['Noora Al Hammadi', '+97455100006'],
            ['Saad Al Marri', '+97455100007'],
            ['Aisha Al Jaber', '+97455100008'],
            ['Hamad Al Saidi', '+97455100009'],
            ['Latifa Al Nuaimi', '+97455100010'],
            ['Tareq Al Buainain', '+97455100011'],
            ['Reem Al Mohannadi', '+97455100012'],
            ['Mansour Al Ansari', '+97455100013'],
            ['Hessa Al Khalifa', '+97455100014'],
            ['Faisal Al Mahmoud', '+97455100015'],
            ['Maitha Al Sayegh', '+97455100016'],
            ['Jassim Al Naimi', '+97455100017'],
        ];

        $users = [];

        // Friendly dev login.
        $users[] = User::query()->firstOrCreate(
            ['email' => 'demo@qbazaar.qa'],
            [
                'full_name' => 'Demo User',
                'phone' => '+97455100000',
                'password' => Hash::make('password'),
                'account_type' => AccountType::PRIVATE_INDIVIDUAL->value,
                'status' => UserStatus::ACTIVE->value,
                'language' => Language::ARABIC->value,
                'email_verified' => true,
                'phone_verified' => true,
            ],
        );

        foreach ($names as $i => [$name, $phone]) {
            $accountType = $i % 5 === 0 ? AccountType::BUSINESS : AccountType::PRIVATE_INDIVIDUAL;
            $users[] = User::query()->firstOrCreate(
                ['email' => strtolower(str_replace(' ', '.', $name)) . '@example.qa'],
                [
                    'full_name' => $name,
                    'phone' => $phone,
                    'password' => Hash::make('password'),
                    'account_type' => $accountType->value,
                    'status' => UserStatus::ACTIVE->value,
                    'language' => $i % 3 === 0 ? Language::ENGLISH->value : Language::ARABIC->value,
                    'email_verified' => true,
                    'phone_verified' => true,
                    'last_login_at' => Carbon::now()->subDays(random_int(0, 14)),
                ],
            );
        }

        return $users;
    }

    /**
     * @param list<User> $users
     * @param Collection<int, Category> $categories
     * @param Collection<int, Location> $locations
     * @return list<Ad>
     */
    private function seedAds(array $users, $categories, $locations): array
    {
        $titles = [
            ['ar' => 'تويوتا لاند كروزر 2022 فل أوبشن', 'en' => 'Toyota Land Cruiser 2022 fully loaded', 'price' => 285000.00, 'cond' => Condition::USED],
            ['ar' => 'لكزس LX600 جديدة وكالة', 'en' => 'Lexus LX600 brand new — dealer', 'price' => 510000.00, 'cond' => Condition::NEW],
            ['ar' => 'نيسان باترول 2023 — وارد الجوهرة', 'en' => 'Nissan Patrol 2023 — Al Jawhara', 'price' => 220000.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'بي إم دبليو X5 2020 صيانة الوكالة', 'en' => 'BMW X5 2020 dealer service', 'price' => 165000.00, 'cond' => Condition::USED],
            ['ar' => 'شقة 3 غرف للإيجار اللؤلؤة', 'en' => '3-bedroom apartment for rent — The Pearl', 'price' => 14500.00, 'cond' => null],
            ['ar' => 'فيلا 4 غرف للبيع في الدفنة', 'en' => '4-bedroom villa for sale — Dafna', 'price' => 5_400_000.00, 'cond' => null],
            ['ar' => 'شقة استوديو لوسيل مارينا', 'en' => 'Studio at Lusail Marina', 'price' => 7800.00, 'cond' => null],
            ['ar' => 'فيلا للإيجار في الوكرة مع حديقة خاصة', 'en' => 'Villa for rent in Al Wakra with private garden', 'price' => 19500.00, 'cond' => null],
            ['ar' => 'آيفون 15 برو ماكس 256GB', 'en' => 'iPhone 15 Pro Max 256GB', 'price' => 4800.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'سامسونغ S24 Ultra مع الضمان', 'en' => 'Samsung S24 Ultra under warranty', 'price' => 3500.00, 'cond' => Condition::NEW],
            ['ar' => 'ماك بوك برو M3 2024 — 14 إنش', 'en' => 'MacBook Pro M3 2024 — 14"', 'price' => 7900.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'بلايستيشن 5 سليم مع لعبتين', 'en' => 'PlayStation 5 Slim + 2 games', 'price' => 2100.00, 'cond' => Condition::USED],
            ['ar' => 'تلفزيون سوني OLED 65 إنش', 'en' => 'Sony OLED 65" TV', 'price' => 4200.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'طقم كنب جلد إيطالي 6 مقاعد', 'en' => 'Italian leather sofa set (6 seats)', 'price' => 6800.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'طاولة طعام رخام مع 8 كراسي', 'en' => 'Marble dining table + 8 chairs', 'price' => 4500.00, 'cond' => Condition::USED],
            ['ar' => 'غرفة نوم كاملة ماستر', 'en' => 'Full master bedroom set', 'price' => 5200.00, 'cond' => Condition::USED],
            ['ar' => 'مكتب موظف خشبي مع كرسي', 'en' => 'Office desk + chair (wood)', 'price' => 850.00, 'cond' => Condition::USED],
            ['ar' => 'ساعة رولكس Submariner أصلية', 'en' => 'Rolex Submariner — authentic', 'price' => 38000.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'حقيبة لويس فيتون نيوفول', 'en' => 'Louis Vuitton Neverfull bag', 'price' => 5400.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'دراجة هوائية كانوندال', 'en' => 'Cannondale road bike', 'price' => 2900.00, 'cond' => Condition::USED],
            ['ar' => 'قطة شيرازي أصلية مع شجرة', 'en' => 'Persian cat — pedigree + papers', 'price' => 1800.00, 'cond' => null],
            ['ar' => 'طقم ذهب عيار 21', 'en' => '21k gold set', 'price' => 12500.00, 'cond' => Condition::NEW],
            ['ar' => 'مكنسة دايسون V15 ديتكت', 'en' => 'Dyson V15 Detect vacuum', 'price' => 1600.00, 'cond' => Condition::LIKE_NEW],
            ['ar' => 'موظف خدمة عملاء مطلوب', 'en' => 'Customer service representative — hiring', 'price' => null, 'cond' => null],
            ['ar' => 'مدرس رياضيات خصوصي', 'en' => 'Private math tutor available', 'price' => 200.00, 'cond' => null],
            ['ar' => 'خدمات تنظيف المنازل', 'en' => 'Home cleaning services', 'price' => 250.00, 'cond' => null],
            ['ar' => 'مصور أعراس محترف', 'en' => 'Professional wedding photographer', 'price' => 4500.00, 'cond' => null],
        ];

        $descriptionStub = [
            'ar' => "حالة ممتازة جداً، استخدام بسيط. للجادين فقط.\n\nالسعر قابل للتفاوض القليل. التواصل عبر QBazaar فقط — لا أرسل بيانات بنكية.",
            'en' => "Excellent condition, lightly used. Serious buyers only.\n\nPrice negotiable. Contact through QBazaar only — I won't share bank details.",
        ];

        $now = Carbon::now();
        $rows = [];

        // Pre-shuffle the title pool so distribution feels organic across statuses.
        shuffle($titles);

        $bucket = static fn (int $from, int $count) => array_slice($titles, $from, $count);

        $statuses = [
            // [status, count, mutate]
            [AdStatus::ACTIVE, 35, function (Ad $ad) use ($now): void {
                $ad->forceFill([
                    'published_at' => $now->copy()->subDays(random_int(0, 25)),
                    'expires_at' => $now->copy()->addDays(random_int(5, 30)),
                    'views_count' => random_int(20, 1500),
                    'favorites_count' => random_int(0, 90),
                ])->save();
            }],
            [AdStatus::SOLD, 6, function (Ad $ad) use ($now): void {
                $ad->forceFill([
                    'published_at' => $now->copy()->subDays(random_int(20, 60)),
                    'expires_at' => $now->copy()->subDays(random_int(0, 20)),
                    'views_count' => random_int(200, 3000),
                    'favorites_count' => random_int(5, 150),
                ])->save();
            }],
            [AdStatus::EXPIRED, 4, function (Ad $ad) use ($now): void {
                $ad->forceFill([
                    'published_at' => $now->copy()->subDays(40),
                    'expires_at' => $now->copy()->subDays(random_int(1, 10)),
                    'views_count' => random_int(50, 800),
                ])->save();
            }],
            [AdStatus::PENDING, 5, function (Ad $ad) use ($now): void {
                $ad->forceFill([
                    'created_at' => $now->copy()->subHours(random_int(1, 36)),
                ])->save();
            }],
            [AdStatus::REJECTED, 5, function (Ad $ad) use ($now): void {
                $ad->forceFill([
                    'created_at' => $now->copy()->subDays(random_int(2, 10)),
                ])->save();
            }],
            [AdStatus::DRAFT, 5, fn (Ad $ad) => null],
        ];

        $cursor = 0;
        foreach ($statuses as [$status, $count, $mutate]) {
            $slice = $bucket($cursor, $count);
            $cursor += $count;
            foreach ($slice as $i => $t) {
                $owner = $users[($cursor + $i) % count($users)];
                $category = $categories->random();
                $location = $locations->random();

                $priceType = $t['price'] === null
                    ? PriceType::CONTACT
                    : ($i % 7 === 0 ? PriceType::NEGOTIABLE : PriceType::FIXED);

                $ad = Ad::query()->forceCreate([
                    'user_id' => $owner->id,
                    'category_id' => $category->id,
                    'location_id' => $location->id,
                    'title' => $t['ar'],
                    'description' => $descriptionStub['ar'],
                    'price' => $t['price'],
                    'price_type' => $priceType->value,
                    'currency' => 'QAR',
                    'condition' => $t['cond']?->value,
                    'status' => $status->value,
                ]);

                $mutate($ad);
                $ad->refresh();
                $rows[] = $ad;
            }
        }

        // Promote the 6 highest-priced active ads to featured for the home strip.
        $featured = collect($rows)
            ->filter(static fn (Ad $a): bool => $a->status === AdStatus::ACTIVE && $a->price !== null)
            ->sortByDesc(static fn (Ad $a): float => (float) $a->price)
            ->take(6);

        foreach ($featured as $ad) {
            $ad->forceFill(['featured' => true])->save();
        }

        return $rows;
    }

    /**
     * @param list<User> $users
     * @param list<Ad> $ads
     */
    private function seedFavoritesAndViews(array $users, array $ads): void
    {
        $activeAds = collect($ads)->filter(fn (Ad $a) => $a->status === AdStatus::ACTIVE)->values();

        // 40 random favorite pairs (deduped at the DB unique key — duplicates are skipped).
        for ($i = 0; $i < 40; $i++) {
            $user = $users[array_rand($users)];
            $ad = $activeAds->random();
            Favorite::query()->firstOrCreate(['user_id' => $user->id, 'ad_id' => $ad->id]);
        }

        // 80 recent-view rows spread across the past 14 days.
        for ($i = 0; $i < 80; $i++) {
            $user = $users[array_rand($users)];
            $ad = $activeAds->random();
            RecentView::query()->create([
                'user_id' => $user->id,
                'session_id' => null,
                'ad_id' => $ad->id,
                'viewed_at' => Carbon::now()->subDays(random_int(0, 13))->subHours(random_int(0, 23)),
            ]);
        }
    }

    /**
     * @param list<User> $users
     * @param list<Ad> $ads
     */
    private function seedConversationsAndOffers(array $users, array $ads): void
    {
        $activeAds = collect($ads)->filter(fn (Ad $a) => $a->status === AdStatus::ACTIVE)->values();
        $buyers = collect($users);

        $threads = [
            ['hi' => 'مرحبا، هل الإعلان لسا متاح؟', 'reply' => 'أهلاً، نعم متاح.', 'follow' => 'ممكن أعاينه السبت؟', 'final' => 'أكيد، أرسل لي العنوان.'],
            ['hi' => 'السلام عليكم، أقدر أعرض سعر؟', 'reply' => 'وعليكم السلام، تفضل اعرض.', 'follow' => null, 'final' => null],
            ['hi' => 'Hello, is delivery possible to Lusail?', 'reply' => 'Yes, I can deliver for an extra 100 QAR.', 'follow' => 'Great, when can we meet?', 'final' => 'Tomorrow after 5pm works.'],
            ['hi' => 'كم آخر سعر؟', 'reply' => 'مع التفاوض البسيط ممكن.', 'follow' => null, 'final' => null],
            ['hi' => 'أهلاً، السعر شامل التوصيل؟', 'reply' => 'لا، التوصيل خارج الدوحة بإضافة.', 'follow' => 'تمام، تعتبر السعر شامل لو في الدوحة؟', 'final' => 'نعم.'],
            ['hi' => 'هل في ضمان؟', 'reply' => 'ضمان شهرين على القطع الأساسية.', 'follow' => null, 'final' => null],
        ];

        $convs = [];
        for ($i = 0; $i < 12; $i++) {
            $ad = $activeAds->random();
            $seller = $users[array_search($ad->user_id, array_map(fn (User $u) => $u->id, $users), true)] ?? $users[0];
            $buyer = $buyers->reject(fn (User $u) => $u->id === $seller->id)->random();

            $conv = Conversation::query()->firstOrCreate(
                ['ad_id' => $ad->id, 'buyer_id' => $buyer->id],
                ['seller_id' => $seller->id],
            );

            $thread = $threads[$i % count($threads)];
            $lines = array_values(array_filter([$thread['hi'], $thread['reply'], $thread['follow'], $thread['final']]));

            $authors = [$buyer, $seller];
            $lastTime = Carbon::now()->subDays(random_int(0, 10))->subHours(random_int(0, 20));
            foreach ($lines as $k => $line) {
                $sender = $authors[$k % 2];
                $lastTime = $lastTime->copy()->addMinutes(random_int(2, 90));
                Message::query()->create([
                    'conversation_id' => $conv->id,
                    'sender_id' => $sender->id,
                    'body' => $line,
                    'type' => 'text',
                    'read_at' => $sender->id === $buyer->id ? $lastTime : null,
                    'created_at' => $lastTime,
                    'updated_at' => $lastTime,
                ]);
            }

            $conv->forceFill([
                'last_message_at' => $lastTime,
                'last_message_preview' => mb_substr(end($lines), 0, 160),
            ])->save();

            $convs[] = ['conv' => $conv, 'buyer' => $buyer, 'seller' => $seller, 'ad' => $ad];
        }

        // 7 offers across statuses.
        $offerStatuses = [
            OfferStatus::PENDING, OfferStatus::PENDING, OfferStatus::PENDING,
            OfferStatus::ACCEPTED, OfferStatus::REJECTED, OfferStatus::WITHDRAWN, OfferStatus::EXPIRED,
        ];

        foreach ($offerStatuses as $idx => $status) {
            $thread = $convs[$idx % count($convs)];
            $ad = $thread['ad'];
            $amount = $ad->price !== null
                ? round(((float) $ad->price) * 0.85, 2)
                : 1000.00;

            $createdAt = Carbon::now()->subDays(random_int(0, 8));

            $offer = Offer::query()->create([
                'conversation_id' => $thread['conv']->id,
                'ad_id' => $ad->id,
                'buyer_id' => $thread['buyer']->id,
                'seller_id' => $thread['seller']->id,
                'amount' => $amount,
                'currency' => 'QAR',
                'note' => $idx === 0 ? 'هل تقبل بهذا السعر؟' : null,
                'status' => $status->value,
                'expires_at' => $status === OfferStatus::EXPIRED
                    ? $createdAt->copy()->subDay()
                    : $createdAt->copy()->addDays(7),
                'accepted_at' => $status === OfferStatus::ACCEPTED ? $createdAt->copy()->addHours(2) : null,
                'rejected_at' => $status === OfferStatus::REJECTED ? $createdAt->copy()->addHours(3) : null,
                'withdrawn_at' => $status === OfferStatus::WITHDRAWN ? $createdAt->copy()->addHours(1) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            unset($offer);
        }
    }

    /**
     * @param list<User> $users
     * @param list<Ad> $ads
     */
    private function seedReports(array $users, array $ads): void
    {
        $activeAds = collect($ads)->filter(fn (Ad $a) => $a->status === AdStatus::ACTIVE)->values();

        $samples = [
            [ReportCategory::SPAM, ReportStatus::PENDING, 'إعلان مكرر — تم نشر نفس المحتوى عدة مرات.'],
            [ReportCategory::FRAUD, ReportStatus::PENDING, 'البائع يطلب الدفع المسبق عبر تحويل بنكي.'],
            [ReportCategory::INAPPROPRIATE, ReportStatus::PENDING, 'الصور غير مناسبة.'],
            [ReportCategory::DUPLICATE, ReportStatus::PENDING, 'نفس الإعلان منشور من حساب آخر.'],
            [ReportCategory::WRONG_CATEGORY, ReportStatus::PENDING, 'الإعلان منشور في القسم الخطأ.'],
            [ReportCategory::FRAUD, ReportStatus::REVIEWED, 'تم التحقق — الإعلان نظامي.'],
            [ReportCategory::OFFENSIVE, ReportStatus::REVIEWED, 'العنوان به لفظ غير لائق.'],
            [ReportCategory::SPAM, ReportStatus::ACTIONED, 'تم حذف الإعلان وحظر البائع.'],
        ];

        foreach ($samples as [$cat, $status, $description]) {
            $ad = $activeAds->random();
            $reporter = collect($users)->reject(fn (User $u) => $u->id === $ad->user_id)->random();
            Report::query()->create([
                'reporter_id' => $reporter->id,
                'target_type' => ReportTarget::AD->value,
                'target_id' => $ad->id,
                'category' => $cat->value,
                'description' => $description,
                'status' => $status->value,
                'reviewed_at' => $status !== ReportStatus::PENDING ? Carbon::now()->subDays(random_int(0, 5)) : null,
                'admin_notes' => $status === ReportStatus::ACTIONED ? 'Ad removed + seller banned for 30d.' : null,
            ]);
        }
    }

    /**
     * @param list<User> $users
     */
    private function seedSupportTickets(array $users): void
    {
        $tickets = [
            ['subject' => 'كيف أعيد تفعيل حسابي؟', 'cat' => SupportTicketCategory::TECHNICAL, 'status' => SupportTicketStatus::OPEN, 'priority' => SupportTicketPriority::NORMAL],
            ['subject' => 'لم أستلم رمز التحقق', 'cat' => SupportTicketCategory::TECHNICAL, 'status' => SupportTicketStatus::IN_PROGRESS, 'priority' => SupportTicketPriority::HIGH],
            ['subject' => 'بائع مشتبه به', 'cat' => SupportTicketCategory::ABUSE, 'status' => SupportTicketStatus::WAITING_USER, 'priority' => SupportTicketPriority::HIGH],
            ['subject' => 'اقتراح ميزة', 'cat' => SupportTicketCategory::FEEDBACK, 'status' => SupportTicketStatus::OPEN, 'priority' => SupportTicketPriority::LOW],
            ['subject' => 'مشكلة محلولة', 'cat' => SupportTicketCategory::GENERAL, 'status' => SupportTicketStatus::RESOLVED, 'priority' => SupportTicketPriority::NORMAL],
        ];

        foreach ($tickets as $t) {
            $user = $users[array_rand($users)];
            $ticket = SupportTicket::query()->create([
                'user_id' => $user->id,
                'email' => null,
                'subject' => $t['subject'],
                'category' => $t['cat']->value,
                'body' => 'مرحبا، أحتاج مساعدة في المشكلة الموضحة في العنوان. شكراً.',
                'status' => $t['status']->value,
                'priority' => $t['priority']->value,
                'last_replied_at' => $t['status'] !== SupportTicketStatus::OPEN
                    ? Carbon::now()->subDays(random_int(0, 3))
                    : null,
            ]);

            if ($t['status'] === SupportTicketStatus::IN_PROGRESS || $t['status'] === SupportTicketStatus::WAITING_USER) {
                SupportReply::query()->create([
                    'ticket_id' => $ticket->id,
                    'author_id' => $users[0]->id,
                    'is_staff' => true,
                    'body' => 'أهلاً، فريق الدعم يراجع طلبك. سنرد قريباً.',
                ]);
            }

            if ($t['status'] === SupportTicketStatus::RESOLVED) {
                SupportReply::query()->create([
                    'ticket_id' => $ticket->id,
                    'author_id' => $users[0]->id,
                    'is_staff' => true,
                    'body' => 'تم حل المشكلة. شكراً لتواصلك.',
                ]);
            }

            unset($ticket);
        }
    }
}
