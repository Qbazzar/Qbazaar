<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Catalog;

/**
 * Names and businesses that look like Qatar's mix of citizens and residents.
 * Every person has an Arabic and an English spelling; the account's
 * language decides which one is stored.
 */
final class People
{
    /** @var list<array{ar: string, en: string}> */
    public const array FIRST_NAMES = [
        ['ar' => 'أحمد', 'en' => 'Ahmed'], ['ar' => 'محمد', 'en' => 'Mohammed'], ['ar' => 'خالد', 'en' => 'Khalid'],
        ['ar' => 'حمد', 'en' => 'Hamad'], ['ar' => 'جاسم', 'en' => 'Jassim'], ['ar' => 'فيصل', 'en' => 'Faisal'],
        ['ar' => 'سعد', 'en' => 'Saad'], ['ar' => 'يوسف', 'en' => 'Yousef'], ['ar' => 'عبدالله', 'en' => 'Abdullah'],
        ['ar' => 'ناصر', 'en' => 'Nasser'], ['ar' => 'طارق', 'en' => 'Tareq'], ['ar' => 'عمر', 'en' => 'Omar'],
        ['ar' => 'فاطمة', 'en' => 'Fatima'], ['ar' => 'مريم', 'en' => 'Mariam'], ['ar' => 'نورة', 'en' => 'Noora'],
        ['ar' => 'عائشة', 'en' => 'Aisha'], ['ar' => 'حصة', 'en' => 'Hessa'], ['ar' => 'لطيفة', 'en' => 'Latifa'],
        ['ar' => 'ريم', 'en' => 'Reem'], ['ar' => 'شيخة', 'en' => 'Sheikha'], ['ar' => 'دانة', 'en' => 'Dana'],
        ['ar' => 'هند', 'en' => 'Hind'], ['ar' => 'سارة', 'en' => 'Sara'], ['ar' => 'منى', 'en' => 'Mona'],
    ];

    /** @var list<array{ar: string, en: string}> */
    public const array FAMILY_NAMES = [
        ['ar' => 'الكواري', 'en' => 'Al Kuwari'], ['ar' => 'المري', 'en' => 'Al Marri'], ['ar' => 'السليطي', 'en' => 'Al Sulaiti'],
        ['ar' => 'العطية', 'en' => 'Al Attiyah'], ['ar' => 'الهاجري', 'en' => 'Al Hajri'], ['ar' => 'النعيمي', 'en' => 'Al Naimi'],
        ['ar' => 'المهندي', 'en' => 'Al Mohannadi'], ['ar' => 'الأنصاري', 'en' => 'Al Ansari'], ['ar' => 'الجابر', 'en' => 'Al Jaber'],
        ['ar' => 'البوعينين', 'en' => 'Al Buainain'], ['ar' => 'الخليفي', 'en' => 'Al Khulaifi'], ['ar' => 'الملا', 'en' => 'Al Mulla'],
        ['ar' => 'حسن', 'en' => 'Hassan'], ['ar' => 'إبراهيم', 'en' => 'Ibrahim'], ['ar' => 'منصور', 'en' => 'Mansour'],
        ['ar' => 'ناير', 'en' => 'Nair'], ['ar' => 'سانتوس', 'en' => 'Santos'], ['ar' => 'خان', 'en' => 'Khan'],
    ];

    /** @var list<array{ar: string, en: string, about_ar: string, about_en: string}> */
    public const array BUSINESSES = [
        ['ar' => 'معرض الدوحة للسيارات', 'en' => 'Doha Auto Gallery', 'about_ar' => 'سيارات مستعملة مفحوصة بضمان ستة أشهر وتمويل عبر البنوك المحلية.', 'about_en' => 'Inspected used cars with a six-month warranty and local bank financing.'],
        ['ar' => 'لؤلؤة العقارية', 'en' => 'Pearl Properties', 'about_ar' => 'شقق وفلل للإيجار والبيع في اللؤلؤة ولوسيل والخليج الغربي.', 'about_en' => 'Apartments and villas for rent and sale in The Pearl, Lusail and West Bay.'],
        ['ar' => 'تك زون قطر', 'en' => 'TechZone Qatar', 'about_ar' => 'هواتف ولابتوبات أصلية مع فاتورة وضمان الوكيل.', 'about_en' => 'Genuine phones and laptops with invoice and dealer warranty.'],
        ['ar' => 'بيت الأثاث', 'en' => 'Furniture House', 'about_ar' => 'أثاث منزلي ومكتبي جديد ومستعمل مع توصيل وتركيب داخل الدوحة.', 'about_en' => 'New and used home and office furniture with delivery and assembly in Doha.'],
        ['ar' => 'أزياء الريان', 'en' => 'Rayyan Fashion', 'about_ar' => 'عبايات وملابس أطفال وإكسسوارات بأسعار المحل.', 'about_en' => 'Abayas, kidswear and accessories at shop prices.'],
        ['ar' => 'خدمات الوكرة المنزلية', 'en' => 'Wakra Home Services', 'about_ar' => 'تنظيف وسباكة وكهرباء بفنيين مرخصين وزيارة في نفس اليوم.', 'about_en' => 'Cleaning, plumbing and electrical work by licensed technicians, same-day visits.'],
        ['ar' => 'عالم الحيوانات الأليفة', 'en' => 'Pet World Lusail', 'about_ar' => 'مستلزمات وأطعمة الحيوانات الأليفة وقطط مطعمة مع شهادات.', 'about_en' => 'Pet food, supplies and vaccinated kittens with papers.'],
        ['ar' => 'الخور للمعدات', 'en' => 'Al Khor Equipment', 'about_ar' => 'معدات مطاعم وصناعية للبيع والإيجار مع صيانة.', 'about_en' => 'Restaurant and industrial equipment for sale and rent, with maintenance.'],
        ['ar' => 'ستوديو مشيرب للتصوير', 'en' => 'Msheireb Photo Studio', 'about_ar' => 'تصوير مناسبات ومنتجات وبورتريه داخل الاستوديو أو في موقعك.', 'about_en' => 'Event, product and portrait photography in the studio or on location.'],
        ['ar' => 'دراجات أسباير', 'en' => 'Aspire Bikes', 'about_ar' => 'دراجات هوائية وإكسسوارات وصيانة دورية.', 'about_en' => 'Bicycles, accessories and regular servicing.'],
    ];

    /** Valid Qatari mobile prefixes; numbers are 8 digits after +974. */
    public const array MOBILE_PREFIXES = ['3', '5', '6', '7'];
}
