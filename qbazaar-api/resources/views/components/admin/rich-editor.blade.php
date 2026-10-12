@props([
    'name',
    'label' => null,
    'id' => null,
    'value' => null,
    'rows' => 10,
    'hint' => null,
    'required' => false,
    'dir' => 'rtl',
    'lang' => null,
])

@php
    $id ??= str_replace(['[]', '[', ']'], ['', '-', ''], $name);
    $lang ??= $dir === 'rtl' ? 'ar' : 'en';
    $error = ($errors ?? null)?->first(str_replace(['[]', '[', ']'], ['', '.', ''], $name));
    $describedBy = trim(($hint ? "{$id}-hint " : '') . ($error ? "{$id}-error" : ''));

    // Each button is labelled in Arabic with the English name beside it, so the
    // toolbar reads correctly whichever language the editor is working in.
    $labels = [
        'toolbar' => 'أدوات التنسيق (Formatting)',
        'undo' => 'تراجع (Undo)', 'redo' => 'إعادة (Redo)',
        'paragraph' => 'فقرة (Paragraph)', 'h2' => 'عنوان رئيسي (Heading 2)', 'h3' => 'عنوان فرعي (Heading 3)',
        'bold' => 'عريض (Bold)', 'italic' => 'مائل (Italic)', 'underline' => 'تسطير (Underline)', 'strike' => 'يتوسطه خط (Strikethrough)',
        'bulletList' => 'قائمة نقطية (Bullet list)', 'orderedList' => 'قائمة رقمية (Numbered list)',
        'blockquote' => 'اقتباس (Quote)', 'hr' => 'خط فاصل (Horizontal rule)',
        'alignStart' => 'محاذاة للبداية (Align start)', 'alignCenter' => 'توسيط (Center)', 'alignEnd' => 'محاذاة للنهاية (Align end)', 'alignJustify' => 'ضبط (Justify)',
        'link' => 'رابط (Link)', 'clear' => 'مسح التنسيق (Clear formatting)', 'source' => 'عرض المصدر HTML (Source)',
        'linkUrl' => 'عنوان الرابط (Link URL)', 'linkApply' => 'تطبيق (Apply)', 'linkRemove' => 'إزالة الرابط (Remove link)', 'linkCancel' => 'إلغاء (Cancel)',
        'placeholder' => '',
    ];
@endphp

@once
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    @vite('resources/js/admin/rich-editor.js')
@endonce

<x-admin.field :for="$id" :label="$label" :hint="$hint" :error="$error" :required="$required" class="{{ $attributes->get('class') }}">
    <div data-rich-editor data-dir="{{ $dir }}" data-lang="{{ $lang }}" data-labels="{{ json_encode($labels, JSON_UNESCAPED_UNICODE) }}" data-invalid="{{ $error ? '1' : '0' }}">
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            dir="{{ $dir }}"
            lang="{{ $lang }}"
            @required($required)
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            @class([
                'w-full rounded-xl border bg-cream-50 px-4 py-2.5 text-sm text-ink-900 outline-hidden transition focus:border-coral-600 focus:ring-2 focus:ring-coral-600/25',
                'border-red-600' => $error,
                'border-ink-200' => ! $error,
            ])
        >{{ $value }}</textarea>
    </div>
</x-admin.field>
