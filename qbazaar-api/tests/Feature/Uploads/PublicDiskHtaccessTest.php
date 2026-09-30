<?php

declare(strict_types=1);

it('ships an .htaccess that keeps the public disk static', function (): void {
    $htaccess = file_get_contents(storage_path('app/public/.htaccess'));

    expect($htaccess)
        ->toContain('SetHandler None')
        ->toContain('<FilesMatch "(?i)\.(php[0-9]*|phtml|phar|phps|html?|shtml|svgz?)$">')
        ->toContain('Require all denied');
});
