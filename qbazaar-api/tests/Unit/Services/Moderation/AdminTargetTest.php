<?php

declare(strict_types=1);

use App\Services\Admin\Inbox\AdminTarget;

beforeEach(function (): void {
    config(['app.url' => 'https://api.qbazaar.test']);
});

it('keeps panel links on the same host as a relative path', function (?string $url, string $expected): void {
    expect(AdminTarget::from($url))->toBe($expected);
})->with([
    'absolute, same host' => ['https://api.qbazaar.test/admin/reports/01abc', '/admin/reports/01abc'],
    'host in another case' => ['https://API.qbazaar.test/admin', '/admin'],
    'relative' => ['/admin/finance/disputes?status=open#top', '/admin/finance/disputes?status=open#top'],
]);

it('drops anything that would leave the panel', function (?string $url): void {
    expect(AdminTarget::from($url))->toBeNull();
})->with([
    'nothing' => [null],
    'empty' => [''],
    'another host' => ['https://evil.example/admin/reports/1'],
    'protocol-relative' => ['//evil.example/admin'],
    'script scheme' => ['javascript:alert(1)'],
    'outside /admin' => ['https://api.qbazaar.test/ads/1'],
    'a lookalike prefix' => ['/administrator/x'],
    'path traversal' => ['/admin/../ads/1'],
    'double slash' => ['/admin//evil.example'],
    'backslash' => ['/admin/\evil.example'],
]);
