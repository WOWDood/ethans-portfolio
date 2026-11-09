<?php

use App\Services\Portfolio\ContentRepository;
use App\Services\Portfolio\ImageProcessor;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/../../../Fixtures/PortfolioContent.php';

test('produces responsive transparent WebP crops without upscaling or copying originals', function () {
    $directory = portfolioContentFixture();
    createPortfolioImage($directory);
    File::ensureDirectoryExists(dirname($directory).'/public');
    app()->usePublicPath(dirname($directory).'/public');
    $content = app(ContentRepository::class)->load();
    $content['profile']['portrait']['source'] = 'test-image.png';
    $content['profile']['portrait']['focal_point'] = [0.5, 0.5];
    unset($content['profile']['portrait']['landscape']);

    $prepared = app(ImageProcessor::class)->prepare($content);

    $asset = $prepared['profile']['portrait']['asset'];
    expect($asset['srcset'])->toContain('320w', '480w', '600w')->not->toContain('640w');
    expect([$asset['width'], $asset['height']])->toBe([600, 800]);
    expect(glob(public_path('media/portrait-*.webp')))->toHaveCount(3);
    $image = imagecreatefromwebp(public_path(ltrim($asset['src'], '/')));
    expect(imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'])->toBeGreaterThan(120);
    expect(glob(public_path('media/*.png')))->toBe([]);
});

test('preserves the wide composition of a landscape hero portrait', function () {
    $directory = portfolioContentFixture();
    createPortfolioImage($directory, 960, 540);
    File::ensureDirectoryExists(dirname($directory).'/public');
    app()->usePublicPath(dirname($directory).'/public');
    $content = app(ContentRepository::class)->load();
    $content['profile']['portrait']['source'] = 'test-image.png';
    $content['profile']['portrait']['landscape'] = true;

    $asset = app(ImageProcessor::class)->prepare($content)['profile']['portrait']['asset'];

    expect([$asset['width'], $asset['height']])->toBe([960, 540]);
    expect($asset['srcset'])->toContain('320w', '960w')->not->toContain('1280w');
});

test('corrects JPEG orientation before removing EXIF and location metadata', function (int $orientation, array $expectedColour) {
    $directory = portfolioContentFixture();
    $source = createPortfolioImage($directory, 120, 80, jpeg: true, orientation: $orientation);
    File::ensureDirectoryExists(dirname($directory).'/public');
    app()->usePublicPath(dirname($directory).'/public');
    $content = app(ContentRepository::class)->load();
    $content['profile']['portrait']['source'] = 'test-image.jpg';
    $content['profile']['portrait']['focal_point'] = [0.5, 0.5];
    unset($content['profile']['portrait']['landscape']);
    expect(exif_read_data($source)['Orientation'])->toBe($orientation);

    $asset = app(ImageProcessor::class)->prepare($content)['profile']['portrait']['asset'];

    $path = public_path(ltrim($asset['src'], '/'));
    $image = imagecreatefromwebp($path);
    $colour = imagecolorsforindex($image, imagecolorat($image, 5, 5));
    expect($colour['red'])->toBeBetween($expectedColour[0] - 25, $expectedColour[0] + 25);
    expect($colour['green'])->toBeBetween($expectedColour[1] - 25, $expectedColour[1] + 25);
    expect($colour['blue'])->toBeBetween($expectedColour[2] - 25, $expectedColour[2] + 25);
    expect(File::get($path))->not->toContain('TEST-LOCATION-METADATA', 'Exif', 'http://ns.adobe.com');
})->with([
    'normal' => [1, [240, 20, 20]],
    'mirrored' => [2, [20, 220, 20]],
    'upside down' => [3, [240, 220, 20]],
    'vertical flip' => [4, [20, 20, 240]],
    'transpose' => [5, [240, 20, 20]],
    'clockwise' => [6, [20, 20, 240]],
    'transverse' => [7, [240, 220, 20]],
    'counterclockwise' => [8, [20, 220, 20]],
]);

test('changes cached image URLs when the crop focal point changes', function () {
    $directory = portfolioContentFixture();
    createPortfolioImage($directory, 120, 240);
    File::ensureDirectoryExists(dirname($directory).'/public');
    app()->usePublicPath(dirname($directory).'/public');
    $content = app(ContentRepository::class)->load();
    $content['profile']['portrait']['source'] = 'test-image.png';
    $content['profile']['portrait']['focal_point'] = [0.5, 0];
    $processor = app(ImageProcessor::class);
    $top = $processor->prepare($content)['profile']['portrait']['asset'];
    $content['profile']['portrait']['focal_point'] = [0.5, 1];

    $bottom = $processor->prepare($content)['profile']['portrait']['asset'];

    expect($bottom['src'])->not->toBe($top['src']);
});

test('rejects metadata in an existing cached image', function () {
    $directory = portfolioContentFixture();
    createPortfolioImage($directory, 30, 40);
    File::ensureDirectoryExists(dirname($directory).'/public');
    app()->usePublicPath(dirname($directory).'/public');
    $content = app(ContentRepository::class)->load();
    $content['profile']['portrait']['source'] = 'test-image.png';
    $processor = app(ImageProcessor::class);
    $asset = $processor->prepare($content)['profile']['portrait']['asset'];
    $path = public_path(ltrim($asset['src'], '/'));
    $original = File::get($path);
    $payload = 'TEST-LOCATION-METADATA';
    $withMetadata = $original.'EXIF'.pack('V', strlen($payload)).$payload."\0";
    File::put($path, substr_replace($withMetadata, pack('V', strlen($withMetadata) - 8), 4, 4));

    expect(fn () => $processor->prepare($content))
        ->toThrow(RuntimeException::class, 'A generated image contains metadata.');
});

test('keeps image slots empty when no source photo is supplied', function () {
    $directory = portfolioContentFixture();
    $content = app(ContentRepository::class)->load();
    $content['profile']['portrait']['source'] = null;

    $prepared = app(ImageProcessor::class)->prepare($content);

    expect($prepared['profile']['portrait']['asset'])->toBeNull();
    expect($prepared['projects'][0]['image']['asset'])->toBeNull();
});
