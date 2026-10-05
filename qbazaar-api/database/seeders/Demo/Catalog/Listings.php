<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Catalog;

use App\Enums\PriceType;
use Database\Seeders\Demo\DemoRandom;

/**
 * What people in Qatar actually list, per leaf category of CategorySeeder:
 * bilingual titles, a realistic QAR price band, and the custom fields the
 * category's schema asks for.
 */
final class Listings
{
    /** Placeholder photo colour per top-level category. */
    public const array COLORS = [
        'vehicles' => '#1F4E79',
        'real-estate' => '#2E7D32',
        'electronics' => '#37474F',
        'home-and-garden' => '#8D6E63',
        'fashion' => '#AD1457',
        'jobs' => '#5D4037',
        'services' => '#00838F',
        'pets' => '#EF6C00',
        'hobbies-and-sports' => '#6A1B9A',
        'business-and-industrial' => '#455A64',
    ];

    public const string DEFAULT_COLOR = '#8A1538';

    private const array NO_CONDITION_PARENTS = ['real-estate', 'jobs', 'services', 'pets'];

    /**
     * leaf slug => list of [ar title, en title, min QAR, max QAR, extra]
     *
     * @var array<string, list<array{0: string, 1: string, 2: int, 3: int, 4?: array<string, mixed>}>>
     */
    private const array TEMPLATES = [
        'cars' => [
            ['تويوتا لاند كروزر GXR', 'Toyota Land Cruiser GXR', 160_000, 320_000, ['make' => 'Toyota', 'model' => 'Land Cruiser']],
            ['نيسان باترول بلاتينيوم', 'Nissan Patrol Platinum', 140_000, 280_000, ['make' => 'Nissan', 'model' => 'Patrol']],
            ['لكزس LX 600', 'Lexus LX 600', 330_000, 520_000, ['make' => 'Lexus', 'model' => 'LX 600']],
            ['تويوتا كامري', 'Toyota Camry', 45_000, 110_000, ['make' => 'Toyota', 'model' => 'Camry']],
            ['هوندا أكورد', 'Honda Accord', 38_000, 95_000, ['make' => 'Honda', 'model' => 'Accord']],
            ['مرسيدس G63', 'Mercedes G63 AMG', 520_000, 850_000, ['make' => 'Mercedes', 'model' => 'G63']],
            ['بي إم دبليو X5', 'BMW X5', 120_000, 260_000, ['make' => 'BMW', 'model' => 'X5']],
            ['أودي Q7', 'Audi Q7', 110_000, 230_000, ['make' => 'Audi', 'model' => 'Q7']],
        ],
        'motorcycles' => [['دراجة هارلي ديفيدسون', 'Harley-Davidson Street Glide', 45_000, 95_000], ['ياماها R1', 'Yamaha R1', 35_000, 70_000], ['دباب بولاريس للبر', 'Polaris desert quad', 18_000, 45_000]],
        'boats' => [['قارب صيد 28 قدم', '28 ft fishing boat', 85_000, 220_000], ['جت سكي سي دو', 'Sea-Doo jet ski', 25_000, 60_000]],
        'auto-parts' => [['جنوط أصلية لاندكروزر', 'Original Land Cruiser rims', 1_500, 6_000], ['إطارات ميشلان 20 إنش', 'Michelin 20" tyres (set of 4)', 1_800, 4_500], ['شاشة أندرويد للسيارة', 'Android car head unit', 450, 1_600]],
        'apartments-for-rent' => [
            ['شقة غرفتين اللؤلؤة إطلالة بحرية', '2BR sea-view apartment, The Pearl', 9_000, 16_000],
            ['استوديو مفروش في لوسيل', 'Furnished studio in Lusail', 4_500, 7_500],
            ['شقة 3 غرف في السد', '3BR apartment in Al Sadd', 8_000, 12_500],
            ['شقة غرفة في مشيرب', '1BR apartment in Msheireb', 6_500, 10_000],
        ],
        'apartments-for-sale' => [['شقة تملك حر في اللؤلؤة', 'Freehold apartment, The Pearl', 1_100_000, 3_500_000], ['شقة للبيع في لوسيل مارينا', 'Apartment for sale, Lusail Marina', 900_000, 2_400_000]],
        'villas-for-rent' => [['فيلا 5 غرف مع مسبح في الوعب', '5BR villa with pool, Al Waab', 18_000, 32_000], ['فيلا في كمباوند بالغرافة', 'Compound villa in Al Gharafa', 14_000, 24_000]],
        'villas-for-sale' => [['فيلا مستقلة للبيع في الوكرة', 'Standalone villa for sale, Al Wakra', 2_800_000, 6_500_000], ['فيلا فاخرة في الخليج الغربي', 'Luxury villa in West Bay', 7_000_000, 15_000_000]],
        'land' => [['أرض سكنية في أم صلال', 'Residential plot in Umm Salal', 900_000, 2_500_000], ['أرض تجارية في الخور', 'Commercial land in Al Khor', 1_500_000, 4_000_000]],
        'commercial-property' => [['محل تجاري في السد', 'Retail shop in Al Sadd', 12_000, 35_000], ['مكتب مجهز في الخليج الغربي', 'Fitted office in West Bay', 15_000, 45_000]],
        'mobile-phones' => [
            ['آيفون 15 برو ماكس', 'iPhone 15 Pro Max', 3_200, 5_200, ['brand' => 'Apple']],
            ['آيفون 14', 'iPhone 14', 1_800, 2_900, ['brand' => 'Apple']],
            ['سامسونج جالكسي S24 ألترا', 'Samsung Galaxy S24 Ultra', 2_600, 4_300, ['brand' => 'Samsung']],
            ['جوجل بكسل 8 برو', 'Google Pixel 8 Pro', 1_900, 3_100, ['brand' => 'Google']],
            ['شاومي 14', 'Xiaomi 14', 1_400, 2_400, ['brand' => 'Xiaomi']],
        ],
        'computers-and-laptops' => [['ماك بوك برو M3', 'MacBook Pro M3 14"', 5_500, 9_000], ['لابتوب ديل XPS 15', 'Dell XPS 15 laptop', 3_500, 6_500], ['كمبيوتر ألعاب RTX 4070', 'Gaming PC RTX 4070', 4_500, 8_000]],
        'tvs' => [['تلفزيون سوني OLED 65 إنش', 'Sony OLED 65" TV', 3_000, 6_500], ['شاشة سامسونج 55 إنش', 'Samsung 55" 4K TV', 1_200, 2_600]],
        'cameras' => [['كاميرا كانون R6', 'Canon EOS R6 body', 5_000, 8_000], ['درون دي جي آي ميني 4', 'DJI Mini 4 Pro drone', 2_200, 3_600]],
        'audio-and-headphones' => [['سماعات سوني XM5', 'Sony WH-1000XM5', 700, 1_200], ['إيربودز برو 2', 'AirPods Pro 2', 550, 850]],
        'gaming-consoles' => [['بلايستيشن 5 مع يدين', 'PlayStation 5 with two controllers', 1_400, 2_200], ['نينتندو سويتش OLED', 'Nintendo Switch OLED', 900, 1_400]],
        'smart-watches' => [['ساعة أبل ألترا 2', 'Apple Watch Ultra 2', 2_300, 3_300], ['جالكسي ووتش 6', 'Galaxy Watch 6', 600, 1_100]],
        'furniture' => [['كنب جلد إيطالي 7 مقاعد', 'Italian leather sofa set, 7 seats', 3_500, 9_000], ['غرفة نوم ماستر كاملة', 'Complete master bedroom set', 2_500, 7_000], ['طاولة طعام رخام 8 كراسي', 'Marble dining table with 8 chairs', 2_000, 6_000]],
        'appliances' => [['غسالة إل جي 9 كيلو', 'LG 9 kg washing machine', 700, 1_600], ['ثلاجة سامسونج بابين', 'Samsung side-by-side fridge', 1_500, 3_800], ['مكيف سبليت 2 طن', '2-ton split AC', 1_200, 2_600]],
        'kitchen' => [['ماكينة قهوة ديلونجي', "De'Longhi espresso machine", 600, 2_200], ['طقم أواني جرانيت', 'Granite cookware set', 250, 700]],
        'home-decor' => [['سجاد إيراني يدوي', 'Hand-made Persian rug', 1_500, 8_000], ['ثريا كريستال', 'Crystal chandelier', 900, 3_500]],
        'garden' => [['جلسة خارجية راتان', 'Rattan outdoor lounge set', 1_200, 4_000], ['شواية ويبر غاز', 'Weber gas grill', 900, 2_500]],
        'mens-clothing' => [['ثياب قطرية تفصيل', 'Tailored Qatari thobes (set of 3)', 400, 1_200], ['بشت رجالي', "Men's bisht", 800, 3_500]],
        'womens-clothing' => [['عباية مطرزة جديدة', 'New embroidered abaya', 350, 1_500], ['فستان سهرة', 'Evening dress', 400, 2_000]],
        'kids' => [['عربة أطفال بوغابو', 'Bugaboo stroller', 1_200, 3_000], ['كرسي سيارة للأطفال', 'Child car seat', 300, 900], ['ملابس أطفال 2-4 سنوات', 'Kids clothes bundle, 2-4 years', 80, 250]],
        'bags' => [['حقيبة لويس فيتون نيفرفول', 'Louis Vuitton Neverfull', 3_500, 6_500], ['حقيبة سفر سامسونايت', 'Samsonite suitcase', 400, 1_200]],
        'watches' => [['ساعة رولكس صب مارينر', 'Rolex Submariner', 38_000, 65_000], ['ساعة أوميغا سيماستر', 'Omega Seamaster', 14_000, 24_000]],
        'jewelry' => [['طقم ذهب عيار 21', '21k gold set', 6_000, 18_000], ['خاتم ألماس', 'Diamond ring', 4_000, 15_000]],
        'shoes' => [['حذاء نايك جوردن', 'Nike Air Jordan 1', 450, 1_200], ['نعال رجالي جلد', "Men's leather sandals", 150, 450]],
        'full-time' => [['مطلوب محاسب بخبرة', 'Accountant needed, full time', 0, 0, ['price_type' => 'contact']], ['مطلوب مندوب مبيعات', 'Sales executive wanted', 0, 0, ['price_type' => 'contact']]],
        'part-time' => [['مطلوب كاشير دوام جزئي', 'Part-time cashier needed', 0, 0, ['price_type' => 'contact']]],
        'freelance' => [['مصمم جرافيك مستقل', 'Freelance graphic designer', 300, 2_000]],
        'internships' => [['تدريب تسويق رقمي', 'Digital marketing internship', 0, 0, ['price_type' => 'contact']]],
        'cleaning' => [['تنظيف منازل بالساعة', 'Hourly home cleaning', 35, 60], ['تنظيف كنب وسجاد', 'Sofa and carpet deep cleaning', 150, 450]],
        'plumbing' => [['فني سباكة 24 ساعة', '24/7 plumber', 100, 300]],
        'electrical' => [['كهربائي منازل مرخص', 'Licensed home electrician', 100, 350]],
        'tutoring' => [['مدرس رياضيات وفيزياء', 'Maths and physics tutor', 150, 300], ['دروس لغة إنجليزية', 'English lessons, IELTS prep', 120, 250]],
        'beauty' => [['خدمات حناء ومكياج', 'Henna and makeup at home', 200, 800]],
        'photography' => [['مصور حفلات وأعراس', 'Wedding and event photographer', 1_500, 6_000]],
        'moving' => [['نقل عفش مع الفك والتركيب', 'Furniture moving with assembly', 400, 1_500]],
        'cats' => [['قطط شيرازي مطعمة', 'Vaccinated Persian kittens', 600, 2_000], ['قطة سكوتش فولد', 'Scottish Fold kitten', 1_200, 3_000]],
        'dogs' => [['جرو جولدن ريتريفر', 'Golden Retriever puppy', 2_500, 6_000]],
        'birds' => [['ببغاء كاسكو', 'African grey parrot', 2_000, 5_000], ['طيور حب مع القفص', 'Lovebirds with cage', 150, 500]],
        'fish' => [['حوض أسماك 200 لتر', '200 L aquarium with fish', 600, 2_000]],
        'pet-accessories' => [['قفص قطط كبير', 'Large cat cage', 150, 500], ['طعام كلاب رويال كانين', 'Royal Canin dog food 15 kg', 250, 400]],
        'sports-equipment' => [['جهاز مشي تكنوجيم', 'Technogym treadmill', 3_000, 9_000], ['أوزان دمبل كاملة', 'Complete dumbbell set', 600, 1_800]],
        'bicycles' => [['دراجة هوائية كانونديل', 'Cannondale road bike', 2_200, 6_000], ['دراجة أطفال 16 إنش', "Kids' bike 16\"", 200, 500]],
        'books' => [['مجموعة كتب أطفال عربية', 'Arabic children books collection', 80, 300], ['كتب جامعية هندسة', 'Engineering university textbooks', 100, 400]],
        'musical-instruments' => [['عود عربي يدوي', 'Hand-made Arabic oud', 1_200, 4_500], ['بيانو كهربائي ياماها', 'Yamaha digital piano', 1_800, 4_000]],
        'art-and-crafts' => [['لوحة خط عربي أصلية', 'Original Arabic calligraphy painting', 800, 5_000]],
        'office-furniture' => [['مكاتب موظفين مع كراسي', 'Staff desks with chairs (set of 6)', 1_500, 5_000], ['طاولة اجتماعات 10 أشخاص', '10-seat meeting table', 1_200, 3_500]],
        'industrial-equipment' => [['مولد كهرباء 100 كيلو', '100 kVA generator', 25_000, 60_000], ['رافعة شوكية تويوتا', 'Toyota forklift', 35_000, 75_000]],
        'restaurant-equipment' => [['معدات مطبخ مطعم كاملة', 'Complete restaurant kitchen', 18_000, 60_000], ['فرن بيتزا إيطالي', 'Italian pizza oven', 6_000, 18_000]],
    ];

