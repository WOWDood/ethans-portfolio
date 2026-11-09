<?php

use App\Services\Portfolio\BrowserArtifacts;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../Fixtures/PortfolioContent.php';

function preparePortfolioBuild(): string
{
    $content = portfolioContentFixture();
    $profile = Yaml::parseFile($content.'/profile.yml');
    $profile['portrait']['source'] = null;
    File::put($content.'/profile.yml', Yaml::dump($profile, 8, 2));
    foreach (['certifications', 'leadership'] as $section) {
        $file = "{$content}/{$section}.yml";
        $data = Yaml::parseFile($file);
        foreach (array_keys($data['entries']) as $index) {
            unset($data['entries'][$index]['badge']);
        }
        File::put($file, Yaml::dump($data, 8, 2));
    }
    config(['portfolio.font_file' => null, 'portfolio.font_license' => null]);
    config(['portfolio.url' => null]);
    config(['portfolio.contact.phone' => null, 'portfolio.contact.email' => null]);
    test()->mock(BrowserArtifacts::class)->shouldReceive('generate')->andReturnUsing(function (string $staging): void {
        File::put($staging.'/ethan-oneil-azlan-resume.pdf', '%PDF-test-fixture');
        File::put($staging.'/social-card.png', 'image fixture');
    });
    $fixture = storage_path('framework/testing/portfolio-'.Str::uuid());
    File::ensureDirectoryExists($fixture.'/public/build/assets');
    File::ensureDirectoryExists($fixture.'/dist');
    File::put($fixture.'/dist/index.html', 'previous export');
    File::put($fixture.'/dist/stale.txt', 'remove on successful rebuild');
    File::put($fixture.'/public/index.php', '<?php echo "private";');
    File::put($fixture.'/public/private.pdf', 'must not be exported');
    File::put($fixture.'/public/hot', 'http://127.0.0.1:5173');
    File::put($fixture.'/public/build/assets/portfolio.css', 'body { color: #047a7a; }');
    File::put($fixture.'/public/build/assets/portfolio.js', 'document.documentElement.dataset.ready = "true";');
    File::put($fixture.'/public/build/assets/shared.js', 'export const ready = true;');
    File::put($fixture.'/public/build/manifest.json', json_encode([
        'resources/css/portfolio.css' => ['file' => 'assets/portfolio.css', 'src' => 'resources/css/portfolio.css'],
        'resources/js/portfolio.js' => ['file' => 'assets/portfolio.js', 'src' => 'resources/js/portfolio.js', 'imports' => ['_shared.js']],
        '_shared.js' => ['file' => 'assets/shared.js', 'src' => 'shared.js'],
    ], JSON_THROW_ON_ERROR));
    app()->usePublicPath($fixture.'/public');
    config(['portfolio.output_directory' => $fixture.'/dist']);
    test()->beforeApplicationDestroyed(function () use ($fixture): void {
        File::deleteDirectory($fixture);
    });

    return $fixture;
}

