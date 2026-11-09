<?php

use App\Services\Portfolio\ContentRepository;
use App\Services\Portfolio\PageMetadata;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../Fixtures/PortfolioContent.php';

test('keeps previews out of search without inventing a public URL', function () {
    config(['portfolio.url' => null]);
    $this->withoutVite();

    $this->get(route('home'))->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
        ->assertDontSee('rel="canonical"', escape: false)
        ->assertDontSee('property="og:url"', escape: false);
});

test('uses the configured domain and trailing slashes for page and sharing URLs', function () {
    config(['portfolio.url' => 'https://portfolio.example/']);
    $this->withoutVite();

    $this->get(route('projects.show', ['slug' => 'acotech-hackathon-2025']))->assertOk()
        ->assertSee('<link rel="canonical" href="https://portfolio.example/projects/acotech-hackathon-2025/">', escape: false)
        ->assertSee('content="https://portfolio.example/social-card.png"', escape: false)
        ->assertSee('content="index, follow"', escape: false);
});

test('serializes Person data safely with only public profile details', function () {
    $directory = portfolioContentFixture();
    $profile = Yaml::parseFile($directory.'/profile.yml');
    $profile['name'] = 'Ethan </script><script>unsafe</script>';
    File::put($directory.'/profile.yml', Yaml::dump($profile, 8, 2));
    $this->withoutVite();
    config(['portfolio.url' => 'https://portfolio.example']);

    $response = $this->get(route('home'))->assertOk();

    $document = HTMLDocument::createFromString($response->getContent());
    $person = json_decode($document->querySelector('script[type="application/ld+json"]')->textContent, true, flags: JSON_THROW_ON_ERROR);
    expect($person)->toBe([
        '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $profile['name'],
        'description' => $profile['bio'], 'sameAs' => array_column($profile['links'], 'url'),
        'url' => 'https://portfolio.example/',
    ]);
    $response->assertDontSee('<script>unsafe</script>', escape: false);
});

test('refuses malformed or local public origins', function (string $origin) {
    config(['portfolio.url' => $origin]);

    expect(fn () => app(PageMetadata::class)->origin())->toThrow(RuntimeException::class);
})->with(['http://portfolio.example', 'https://localhost', 'https://ethan.test', 'https://127.0.0.1', 'https://portfolio.example/subfolder', 'https://user@portfolio.example', 'https://portfolio.example?key=value']);

test('renders PDF input with external links and no local file references', function () {
    config(['portfolio.url' => null]);
    $content = app(ContentRepository::class)->load();

    $html = view('portfolio.resume-pdf', ['content' => $content, 'fontData' => null])->render();

    expect($html)->toContain('https://github.com/WOWDood', 'Core 1 passed with training')
        ->not->toContain('href="/', 'file:', 'proton.me', 'wire:snapshot');
});
