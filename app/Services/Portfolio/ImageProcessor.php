<?php

namespace App\Services\Portfolio;

use Dom\HTMLDocument;
use GdImage;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class ImageProcessor
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function prepare(array $content): array
    {
        $portrait = $content['profile']['portrait'];
        $portraitRatio = ($portrait['landscape'] ?? false) ? 16 / 9 : 3 / 4;
        $content['profile']['portrait']['asset'] = $this->process($portrait, $portraitRatio, [320, 480, 640, 960, 1280, 1600], 'portrait');

        foreach ($content['projects'] as $index => $project) {
            $content['projects'][$index]['image']['asset'] = $this->process($project['image'], 3 / 2, [480, 800, 1200], 'project');
            $content['projects'][$index]['html'] = $this->prepareMarkdownImages($project['html']);
        }

        foreach (['certifications', 'leadership', 'education'] as $section) {
            foreach ($content[$section]['entries'] as $index => $entry) {
                $badge = $entry['badge'] ?? null;

                if (is_array($badge)) {
                    $badge['asset'] = $this->process($badge, 1, [96, 192], 'badge');
                }

                $content[$section]['entries'][$index]['badge'] = $badge;
            }
        }

        return $content;
    }

    private function prepareMarkdownImages(string $html): string
    {
        if (! str_contains($html, '<img')) {
            return $html;
        }

        $document = HTMLDocument::createFromString('<!doctype html><html><head><title>Project</title></head><body>'.$html.'</body></html>', overrideEncoding: 'UTF-8');

        foreach ($document->querySelectorAll('img') as $image) {
            $asset = $this->process(['source' => $image->getAttribute('src')], 3 / 2, [480, 800, 1200], 'body');

            if ($asset === null) {
                throw new RuntimeException('A Markdown image needs a source file.');
            }

            foreach ($asset as $attribute => $value) {
                $image->setAttribute($attribute, (string) $value);
            }

            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');
            $image->setAttribute('sizes', '(min-width: 900px) 720px, calc(100vw - 48px)');
        }

        $result = '';

        foreach ($document->querySelector('body')->childNodes as $node) {
            $result .= $document->saveHtml($node);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $image
     * @param  list<int>  $sizes
     * @return array{src: string, srcset: string, width: int, height: int}|null
     */
    private function process(array $image, float $ratio, array $sizes, string $kind): ?array
    {
        if (! is_string($image['source'] ?? null) || $image['source'] === '') {
            return null;
        }

        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Image processing requires PHP GD with WebP support.');
        }

        $root = realpath(config()->string('portfolio.content_directory').'/assets');
        $path = $root === false ? false : realpath($root.'/'.$image['source']);

        if ($root === false || $path === false || ! str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $root), '/').'/')) {
            throw new RuntimeException('Image sources must stay inside content/assets/.');
        }

        $dimensions = @getimagesize($path);

        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 24000000) {
            throw new RuntimeException('Use a readable image no larger than 24 megapixels.');
        }

        $source = $this->files->get($path);
        $focalPoint = $image['focal_point'] ?? [0.5, 0.5];
        $hash = substr(hash('sha256', $source.json_encode([$ratio, $focalPoint, 86], JSON_THROW_ON_ERROR)), 0, 16);
        $bitmap = @imagecreatefromstring($source);

        if ($bitmap === false) {
            throw new RuntimeException('The image could not be decoded. Save it again as JPEG, PNG, or WebP.');
        }

        if ($dimensions[2] === IMAGETYPE_JPEG) {
            if (! function_exists('exif_read_data')) {
                throw new RuntimeException('Enable PHP EXIF to orient JPEG images before removing metadata.');
            }

            $metadata = @exif_read_data($path);
            $bitmap = $this->orient($bitmap, (int) ($metadata['Orientation'] ?? 1));
        }

        if ($kind === 'badge') {
            $trimmed = imagecropauto($bitmap, IMG_CROP_TRANSPARENT);

            if ($trimmed !== false) {
                $bitmap = $trimmed;
            }
        }

        $cropWidth = $kind === 'badge' ? imagesx($bitmap) : min(imagesx($bitmap), imagesy($bitmap) * $ratio);
        $cropHeight = $kind === 'badge' ? imagesy($bitmap) : $cropWidth / $ratio;
        $sourceX = $kind === 'badge' ? 0 : (int) round((imagesx($bitmap) - $cropWidth) * $focalPoint[0]);
        $sourceY = $kind === 'badge' ? 0 : (int) round((imagesy($bitmap) - $cropHeight) * $focalPoint[1]);
        $maximumWidth = $kind === 'badge' ? max($cropWidth, $cropHeight) : $cropWidth;
        $widths = array_values(array_unique(array_map(fn (int $width): int => min($width, max(1, (int) floor($maximumWidth))), $sizes)));
        $directory = public_path('media');

        if (is_link($directory)) {
            throw new RuntimeException('The generated media directory cannot be a symlink.');
        }

        $this->files->ensureDirectoryExists($directory);
        $publicRoot = realpath(public_path());
        $mediaRoot = realpath($directory);

        if ($publicRoot === false || $mediaRoot === false || ! str_starts_with(str_replace('\\', '/', $mediaRoot), rtrim(str_replace('\\', '/', $publicRoot), '/').'/')) {
            throw new RuntimeException('Generated images must stay inside the public directory.');
        }

        $sources = [];

        foreach ($widths as $width) {
            $width = max(1, $width);
            $height = max(1, (int) round($width / $ratio));
            $filename = "{$kind}-{$hash}-{$width}.webp";
            $destination = $directory.'/'.$filename;

            if (is_link($destination)) {
                throw new RuntimeException('A generated image cannot be a symlink.');
            }

            if (! $this->files->isFile($destination)) {
                $resized = imagecreatetruecolor($width, $height);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);

                if ($transparent === false) {
                    throw new RuntimeException('Could not allocate an image transparency colour.');
                }

                imagefill($resized, 0, 0, $transparent);
                $scale = $kind === 'badge' ? min($width / $cropWidth, $height / $cropHeight) : 1;
                $targetWidth = $kind === 'badge' ? max(1, (int) round($cropWidth * $scale)) : $width;
                $targetHeight = $kind === 'badge' ? max(1, (int) round($cropHeight * $scale)) : $height;
                $targetX = (int) floor(($width - $targetWidth) / 2);
                $targetY = (int) floor(($height - $targetHeight) / 2);
                imagecopyresampled($resized, $bitmap, $targetX, $targetY, $sourceX, $sourceY, $targetWidth, $targetHeight, (int) round($cropWidth), (int) round($cropHeight));

                if (! imagewebp($resized, $destination, 86)) {
                    throw new RuntimeException('Could not save a processed WebP image. Check the public/media directory.');
                }
            }

            $this->assertCleanWebp($destination);
            $sources[] = '/media/'.$filename.' '.$width.'w';
        }

        $width = max(1, max($widths ?: [1]));

        return [
            'src' => '/media/'.$kind.'-'.$hash.'-'.$width.'.webp',
            'srcset' => implode(', ', $sources),
            'width' => $width,
            'height' => max(1, (int) round($width / $ratio)),
        ];
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        $rotation = match ($orientation) {
            3 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($rotation !== 0) {
            $rotated = imagerotate($image, $rotation, 0);

            if ($rotated === false) {
                throw new RuntimeException('Could not orient the source image.');
            }

            $image = $rotated;
        }

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }

    public function assertCleanWebp(string $path): void
    {
        $contents = $this->files->get($path);
        $dimensions = @getimagesize($path);

        if ($dimensions === false || $dimensions[2] !== IMAGETYPE_WEBP || substr($contents, 0, 4) !== 'RIFF' || substr($contents, 8, 4) !== 'WEBP') {
            throw new RuntimeException('A generated image is not a valid WebP file. Remove it and rebuild.');
        }

        $position = 12;

        while ($position + 8 <= strlen($contents)) {
            $chunk = substr($contents, $position, 4);
            $length = unpack('Vsize', substr($contents, $position + 4, 4));

            if ($length === false || $position + 8 + $length['size'] > strlen($contents)) {
                throw new RuntimeException('A generated WebP image is truncated. Remove it and rebuild.');
            }

            if (in_array($chunk, ['EXIF', 'XMP ', 'ICCP'], true)) {
                throw new RuntimeException('A generated image contains metadata. Remove it and rebuild to strip metadata.');
            }

            $position += 8 + $length['size'] + ($length['size'] % 2);
        }
    }
}