test('builds assets and exports only public HTML and its production asset dependencies', function () {
    $fixture = preparePortfolioBuild();
    Process::preventStrayProcesses();
    Process::fake();

    $status = Artisan::call('site:build');

    expect(Artisan::output())->toContain('Exported 7 page(s)');
    expect($status)->toBe(0);

    Process::assertRan([PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm', 'run', 'build']);
    $files = collect(File::allFiles($fixture.'/dist'))
        ->map(fn (SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname()))
        ->sort()->values()->all();
    expect($files)->toBe([
        'build/assets/portfolio.css',
        'build/assets/portfolio.js',
        'build/assets/shared.js',
        'ethan-oneil-azlan-resume.pdf',
        'index.html',
        'projects/acotech-hackathon-2025/index.html',
        'projects/arduino-automatic-curtain/index.html',
        'projects/english-asean-olympiad-2023/index.html',
        'projects/wind-powered-vehicle/index.html',
        'projects/wordpress-personal-website/index.html',
        'resume/index.html',
        'robots.txt',
        'sitemap.xml',
        'social-card.png',
    ]);
    expect(File::get($fixture.'/dist/index.html'))
        ->toContain('Ethan Oneil Azlan', 'I’m studying', 'data-theme-toggle', '/build/assets/portfolio.js')
        ->not->toContain('wire:', 'livewire', 'csrf-token', '127.0.0.1', 'localhost', 'proton.me');
    expect(File::isDirectory($fixture.'/dist.building'))->toBeFalse();
});

test('exports configured contact details as obfuscated links only in the contact section', function () {
    $fixture = preparePortfolioBuild();
    $phone = '+60123456789';
    $email = 'person@example.com';
    config(['portfolio.contact.phone' => $phone, 'portfolio.contact.email' => $email]);

    $this->artisan('site:build', ['--skip-assets' => true])->assertSuccessful();

    $html = File::get($fixture.'/dist/index.html');
    $document = HTMLDocument::createFromString($html);
    expect($document->querySelector('#contact a[data-contact-phone]')?->getAttribute('data-contact-phone'))->toBe(base64_encode($phone));
    expect($document->querySelector('#contact a[data-contact-email]')?->getAttribute('data-contact-email'))->toBe(base64_encode($email));
    expect($html)->not->toContain($phone, $email);
    expect(File::get($fixture.'/dist/resume/index.html'))->not->toContain(base64_encode($phone), base64_encode($email));
});

test('removes component metadata and private snapshots while retaining page content', function () {
    $fixture = preparePortfolioBuild();
    Route::get('/snapshot-test', fn () => response('<!doctype html><html><body><main wire:id="abc" wire:name="demo" wire:snapshot="private-personal-data" wire:effects="{}"><h1>Public text</h1></main></body></html>'))->name('snapshot-test');
    Route::getRoutes()->refreshNameLookups();
    config(['portfolio.routes' => ['snapshot-test' => 'index.html']]);

    $this->artisan('site:build', ['--skip-assets' => true])->assertSuccessful();

    expect(File::get($fixture.'/dist/index.html'))
        ->toContain('Public text')
        ->not->toContain('private-personal-data', 'wire:');
});

test('exports only processed local badge images', function (string $section, string $selector, string $alt) {
    $fixture = preparePortfolioBuild();
    $content = config('portfolio.content_directory');
    createPortfolioImage($content, 120, 60);
    $file = "{$content}/{$section}.yml";
    $data = Yaml::parseFile($file);
    $data['entries'][0]['badge'] = ['source' => 'test-image.png', 'alt' => $alt];
    File::put($file, Yaml::dump($data, 8, 2));

    $this->artisan('site:build', ['--skip-assets' => true])->assertSuccessful();

    $document = HTMLDocument::createFromString(File::get($fixture.'/dist/index.html'));
    $image = $document->querySelector($selector);
    expect($image->getAttribute('src'))->toStartWith('/media/badge-');
    expect($image->getAttribute('alt'))->toBe($alt);
    expect(getimagesize($fixture.'/dist'.$image->getAttribute('src'))[2])->toBe(IMAGETYPE_WEBP);
    expect(File::get($fixture.'/dist/index.html'))->not->toContain('images.credly.com', 'test-image.png');
})->with([
    'credential' => ['certifications', '.credential-badge img', 'Cisco support credential badge'],
    'leadership' => ['leadership', '#leadership .role-badge img', 'Student section logo'],
]);

test('writes crawl settings and a sitemap only for configured public pages', function () {
    $fixture = preparePortfolioBuild();
    config(['portfolio.url' => 'https://portfolio.example']);

    $this->artisan('site:build', ['--skip-assets' => true])->assertSuccessful();

    expect(File::get($fixture.'/dist/robots.txt'))->toBe("User-agent: *\nAllow: /\nSitemap: https://portfolio.example/sitemap.xml\n");
    $sitemap = simplexml_load_string(File::get($fixture.'/dist/sitemap.xml'));
    expect($sitemap->url)->toHaveCount(7);
    expect((string) $sitemap->url[0]->loc)->toBe('https://portfolio.example/');
    expect(File::get($fixture.'/dist/sitemap.xml'))->not->toContain('localhost', 'dashboard', 'login', 'proton.me');
});

test('retains the previous export when PDF generation fails', function () {
    $fixture = preparePortfolioBuild();
    $this->mock(BrowserArtifacts::class)->shouldReceive('generate')->andThrow(new RuntimeException('PDF generation failed'));

    $this->artisan('site:build', ['--skip-assets' => true])->expectsOutputToContain('PDF generation failed')->assertFailed();

    expect(File::get($fixture.'/dist/index.html'))->toBe('previous export');
});

test('discovers added Markdown pages and removes unpublished pages on rebuild', function () {
    $fixture = preparePortfolioBuild();
    $content = config('portfolio.content_directory');
    createPortfolioImage($content, 90, 60, jpeg: true);
    writePortfolioProject($content, 'livewire-widget', ['title' => 'Livewire widget'], "## Result\n\nA static case study about `wire:model`.\n\n[Result](#result)\n\n![Prototype on a desk](test-image.jpg)");
    writePortfolioProject($content, 'private-draft', ['published' => false]);

    $this->artisan('site:build', ['--skip-assets' => true])->assertSuccessful();

    $path = $fixture.'/dist/projects/livewire-widget/index.html';
    $document = HTMLDocument::createFromString(File::get($path));
    $image = $document->querySelector('img');
    expect(File::get($path))->toContain('Livewire widget', 'wire:model', 'id="result"')->not->toContain('wire:snapshot', 'TEST-LOCATION-METADATA');
    expect(getimagesize($fixture.'/dist'.$image->getAttribute('src'))[2])->toBe(IMAGETYPE_WEBP);
    expect(File::isDirectory($fixture.'/dist/projects/private-draft'))->toBeFalse();

    writePortfolioProject($content, 'livewire-widget', ['published' => false]);
    $this->artisan('site:build', ['--skip-assets' => true])->assertSuccessful();

    expect(File::isDirectory($fixture.'/dist/projects/livewire-widget'))->toBeFalse();
    expect(File::get($fixture.'/dist/index.html'))->not->toContain('livewire-widget');
});

test('keeps the previous export when a page violates the static contract', function (string $markup, string $message) {
    $fixture = preparePortfolioBuild();
    Route::get('/invalid-test', fn () => response('<!doctype html><html><body>'.$markup.'</body></html>'))->name('invalid-test');
    Route::getRoutes()->refreshNameLookups();
    config(['portfolio.routes' => ['invalid-test' => 'index.html']]);

    $this->artisan('site:build', ['--skip-assets' => true])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(File::get($fixture.'/dist/index.html'))->toBe('previous export');
    expect(File::isDirectory($fixture.'/dist.building'))->toBeFalse();
})->with([
    'server action' => ['<button wire:click="save">Save</button>', 'Server interaction [wire:click]'],
    'missing page' => ['<a href="/missing/">Missing</a>', 'Broken link [/missing/]'],
    'missing fragment' => ['<a href="#missing">Missing</a>', 'Missing anchor [#missing]'],
    'malformed link' => ['<a href="http://:bad">Invalid</a>', 'Invalid link [http://:bad]'],
    'remote script' => ['<script src="https://example.com/runtime.js"></script>', 'Remote browser asset'],
    'hashed runtime endpoint' => ['<script>fetch("/livewire-abcdef/update")</script>', 'Server or development runtime'],
    'exposed email in a template' => ['<meta name="description" content="person&#64;example.com">', 'remove the exposed email or phone number'],
    'phone in a template' => ['<p>012-3456789</p>', 'remove the exposed email or phone number'],
    'unprocessed image' => ['<img src="/source.jpg" alt="A prototype.">', 'Export only processed WebP images'],
    'image without alt' => ['<img src="/media/test.webp">', 'Every exported image needs descriptive alt text'],
]);

test('keeps the previous export when production assets are missing', function () {
    $fixture = preparePortfolioBuild();
    File::delete($fixture.'/public/build/assets/shared.js');

    $this->artisan('site:build', ['--skip-assets' => true])
        ->expectsOutputToContain('Production asset [assets/shared.js] is missing')
        ->assertFailed();

    expect(File::get($fixture.'/dist/index.html'))->toBe('previous export');
});

test('keeps the previous export when the asset build fails', function () {
    $fixture = preparePortfolioBuild();
    Process::fake(['*' => Process::result(exitCode: 1)]);

    $this->artisan('site:build')
        ->expectsOutputToContain('Asset build failed')
        ->assertFailed();

    Process::assertRan([PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm', 'run', 'build']);
    expect(File::get($fixture.'/dist/index.html'))->toBe('previous export');
});

test('refuses an output directory outside the generated export locations', function () {
    preparePortfolioBuild();
    config(['portfolio.output_directory' => base_path('app')]);

    $this->artisan('site:build', ['--skip-assets' => true])
        ->expectsOutputToContain('Export only to dist')
        ->assertFailed();

    expect(File::isFile(app_path('Providers/AppServiceProvider.php')))->toBeTrue();
});

test('stops an invalid content build before assets run and preserves the previous export', function () {
    $fixture = preparePortfolioBuild();
    $content = portfolioContentFixture();
    File::put($content.'/profile.yml', "name: null\n");
    Process::fake();

    $this->artisan('site:build')
        ->expectsOutputToContain('content/profile.yml → name:')
        ->assertFailed();

    Process::assertNothingRan();
    expect(File::get($fixture.'/dist/index.html'))->toBe('previous export');
});

test('exports responsive portrait files and the self-hosted font without source images', function () {
    $fixture = preparePortfolioBuild();
    $content = config('portfolio.content_directory');
    createPortfolioImage($content, 120, 160);
    $profile = Yaml::parseFile($content.'/profile.yml');
    $profile['portrait']['source'] = 'test-image.png';
    File::put($content.'/profile.yml', Yaml::dump($profile, 8, 2));
    File::ensureDirectoryExists($fixture.'/public/fonts');
    File::copy(base_path('public/fonts/dm-sans-latin-variable.woff2'), $fixture.'/public/fonts/dm-sans-latin-variable.woff2');
    File::copy(base_path('public/fonts/OFL.txt'), $fixture.'/public/fonts/OFL.txt');
    config(['portfolio.font_file' => 'fonts/dm-sans-latin-variable.woff2', 'portfolio.font_license' => 'fonts/OFL.txt']);

    $this->artisan('site:build', ['--skip-assets' => true])->assertSuccessful();

    $document = HTMLDocument::createFromString(File::get($fixture.'/dist/index.html'));
    $image = $document->querySelector('img');
    expect($image->getAttribute('alt'))->toBe('Portrait of Ethan Oneil Azlan');
    expect($image->getAttribute('srcset'))->toContain('120w');
    expect(getimagesize($fixture.'/dist'.$image->getAttribute('src'))[2])->toBe(IMAGETYPE_WEBP);
    expect(File::isFile($fixture.'/dist/fonts/dm-sans-latin-variable.woff2'))->toBeTrue();
    expect(File::isFile($fixture.'/dist/fonts/OFL.txt'))->toBeTrue();
    expect(glob($fixture.'/dist/media/*.png'))->toBe([]);
});
