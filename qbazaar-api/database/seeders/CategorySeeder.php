<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Seeds the public category taxonomy used by browse + post-ad UX.
 *
 * Design notes:
 *  - Source data lives in {@see self::tree()} so the seeder reads top-to-bottom
 *    like the catalogue itself; the writer loops over it and creates rows.
 *  - `custom_fields` / `custom_filters` are defined only for a handful of
 *    high-traffic leaves (Cars / Apartments for Rent / Mobile Phones); other
 *    leaves keep them null until product decides on per-category fields.
 *  - We `truncate` the table first so re-seeding stays idempotent during
 *    development. Re-seeding production would be a deliberate, manual op.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Children FK is ON DELETE CASCADE so a single delete on parents
        // takes care of orphans. Truncate would require disabling FK checks.
        Category::query()->delete();
        Cache::forget('categories.tree');
        Cache::forget('categories.main');

        foreach ($this->tree() as $parentOrder => $group) {
            /** @var Category $parent */
            $parent = Category::query()->create([
                'parent_id' => null,
                'slug' => $group['slug'],
                'name' => $group['name'],
                'description' => null,
                'icon' => $group['icon'],
                'order' => $parentOrder,
                'is_active' => true,
                'custom_fields' => null,
                'custom_filters' => null,
            ]);

            foreach ($group['children'] as $childOrder => $child) {
                Category::query()->create([
                    'parent_id' => $parent->id,
                    'slug' => $child['slug'],
                    'name' => $child['name'],
                    'description' => null,
                    'icon' => $child['icon'] ?? null,
                    'order' => $childOrder,
                    'is_active' => true,
                    'custom_fields' => $child['custom_fields'] ?? null,
                    'custom_filters' => $child['custom_filters'] ?? null,
                ]);
            }
        }
    }

    /**
     * Static category tree used by the seeder.
     *
     * @return list<array{
     *     slug: string,
     *     name: array{ar: string, en: string},
     *     icon: string,
     *     children: list<array{
     *         slug: string,
     *         name: array{ar: string, en: string},
     *         icon?: string,
     *         custom_fields?: list<array<string, mixed>>,
     *         custom_filters?: list<array<string, mixed>>,
     *     }>,
     * }>
     */
    private function tree(): array
    {
        return [
            [
                'slug' => 'vehicles',
                'name' => ['ar' => 'مركبات', 'en' => 'Vehicles'],
                'icon' => 'Car',
                'children' => [
                    [
                        'slug' => 'cars',
                        'name' => ['ar' => 'سيارات', 'en' => 'Cars'],
                        'custom_fields' => $this->carsFields(),
                        'custom_filters' => $this->carsFilters(),
                    ],
                    [
                        'slug' => 'motorcycles',
                        'name' => ['ar' => 'دراجات نارية', 'en' => 'Motorcycles'],
                    ],
                    [
                        'slug' => 'boats',
                        'name' => ['ar' => 'قوارب', 'en' => 'Boats'],
                    ],
                    [
                        'slug' => 'auto-parts',
                        'name' => ['ar' => 'قطع غيار سيارات', 'en' => 'Auto Parts'],
                    ],
                ],
            ],
            [
                'slug' => 'real-estate',
                'name' => ['ar' => 'عقارات', 'en' => 'Real Estate'],
                'icon' => 'Home',
                'children' => [
                    [
                        'slug' => 'apartments-for-rent',
                        'name' => ['ar' => 'شقق للإيجار', 'en' => 'Apartments for Rent'],
                        'custom_fields' => $this->apartmentsFields(),
                        'custom_filters' => $this->apartmentsFilters(),
                    ],
                    [
                        'slug' => 'apartments-for-sale',
                        'name' => ['ar' => 'شقق للبيع', 'en' => 'Apartments for Sale'],
                    ],
                    [
                        'slug' => 'villas-for-rent',
                        'name' => ['ar' => 'فلل للإيجار', 'en' => 'Villas for Rent'],
                    ],
                    [
                        'slug' => 'villas-for-sale',
                        'name' => ['ar' => 'فلل للبيع', 'en' => 'Villas for Sale'],
                    ],
                    [
                        'slug' => 'land',
                        'name' => ['ar' => 'أراضي', 'en' => 'Land'],
                    ],
                    [
                        'slug' => 'commercial-property',
                        'name' => ['ar' => 'عقارات تجارية', 'en' => 'Commercial Property'],
                    ],
                ],
            ],
            [
                'slug' => 'electronics',
                'name' => ['ar' => 'إلكترونيات', 'en' => 'Electronics'],
                'icon' => 'Smartphone',
                'children' => [
                    [
                        'slug' => 'mobile-phones',
                        'name' => ['ar' => 'هواتف محمولة', 'en' => 'Mobile Phones'],
                        'custom_fields' => $this->mobilePhonesFields(),
                        'custom_filters' => $this->mobilePhonesFilters(),
                    ],
                    [
                        'slug' => 'computers-and-laptops',
                        'name' => ['ar' => 'حواسيب وأجهزة محمولة', 'en' => 'Computers & Laptops'],
                    ],
                    [
                        'slug' => 'tvs',
                        'name' => ['ar' => 'تلفزيونات', 'en' => 'TVs'],
                    ],
                    [
                        'slug' => 'cameras',
                        'name' => ['ar' => 'كاميرات', 'en' => 'Cameras'],
                    ],
                    [
                        'slug' => 'audio-and-headphones',
                        'name' => ['ar' => 'صوتيات وسماعات', 'en' => 'Audio & Headphones'],
                    ],
                    [
                        'slug' => 'gaming-consoles',
                        'name' => ['ar' => 'أجهزة ألعاب', 'en' => 'Gaming Consoles'],
                    ],
                    [
                        'slug' => 'smart-watches',
                        'name' => ['ar' => 'ساعات ذكية', 'en' => 'Smart Watches'],
                    ],
                ],
            ],
            [
                'slug' => 'home-and-garden',
                'name' => ['ar' => 'منزل وحديقة', 'en' => 'Home & Garden'],
                'icon' => 'Sofa',
                'children' => [
                    ['slug' => 'furniture', 'name' => ['ar' => 'أثاث', 'en' => 'Furniture']],
                    ['slug' => 'appliances', 'name' => ['ar' => 'أجهزة منزلية', 'en' => 'Appliances']],
                    ['slug' => 'kitchen', 'name' => ['ar' => 'مطبخ', 'en' => 'Kitchen']],
                    ['slug' => 'home-decor', 'name' => ['ar' => 'ديكور منزلي', 'en' => 'Home Decor']],
                    ['slug' => 'garden', 'name' => ['ar' => 'حديقة', 'en' => 'Garden']],
                ],
            ],
            [
                'slug' => 'fashion',
                'name' => ['ar' => 'موضة', 'en' => 'Fashion'],
                'icon' => 'Shirt',
                'children' => [
                    ['slug' => 'mens-clothing', 'name' => ['ar' => 'ملابس رجالية', 'en' => "Men's Clothing"]],
                    ['slug' => 'womens-clothing', 'name' => ['ar' => 'ملابس نسائية', 'en' => "Women's Clothing"]],
                    ['slug' => 'kids', 'name' => ['ar' => 'أطفال', 'en' => 'Kids']],
                    ['slug' => 'bags', 'name' => ['ar' => 'حقائب', 'en' => 'Bags']],
                    ['slug' => 'watches', 'name' => ['ar' => 'ساعات', 'en' => 'Watches']],
                    ['slug' => 'jewelry', 'name' => ['ar' => 'مجوهرات', 'en' => 'Jewelry']],
                    ['slug' => 'shoes', 'name' => ['ar' => 'أحذية', 'en' => 'Shoes']],
                ],
            ],
            [
                'slug' => 'jobs',
                'name' => ['ar' => 'وظائف', 'en' => 'Jobs'],
                'icon' => 'Briefcase',
                'children' => [
                    ['slug' => 'full-time', 'name' => ['ar' => 'دوام كامل', 'en' => 'Full-time']],
                    ['slug' => 'part-time', 'name' => ['ar' => 'دوام جزئي', 'en' => 'Part-time']],
                    ['slug' => 'freelance', 'name' => ['ar' => 'عمل حر', 'en' => 'Freelance']],
                    ['slug' => 'internships', 'name' => ['ar' => 'تدريب', 'en' => 'Internships']],
                ],
            ],
            [
                'slug' => 'services',
                'name' => ['ar' => 'خدمات', 'en' => 'Services'],
                'icon' => 'Wrench',
                'children' => [
                    ['slug' => 'cleaning', 'name' => ['ar' => 'تنظيف', 'en' => 'Cleaning']],
                    ['slug' => 'plumbing', 'name' => ['ar' => 'سباكة', 'en' => 'Plumbing']],
                    ['slug' => 'electrical', 'name' => ['ar' => 'كهرباء', 'en' => 'Electrical']],
                    ['slug' => 'tutoring', 'name' => ['ar' => 'دروس خصوصية', 'en' => 'Tutoring']],
                    ['slug' => 'beauty', 'name' => ['ar' => 'تجميل', 'en' => 'Beauty']],
                    ['slug' => 'photography', 'name' => ['ar' => 'تصوير', 'en' => 'Photography']],
                    ['slug' => 'moving', 'name' => ['ar' => 'نقل عفش', 'en' => 'Moving']],
                ],
            ],
            [
                'slug' => 'pets',
                'name' => ['ar' => 'حيوانات أليفة', 'en' => 'Pets'],
                'icon' => 'PawPrint',
                'children' => [
                    ['slug' => 'cats', 'name' => ['ar' => 'قطط', 'en' => 'Cats']],
                    ['slug' => 'dogs', 'name' => ['ar' => 'كلاب', 'en' => 'Dogs']],
                    ['slug' => 'birds', 'name' => ['ar' => 'طيور', 'en' => 'Birds']],
                    ['slug' => 'fish', 'name' => ['ar' => 'أسماك', 'en' => 'Fish']],
                    ['slug' => 'pet-accessories', 'name' => ['ar' => 'مستلزمات حيوانات', 'en' => 'Pet Accessories']],
                ],
            ],
            [
                'slug' => 'hobbies-and-sports',
                'name' => ['ar' => 'هوايات ورياضة', 'en' => 'Hobbies & Sports'],
                'icon' => 'Bike',
                'children' => [
                    ['slug' => 'sports-equipment', 'name' => ['ar' => 'معدات رياضية', 'en' => 'Sports Equipment']],
                    ['slug' => 'bicycles', 'name' => ['ar' => 'دراجات هوائية', 'en' => 'Bicycles']],
                    ['slug' => 'books', 'name' => ['ar' => 'كتب', 'en' => 'Books']],
                    ['slug' => 'musical-instruments', 'name' => ['ar' => 'آلات موسيقية', 'en' => 'Musical Instruments']],
                    ['slug' => 'art-and-crafts', 'name' => ['ar' => 'فنون وحرف', 'en' => 'Art & Crafts']],
                ],
            ],
            [
                'slug' => 'business-and-industrial',
                'name' => ['ar' => 'أعمال وصناعة', 'en' => 'Business & Industrial'],
                'icon' => 'Factory',
                'children' => [
                    ['slug' => 'office-furniture', 'name' => ['ar' => 'أثاث مكتبي', 'en' => 'Office Furniture']],
                    ['slug' => 'industrial-equipment', 'name' => ['ar' => 'معدات صناعية', 'en' => 'Industrial Equipment']],
                    ['slug' => 'restaurant-equipment', 'name' => ['ar' => 'معدات مطاعم', 'en' => 'Restaurant Equipment']],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function carsFields(): array
    {
        return [
            [
                'key' => 'make',
                'type' => 'select',
                'required' => true,
                'options' => ['Toyota', 'Nissan', 'Honda', 'BMW', 'Mercedes', 'Audi', 'Lexus', 'Other'],
                'option_labels' => ['Other' => ['ar' => 'أخرى', 'en' => 'Other']],
                'label' => ['ar' => 'الماركة', 'en' => 'Make'],
            ],
            [
                'key' => 'model',
                'type' => 'text',
                'required' => true,
                'label' => ['ar' => 'الموديل', 'en' => 'Model'],
            ],
            [
                'key' => 'year',
                'type' => 'number',
                'required' => true,
                'show_in_card' => true,
                'label' => ['ar' => 'سنة الصنع', 'en' => 'Year'],
            ],
            [
                'key' => 'mileage_km',
                'type' => 'number',
                'required' => false,
                'show_in_card' => true,
                'label' => ['ar' => 'الكيلومترات', 'en' => 'Mileage (km)'],
            ],
            [
                'key' => 'transmission',
                'type' => 'select',
                'required' => true,
                'show_in_card' => true,
                'options' => ['automatic', 'manual'],
                'option_labels' => [
                    'automatic' => ['ar' => 'أوتوماتيك', 'en' => 'Automatic'],
                    'manual' => ['ar' => 'عادي', 'en' => 'Manual'],
                ],
                'label' => ['ar' => 'ناقل الحركة', 'en' => 'Transmission'],
            ],
            [
                'key' => 'fuel_type',
                'type' => 'select',
                'required' => true,
                'options' => ['petrol', 'diesel', 'hybrid', 'electric'],
                'option_labels' => [
                    'petrol' => ['ar' => 'بنزين', 'en' => 'Petrol'],
                    'diesel' => ['ar' => 'ديزل', 'en' => 'Diesel'],
                    'hybrid' => ['ar' => 'هجين', 'en' => 'Hybrid'],
                    'electric' => ['ar' => 'كهرباء', 'en' => 'Electric'],
                ],
                'label' => ['ar' => 'الوقود', 'en' => 'Fuel'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function carsFilters(): array
    {
        return [
            [
                'key' => 'make',
                'type' => 'select',
                'options' => ['Toyota', 'Nissan', 'Honda', 'BMW', 'Mercedes', 'Audi', 'Lexus', 'Other'],
                'label' => ['ar' => 'الماركة', 'en' => 'Make'],
            ],
            [
                'key' => 'transmission',
                'type' => 'select',
                'options' => ['automatic', 'manual'],
                'label' => ['ar' => 'ناقل الحركة', 'en' => 'Transmission'],
            ],
            [
                'key' => 'fuel_type',
                'type' => 'select',
                'options' => ['petrol', 'diesel', 'hybrid', 'electric'],
                'label' => ['ar' => 'الوقود', 'en' => 'Fuel'],
            ],
            [
                'key' => 'price',
                'type' => 'range',
                'options' => null,
                'label' => ['ar' => 'السعر', 'en' => 'Price'],
            ],
            [
                'key' => 'year',
                'type' => 'range',
                'options' => null,
                'label' => ['ar' => 'سنة الصنع', 'en' => 'Year'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function apartmentsFields(): array
    {
        return [
            [
                'key' => 'bedrooms',
                'type' => 'number',
                'required' => true,
                'show_in_card' => true,
                'label' => ['ar' => 'غرف النوم', 'en' => 'Bedrooms'],
            ],
            [
                'key' => 'bathrooms',
                'type' => 'number',
                'required' => true,
                'show_in_card' => true,
                'label' => ['ar' => 'الحمامات', 'en' => 'Bathrooms'],
            ],
            [
                'key' => 'furnished',
                'type' => 'select',
                'required' => true,
                'show_in_card' => true,
                'options' => ['furnished', 'semi_furnished', 'unfurnished'],
                'option_labels' => [
                    'furnished' => ['ar' => 'مفروش', 'en' => 'Furnished'],
                    'semi_furnished' => ['ar' => 'مفروش جزئياً', 'en' => 'Semi-furnished'],
                    'unfurnished' => ['ar' => 'غير مفروش', 'en' => 'Unfurnished'],
                ],
                'label' => ['ar' => 'الأثاث', 'en' => 'Furnished'],
            ],
            [
                'key' => 'parking',
                'type' => 'boolean',
                'required' => false,
                'label' => ['ar' => 'موقف سيارات', 'en' => 'Parking'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function apartmentsFilters(): array
    {
        return [
            [
                'key' => 'bedrooms',
                'type' => 'range',
                'options' => null,
                'label' => ['ar' => 'غرف النوم', 'en' => 'Bedrooms'],
            ],
            [
                'key' => 'bathrooms',
                'type' => 'range',
                'options' => null,
                'label' => ['ar' => 'الحمامات', 'en' => 'Bathrooms'],
            ],
            [
                'key' => 'furnished',
                'type' => 'select',
                'options' => ['furnished', 'semi_furnished', 'unfurnished'],
                'label' => ['ar' => 'الأثاث', 'en' => 'Furnished'],
            ],
            [
                'key' => 'parking',
                'type' => 'boolean',
                'options' => null,
                'label' => ['ar' => 'موقف سيارات', 'en' => 'Parking'],
            ],
            [
                'key' => 'price',
                'type' => 'range',
                'options' => null,
                'label' => ['ar' => 'السعر', 'en' => 'Price'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mobilePhonesFields(): array
    {
        return [
            [
                'key' => 'brand',
                'type' => 'select',
                'required' => true,
                'show_in_card' => true,
                'options' => ['Apple', 'Samsung', 'Huawei', 'Xiaomi', 'Google', 'OnePlus', 'Other'],
                'option_labels' => ['Other' => ['ar' => 'أخرى', 'en' => 'Other']],
                'label' => ['ar' => 'الماركة', 'en' => 'Brand'],
            ],
            [
                'key' => 'storage_gb',
                'type' => 'select',
                'required' => true,
                'show_in_card' => true,
                'options' => ['64', '128', '256', '512', '1024'],
                'label' => ['ar' => 'السعة (جيجا)', 'en' => 'Storage (GB)'],
            ],
            [
                'key' => 'condition',
                'type' => 'select',
                'required' => true,
                'options' => ['new', 'like_new', 'good', 'fair'],
                'option_labels' => [
                    'new' => ['ar' => 'جديد', 'en' => 'New'],
                    'like_new' => ['ar' => 'شبه جديد', 'en' => 'Like new'],
                    'good' => ['ar' => 'جيد', 'en' => 'Good'],
                    'fair' => ['ar' => 'مقبول', 'en' => 'Fair'],
                ],
                'label' => ['ar' => 'الحالة', 'en' => 'Condition'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mobilePhonesFilters(): array
    {
        return [
            [
                'key' => 'brand',
                'type' => 'select',
                'options' => ['Apple', 'Samsung', 'Huawei', 'Xiaomi', 'Google', 'OnePlus', 'Other'],
                'label' => ['ar' => 'الماركة', 'en' => 'Brand'],
            ],
            [
                'key' => 'storage_gb',
                'type' => 'select',
                'options' => ['64', '128', '256', '512', '1024'],
                'label' => ['ar' => 'السعة (جيجا)', 'en' => 'Storage (GB)'],
            ],
            [
                'key' => 'condition',
                'type' => 'select',
                'options' => ['new', 'like_new', 'good', 'fair'],
                'label' => ['ar' => 'الحالة', 'en' => 'Condition'],
            ],
            [
                'key' => 'price',
                'type' => 'range',
                'options' => null,
                'label' => ['ar' => 'السعر', 'en' => 'Price'],
            ],
        ];
    }
}