    private const array DESCRIPTIONS = [
        'ar' => [
            "%s بحالة ممتازة، الاستخدام بسيط جداً.\nالمعاينة في %s بعد العصر.\nالتواصل عبر محادثة QBazaar فقط، لا أرسل بيانات بنكية.",
            "للبيع %s، نظيف ومحافظ عليه.\nالموقع: %s. السعر قابل للتفاوض البسيط للجادين.",
            "%s متوفر الآن.\nالاستلام من %s أو توصيل داخل الدوحة بإضافة بسيطة.",
        ],
        'en' => [
            "%s in excellent condition, lightly used.\nViewing in %s after 4pm.\nPlease contact me through QBazaar chat only.",
            "Selling %s, clean and well kept.\nLocation: %s. Small negotiation for serious buyers.",
            "%s available now.\nPick-up from %s or delivery inside Doha for a small fee.",
        ],
    ];

    public function __construct(
        private readonly DemoRandom $random,
    ) {}

    /**
     * @return list<string>
     */
    public function categorySlugs(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public function colorFor(?string $parentSlug): string
    {
        return self::COLORS[$parentSlug] ?? self::DEFAULT_COLOR;
    }

    public function draft(string $leafSlug, string $parentSlug, string $language, string $area): ListingDraft
    {
        $template = $this->random->pick($this->templatesFor($leafSlug));
        [$titleAr, $titleEn, $min, $max, $extra] = $template;
        $title = $language === 'ar' ? $titleAr : $titleEn;
        $priceType = $this->priceType($extra, $min);

        return new ListingDraft(
            title: $title,
            captionEn: $titleEn,
            description: sprintf($this->random->pick(self::DESCRIPTIONS[$language]), $title, $area),
            price: $priceType === PriceType::CONTACT || $priceType === PriceType::FREE ? null : (string) $this->random->price($min, $max),
            priceType: $priceType,
            hasCondition: ! in_array($parentSlug, self::NO_CONDITION_PARENTS, true),
            customFields: $this->customFields($leafSlug, $extra),
        );
    }

    /**
     * @return list<array{string, string, int, int, array<string, mixed>}>
     */
    private function templatesFor(string $leafSlug): array
    {
        return array_map(self::template(...), self::TEMPLATES[$leafSlug]);
    }

    /**
     * @param array<int, mixed> $row
     * @return array{string, string, int, int, array<string, mixed>}
     */
    private static function template(array $row): array
    {
        /** @var array<string, mixed> $extra */
        $extra = is_array($row[4] ?? null) ? $row[4] : [];

        return [(string) $row[0], (string) $row[1], (int) $row[2], (int) $row[3], $extra];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function priceType(array $extra, int $min): PriceType
    {
        if (($extra['price_type'] ?? null) === 'contact') {
            return PriceType::CONTACT;
        }

        if ($min < 1_000 && $this->random->chance(0.03)) {
            return PriceType::FREE;
        }

        return $this->random->chance(0.35) ? PriceType::NEGOTIABLE : PriceType::FIXED;
    }

    /**
     * Values for the categories whose schema has required fields.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>|null
     */
    private function customFields(string $leafSlug, array $extra): ?array
    {
        return match ($leafSlug) {
            'cars' => [
                'make' => $extra['make'],
                'model' => $extra['model'],
                'year' => $this->random->int(2015, 2025),
                'mileage_km' => $this->random->int(5, 180) * 1_000,
                'transmission' => $this->random->chance(0.92) ? 'automatic' : 'manual',
                'fuel_type' => $this->random->pick(['petrol', 'petrol', 'petrol', 'hybrid', 'diesel']),
            ],
            'apartments-for-rent' => [
                'bedrooms' => $this->random->int(1, 4),
                'bathrooms' => $this->random->int(1, 4),
                'furnished' => $this->random->pick(['furnished', 'semi_furnished', 'unfurnished']),
                'parking' => $this->random->chance(0.8),
            ],
            'mobile-phones' => [
                'brand' => $extra['brand'],
                'storage_gb' => $this->random->pick(['128', '256', '256', '512']),
                'condition' => $this->random->pick(['new', 'like_new', 'good', 'fair']),
            ],
            default => null,
        };
    }
}
