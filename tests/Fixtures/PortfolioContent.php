<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

function portfolioContentFixture(): string
{
    $fixture = storage_path('framework/testing/content-'.Str::uuid());
    File::ensureDirectoryExists($fixture);
    File::copyDirectory(base_path('content'), $fixture.'/content');
    config(['portfolio.content_directory' => $fixture.'/content']);
    test()->beforeApplicationDestroyed(function () use ($fixture): void {
        File::deleteDirectory($fixture);
    });

    return $fixture.'/content';
}

/** @param array<string, mixed> $changes */
function writePortfolioProject(string $directory, string $slug, array $changes = [], string $body = '## Outcome'."\n\n".'A **working** project.'): void
{
    $data = array_replace([
        'title' => 'New build', 'kind' => 'project', 'published' => true, 'order' => 1,
        'date' => '2026-11', 'context' => 'Test fixture', 'outcome' => 'Built a prototype.',
        'role' => 'Designer and builder.', 'tech' => ['Arduino'], 'tech_notes' => null,
        'image' => ['source' => null, 'alt' => null], 'links' => [],
    ], $changes);
    File::put($directory.'/projects/'.$slug.'.md', "---\n".Yaml::dump($data, 6, 2)."---\n\n".$body."\n");
}

function createPortfolioImage(string $directory, int $width = 600, int $height = 800, bool $jpeg = false, int $orientation = 1): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $colours = [
        imagecolorallocate($image, 240, 20, 20), imagecolorallocate($image, 20, 220, 20),
        imagecolorallocate($image, 20, 20, 240), imagecolorallocate($image, 240, 220, 20),
    ];
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), intdiv($height, 2), $colours[0]);
    imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, intdiv($height, 2), $colours[1]);
    imagefilledrectangle($image, 0, intdiv($height, 2), intdiv($width, 2), $height - 1, $colours[2]);
    imagefilledrectangle($image, intdiv($width, 2), intdiv($height, 2), $width - 1, $height - 1, $colours[3]);
    $path = $directory.'/assets/test-image.'.($jpeg ? 'jpg' : 'png');

    if ($jpeg) {
        imagejpeg($image, $path, 95);
        $exif = "Exif\0\0II".pack('vVv', 42, 8, 1).pack('vvVv', 0x0112, 3, 1, $orientation)."\0\0".pack('V', 0);
        $xmp = "http://ns.adobe.com/xap/1.0/\0<xmp>TEST-LOCATION-METADATA</xmp>";
        $contents = File::get($path);
        File::put($path, substr($contents, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif."\xff\xe1".pack('n', strlen($xmp) + 2).$xmp.substr($contents, 2));
    } else {
        imagefilledrectangle($image, 0, 0, 10, 10, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);
    }

    return $path;
}
