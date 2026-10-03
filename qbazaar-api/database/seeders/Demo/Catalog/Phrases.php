<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Catalog;

use App\Enums\ReportCategory;
use App\Enums\SupportTicketCategory;

/**
 * The words people type: chat lines, reviews, reports and support tickets,
 * each in Arabic and English.
 */
final class Phrases
{
    /**
     * A chat alternates buyer and seller lines, starting with the buyer.
     *
     * @var array<string, list<list<string>>>
     */
    public const array CHATS = [
        'ar' => [
            ['السلام عليكم، الإعلان لسا متوفر؟', 'وعليكم السلام، نعم متوفر.', 'ممكن أعاينه اليوم بعد المغرب؟', 'أكيد، أرسل لك اللوكيشن.'],
            ['مرحبا، كم آخر سعر؟', 'السعر المكتوب نهائي تقريباً، فيه مجال بسيط.', 'تمام، بفكر وأرد عليك.'],
            ['هل في ضمان؟', 'نعم، باقي ضمان الوكيل ستة شهور.', 'ممتاز، الفاتورة موجودة؟', 'موجودة مع الكرتون.'],
            ['ممكن توصيل للوكرة؟', 'ممكن بإضافة 50 ريال.', 'تمام، متى تقدر توصل؟', 'بكرة الصبح إن شاء الله.'],
        ],
        'en' => [
            ['Hi, is this still available?', 'Yes, it is.', 'Can I see it this evening?', 'Sure, I will share the location.'],
            ['Hello, what is your best price?', 'The price is almost final, a little room to negotiate.', 'Okay, let me think about it.'],
            ['Any warranty left?', 'Yes, six months of dealer warranty.', 'Great, do you have the invoice?', 'Yes, with the original box.'],
            ['Can you deliver to Lusail?', 'Yes, for an extra 50 QAR.', 'Perfect, when could you deliver?', 'Tomorrow morning.'],
        ],
    ];

    /** @var array<string, list<string>> */
    public const array OFFER_NOTES = [
        'ar' => ['أدفع كاش وأستلم اليوم.', 'هذا أفضل سعر عندي.', 'ممكن نتفاهم؟'],
        'en' => ['Cash, and I can collect today.', 'This is my best price.', 'Can we meet halfway?'],
    ];

    /** @var array<string, list<string>> */
    public const array CANCELLATION_REASONS = [
        'ar' => ['غيرت رأيي، آسف.', 'البائع لم يرد على المواعيد.'],
        'en' => ['Changed my mind, sorry.', 'Could not agree on a pickup time.'],
    ];

    /** @var array<int, array<string, string>> */
    public const array REVIEWS = [
        5 => ['ar' => 'بائع محترم والغرض مثل الوصف تماماً. أنصح بالتعامل معه.', 'en' => 'Great seller, item exactly as described. Highly recommended.'],
        4 => ['ar' => 'تعامل جيد والتسليم في الموعد.', 'en' => 'Smooth deal and on-time handover.'],
        3 => ['ar' => 'الغرض جيد لكن التواصل كان بطيء.', 'en' => 'Item is fine but replies were slow.'],
        2 => ['ar' => 'الحالة أقل من الوصف.', 'en' => 'Condition was worse than described.'],
    ];

    /** @var array<string, array{ar: string, en: string}> */
    public const array REPORTS = [
        ReportCategory::SPAM->value => ['ar' => 'نفس الإعلان منشور أكثر من مرة.', 'en' => 'The same ad is posted again and again.'],
        ReportCategory::FRAUD->value => ['ar' => 'البائع يطلب تحويل عربون قبل المعاينة.', 'en' => 'Seller asks for a deposit transfer before any viewing.'],
        ReportCategory::INAPPROPRIATE->value => ['ar' => 'الصور غير مناسبة.', 'en' => 'The photos are inappropriate.'],
        ReportCategory::OFFENSIVE->value => ['ar' => 'ألفاظ مسيئة في المحادثة.', 'en' => 'Abusive language in the chat.'],
        ReportCategory::DUPLICATE->value => ['ar' => 'الإعلان منسوخ من حساب آخر.', 'en' => 'This ad is copied from another account.'],
        ReportCategory::WRONG_CATEGORY->value => ['ar' => 'الإعلان في القسم الخطأ.', 'en' => 'Posted in the wrong category.'],
        ReportCategory::OTHER->value => ['ar' => 'السعر غير منطقي ويبدو وهمياً.', 'en' => 'The price looks fake.'],
    ];

    /** @var list<array{category: SupportTicketCategory, ar: array{0: string, 1: string}, en: array{0: string, 1: string}}> */
    public const array TICKETS = [
        ['category' => SupportTicketCategory::TECHNICAL, 'ar' => ['لم يصلني رمز التحقق', 'حاولت أكثر من مرة ولم تصل رسالة الرمز إلى رقمي.'], 'en' => ['Verification code not received', 'I tried several times and the SMS never arrived.']],
        ['category' => SupportTicketCategory::ABUSE, 'ar' => ['بائع يطلب تحويل مسبق', 'تواصلت مع بائع وطلب مني تحويل مبلغ قبل المعاينة.'], 'en' => ['Seller asking for prepayment', 'A seller asked me to transfer money before viewing.']],
        ['category' => SupportTicketCategory::GENERAL, 'ar' => ['كيف أغير اسم المتجر؟', 'أريد تعديل اسم حسابي التجاري.'], 'en' => ['How do I rename my shop?', 'I want to change my business account name.']],
        ['category' => SupportTicketCategory::FEEDBACK, 'ar' => ['اقتراح: فلتر حسب المسافة', 'سيكون رائعاً لو أقدر أبحث عن الإعلانات القريبة مني.'], 'en' => ['Suggestion: distance filter', 'It would be great to search for ads near me.']],
        ['category' => SupportTicketCategory::TECHNICAL, 'ar' => ['الصور لا تظهر في إعلاني', 'رفعت خمس صور لكن تظهر واحدة فقط.'], 'en' => ['Photos missing on my ad', 'I uploaded five photos but only one shows.']],
    ];

    /** @var array<string, list<string>> */
    public const array STAFF_REPLIES = [
        'ar' => ['أهلاً بك، نراجع طلبك الآن وسنعود إليك قريباً.', 'تم حل المشكلة من جهتنا، هل يمكنك المحاولة مرة أخرى؟'],
        'en' => ['Hi, we are looking into this and will get back to you shortly.', 'This is fixed on our side, could you try again?'],
    ];

    /** @var array<string, string> */
    public const array OWNER_FOLLOW_UPS = [
        'ar' => 'شكراً، جربت مرة ثانية واشتغل.',
        'en' => 'Thanks, it works now.',
    ];

    public const array REJECTION_NOTES = [
        'Photos do not show the actual item.',
        'Contact details in the description; please use chat.',
        'Price looks unrealistic for this category.',
    ];

    /** @var array<string, list<string>> */
    public const array SAVED_SEARCH_NAMES = [
        'ar' => ['سيارات عائلية', 'شقق قريبة من العمل', 'جوالات مستعملة', 'أثاث رخيص'],
        'en' => ['Family SUVs', 'Flats near work', 'Used phones', 'Cheap furniture'],
    ];
}
