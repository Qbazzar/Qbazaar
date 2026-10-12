<?php

declare(strict_types=1);

const ADMIN_FONT_VIEWS = [
    'resources/views/admin/layout.blade.php',
    'resources/views/admin/login.blade.php',
    'resources/views/components/admin/rich-editor.blade.php',
];

it('keeps Cairo and Google Fonts out of the admin views and styles', function (string $path) {
    $source = file_get_contents(base_path($path));

    expect($source)->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->not->toContain('Cairo');
})->with([...ADMIN_FONT_VIEWS, 'resources/css/app.css', 'resources/css/rich-editor.css']);

it('self-hosts Noto Kufi Arabic and Poppins with swap', function () {
    $css = file_get_contents(base_path('resources/css/app.css'));

    expect($css)->toContain("font-family: 'Noto Kufi Arabic'")
        ->toContain("font-family: 'Poppins'")
        ->toContain('font-display: swap')
        ->toContain('unicode-range: U+0020, U+00A0, U+0600-06FF')
        ->toContain("--font-sans: 'Noto Kufi Arabic', 'Poppins'");
    expect(base_path('resources/fonts/noto-kufi-arabic-variable.woff2'))->toBeFile();
    expect(base_path('resources/fonts/OFL-NotoKufiArabic.txt'))->toBeFile();
});

it('preloads the Arabic font on admin pages', function (string $path) {
    expect(file_get_contents(base_path($path)))->toContain('noto-kufi-arabic-variable.woff2');
})->with(['resources/views/admin/layout.blade.php', 'resources/views/admin/login.blade.php']);
