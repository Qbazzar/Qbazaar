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

it('round-trips everything the rich-text editor can produce', function (): void {
    $html = '<h2 style="text-align: center">عنوان</h2><h3>sub</h3>'
        . '<p style="text-align: end"><strong>b</strong><em>i</em><u>u</u><s>s</s></p>'
        . '<p style="text-align: justify">j</p><ul><li><p>one</p></li></ul><ol><li>two</li></ol>'
        . '<blockquote><p>q</p></blockquote><hr>'
        . '<a href="https://x.qa" target="_blank" rel="noopener noreferrer nofollow">x</a>';

    expect((new HtmlSanitizer)->sanitize($html))->toBe($html);
});

it('keeps only a plain text-align declaration in style', function (string $input, string $expected): void {
    expect((new HtmlSanitizer)->sanitize($input))->toBe($expected);
})->with([
    'alignment with extras' => ['<p style="text-align: center; background:url(javascript:x)">a</p>', '<p>a</p>'],
    'expression value' => ['<p style="text-align: expression(alert(1))">a</p>', '<p>a</p>'],
    'non-block tag' => ['<span style="text-align: center">a</span>', '<span>a</span>'],
    'uppercase value is normalised' => ['<p style="TEXT-ALIGN:CENTER;">a</p>', '<p style="text-align: center">a</p>'],
]);

it('rejects style smuggling around the text-align allowance', function (string $input, string $expected): void {
    expect((new HtmlSanitizer)->sanitize($input))->toBe($expected);
})->with([
    '!important' => ['<p style="text-align: center !important">a</p>', '<p>a</p>'],
    'comment inside value' => ['<p style="text-align:/**/center">a</p>', '<p>a</p>'],
    'css escape' => ['<p style="text-align:\63enter">a</p>', '<p>a</p>'],
    'doubled semicolon' => ['<p style="text-align:center;;">a</p>', '<p>a</p>'],
    'second declaration' => ['<p style="text-align:center;color:red">a</p>', '<p>a</p>'],
    'duplicate style attributes' => ['<p style="text-align:center" style="background:url(x)">a</p>', '<p style="text-align: center">a</p>'],
    'style on a link' => ['<a href="/x" style="text-align:center">a</a>', '<a href="/x">a</a>'],
    'uppercase tag and attribute' => ['<P STYLE="text-align:center">a</P>', '<p style="text-align: center">a</p>'],
    'table cell alignment' => ['<table><tbody><tr><td style="text-align:end">a</td></tr></tbody></table>', '<table><tbody><tr><td style="text-align: end">a</td></tr></tbody></table>'],
    'target self is forced to blank' => ['<a href="https://x.qa" target="_self">a</a>', '<a href="https://x.qa" target="_blank" rel="noopener noreferrer nofollow">a</a>'],
    'target top is forced to blank' => ['<a href="https://x.qa" target="_top" rel="opener">a</a>', '<a href="https://x.qa" target="_blank" rel="noopener noreferrer nofollow">a</a>'],
]);
