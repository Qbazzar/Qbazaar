<?php

declare(strict_types=1);

use App\Services\Content\HtmlSanitizer;

dataset('unsafe markup', [
    'script tag' => ['<p>Hi</p><script>alert(1)</script>', '<p>Hi</p>'],
    'event handler' => ['<p onclick="alert(1)">Hi</p>', '<p>Hi</p>'],
    'javascript link' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
    'obfuscated scheme' => ["<a href=\"java\tscript:alert(1)\">x</a>", '<a>x</a>'],
    'data image' => ['<img src="data:text/html;base64,PHNjcmlwdD4=" alt="a">', '<img alt="a">'],
    'iframe' => ['<iframe src="https://evil.example"></iframe><p>ok</p>', '<p>ok</p>'],
    'svg payload' => ['<svg><script>alert(1)</script></svg>text', 'text'],
    'style attribute' => ['<span style="background:url(x)">t</span>', '<span>t</span>'],
    'comment' => ['<p>a<!-- hidden --></p>', '<p>a</p>'],
    'unknown tag keeps text' => ['<custom-el><b>bold</b></custom-el>', '<b>bold</b>'],
    'unclosed script breakout' => ['<p>a</p><script', '<p>a</p>'],
]);

it('strips unsafe markup', function (string $input, string $expected): void {
    expect((new HtmlSanitizer)->sanitize($input))->toBe($expected);
})->with('unsafe markup');

it('keeps the formatting allowlist intact', function (): void {
    $html = '<h2 dir="rtl">عنوان</h2><p><strong>a</strong> <em>b</em></p><ul><li>one</li></ul>'
        . '<a href="https://qbazaar.qa/help" title="Help">help</a><a href="/p/terms">terms</a>'
        . '<a href="mailto:support@qbazaar.qa">mail</a><img src="https://cdn.qbazaar.qa/a.png" alt="a">';

    expect((new HtmlSanitizer)->sanitize($html))->toBe($html);
});

it('forces safe rel on links that open a new tab', function (): void {
    expect((new HtmlSanitizer)->sanitize('<a href="https://x.qa" target="_self">x</a>'))
        ->toBe('<a href="https://x.qa" target="_blank" rel="noopener noreferrer nofollow">x</a>');
});

it('returns an empty string for blank input', function (): void {
    expect((new HtmlSanitizer)->sanitize("  \n "))->toBe('');
});
