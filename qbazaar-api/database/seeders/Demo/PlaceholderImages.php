<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use GdImage;
use RuntimeException;

/**
 * Draws listing photos locally with GD: the category colour and the listing
 * title. No network, no stock images, and every file is a real JPEG the
 * media pipeline can convert, hash and serve.
 */
final class PlaceholderImages
{
    private const array SIZES = [[960, 720], [1024, 768], [800, 800], [720, 960], [1024, 576]];

    private const int BUILTIN_FONT = 5;

    private const int GLYPH_WIDTH = 9;

    private const int GLYPH_HEIGHT = 15;

    private const int LINE_LENGTH = 24;

    /** @var list<string> */
    private array $files = [];

    public function __construct(
        private readonly DemoRandom $random,
    ) {}

    /**
     * @param array{int, int}|null $size width and height; a random photo size when null
     * @return string path of a temporary JPEG, removed by {@see cleanUp()}
     */
    public function draw(string $hexColor, string $caption, ?array $size = null): string
    {
        return $this->series($hexColor, $caption, 1, $size)[0];
    }

    /**
     * Photos of one listing share their background and caption, which are
     * drawn once and saved as separate files.
     *
     * @param array{int, int}|null $size width and height; a random photo size when null
     * @return list<string> paths of temporary JPEGs, removed by {@see cleanUp()}
     */
    public function series(string $hexColor, string $caption, int $count, ?array $size = null): array
    {
        [$width, $height] = $size ?? $this->random->pick(self::SIZES);
        [$red, $green, $blue] = $this->rgb($hexColor);
        $base = $this->background($width, $height, $red, $green, $blue, $caption);
        $paths = [];

        for ($copy = 0; $copy < $count; $copy++) {
            $paths[] = $this->save($base);
        }

        return $paths;
    }

    public function cleanUp(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $this->files = [];
    }

    private function background(int $width, int $height, int $red, int $green, int $blue, string $caption): GdImage
    {
        $image = $this->canvas($width, $height);
        imagefill($image, 0, 0, $this->color($image, $red, $green, $blue));
        imagefilledrectangle($image, 0, (int) ($height * 0.62), $width, $height, $this->color($image, (int) ($red * 0.75), (int) ($green * 0.75), (int) ($blue * 0.75)));
        $this->caption($image, $caption, $width, $height);

        return $image;
    }

    private function caption(GdImage $image, string $caption, int $width, int $height): void
    {
        $lines = explode("\n", wordwrap($this->ascii($caption), self::LINE_LENGTH, "\n", true));
        $scale = max(2, intdiv($width, self::LINE_LENGTH * self::GLYPH_WIDTH + 40));
        $lineHeight = self::GLYPH_HEIGHT * $scale + 8;
        $top = (int) ($height * 0.81) - intdiv(count($lines) * $lineHeight, 2);
        $white = $this->color($image, 255, 255, 255);

        foreach (array_slice($lines, 0, 3) as $index => $line) {
            $this->text($image, $line, intdiv($width, 2), $top + $index * $lineHeight + intdiv($lineHeight, 2), $scale, $white);
        }
    }

    /**
     * GD's built-in font is tiny and needs no font file, so each line is
     * drawn small and scaled up around its centre point; nearest-neighbour
     * scaling keeps the pixel font crisp and costs a fraction of resampling.
     */
    private function text(GdImage $image, string $text, int $centerX, int $centerY, int $scale, int $color): void
    {
        $width = max(1, strlen($text) * self::GLYPH_WIDTH);
        $stamp = $this->canvas($width, self::GLYPH_HEIGHT);
        $transparent = imagecolorallocatealpha($stamp, 0, 0, 0, 127);

        imagealphablending($stamp, false);
        imagefill($stamp, 0, 0, $transparent === false ? 0 : $transparent);
        imagealphablending($stamp, true);
        imagestring($stamp, self::BUILTIN_FONT, 0, 0, $text, $this->color($stamp, ...$this->rgbOf($image, $color)));

        $scaledWidth = $width * $scale;
        $scaledHeight = self::GLYPH_HEIGHT * $scale;
        imagecopyresized($image, $stamp, $centerX - intdiv($scaledWidth, 2), $centerY - intdiv($scaledHeight, 2), 0, 0, $scaledWidth, $scaledHeight, $width, self::GLYPH_HEIGHT);
    }

    private function save(GdImage $image): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qb-demo-');

        if ($path === false || ! imagejpeg($image, $path, 82)) {
            throw new RuntimeException('Could not write a placeholder image to the temp directory.');
        }

        $this->files[] = $path;

        return $path;
    }

    private function canvas(int $width, int $height): GdImage
    {
        return imagecreatetruecolor(max(1, $width), max(1, $height)) ?: throw new RuntimeException('GD could not allocate an image.');
    }

    private function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $this->channel($red), $this->channel($green), $this->channel($blue));

        return $color === false ? 0 : $color;
    }

    /**
     * @return int<0, 255>
     */
    private function channel(int $value): int
    {
        return max(0, min(255, $value));
    }

    /**
     * @return array{int, int, int}
     */
    private function rgbOf(GdImage $image, int $color): array
    {
        $parts = imagecolorsforindex($image, $color);

        return [$parts['red'], $parts['green'], $parts['blue']];
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }

    /**
     * The built-in font only knows Latin-1, so captions are reduced to it.
     */
    private function ascii(string $text): string
    {
        $plain = trim((string) preg_replace('/[^\x20-\x7E]+/', ' ', $text));

        return (string) preg_replace('/\s+/', ' ', $plain);
    }
}
