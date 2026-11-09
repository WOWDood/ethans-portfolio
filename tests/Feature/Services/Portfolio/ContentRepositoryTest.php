<?php

use App\Services\Portfolio\ContentRepository;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/../../../Fixtures/PortfolioContent.php';

test('migrates all résumé sections and preserves the confirmed corrections', function () {
    $content = app(ContentRepository::class)->load();

    expect(array_column($content['projects'], 'slug'))->toContain(
        'wind-powered-vehicle', 'wordpress-personal-website', 'arduino-automatic-curtain',
        'acotech-hackathon-2025', 'english-asean-olympiad-2023',
    );
    expect(array_column($content['experience']['entries'], 'id'))->toContain('starza-it-support', 'hardware-assembly-repair', 'event-work', 'resume4u', 'bake-with-us');
    expect(array_column($content['leadership']['entries'], 'id'))->toContain(
        'iem-ums-media', 'computer-engineering-club-media', 'foundation-english-representative',
        'scouts-platoon-leader', 'football-publicity', 'science-maths-special-tasks', 'science-maths-director',
    );
    expect($content['education']['entries'][0]['start'])->toBe('2025-09-27');
    expect($content['education']['entries'][0]['expected'])->toBeTrue();
    expect($content['experience']['entries'][0]['engagement'])->toBe('Occasional, part-time');
    $credentials = collect($content['certifications']['entries'])->keyBy('id');
    expect($credentials->keys()->all())->toContain(
        'cisco-ccst-it-support', 'computer-hardware-basics', 'comptia-core-1', 'opswat-icip',
        'web-application-fundamental', 'unity-programmer', 'excel-expert', 'word-expert',
        'intro-power-bi', 'google-prompting', 'adobe-after-effects', 'google-marketing-ecommerce',
        'ubs-inventory-billing', 'art-of-selling', 'food-handling', 'ef-set',
    );
    expect($credentials['comptia-core-1']['status'])->toBe('Core 1 passed with training');
    expect($credentials['comptia-core-1']['kind'])->toBe('exam');
    expect($credentials['google-marketing-ecommerce']['status'])->toBe('Partial Completion');
    expect($credentials['ubs-inventory-billing']['issuer'])->toBe('Access Group');
    expect($credentials['pmi-project-management-ready']['date'])->toBeNull();
    expect($credentials['isc2-candidate']['kind'])->toBe('candidate');
    expect(json_encode($content, JSON_THROW_ON_ERROR))->not->toContain('source_bullets', 'Union Bank of Switzerland', 'proton.me');
    expect($content['profile']['portrait'])->toHaveKey('source');
});

test('discovers a new Markdown file and reads later edits without a cached content snapshot', function () {
    $directory = portfolioContentFixture();
    $repository = app(ContentRepository::class);
    $originalCount = count($repository->load()['projects']);

    writePortfolioProject($directory, 'new-build');

    $content = $repository->load();
    expect($content['projects'])->toHaveCount($originalCount + 1);
    expect($content['projects'][0]['slug'])->toBe('new-build');
    expect($content['projects'][0]['html'])->toContain('<strong>working</strong>');
    File::delete($directory.'/projects/new-build.md');
    expect($repository->load()['projects'])->toHaveCount($originalCount);
});

test('validates drafts but excludes them from the published project collection', function () {
    $directory = portfolioContentFixture();
    writePortfolioProject($directory, 'unfinished-build', ['published' => false, 'outcome' => null, 'role' => null, 'tech' => []], '');

    $content = app(ContentRepository::class)->load();

    expect(array_column($content['projects'], 'slug'))->not->toContain('unfinished-build');
});

test('renders Markdown formatting while escaping raw HTML and blocking unsafe link destinations', function () {
    $directory = portfolioContentFixture();
    writePortfolioProject($directory, 'safe-markdown', body: '**Strong text**'."\n\n".'<script>alert("unsafe")</script>'."\n\n".'[Unsafe](javascript:alert(1))');

    $project = app(ContentRepository::class)->load()['projects'][0];

    expect($project['html'])->toContain('<strong>Strong text</strong>', '&lt;script&gt;')
        ->not->toContain('<script>', 'href="javascript:');
});

test('retains a validated image reference without exposing source notes', function () {
    $directory = portfolioContentFixture();
    File::put($directory.'/assets/example.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    writePortfolioProject($directory, 'with-image', ['image' => ['source' => 'example.png', 'alt' => 'A test image.'], 'source' => ['notes' => ['Editorial reference.']]]);

    $project = app(ContentRepository::class)->load()['projects'][0];

    expect($project['image'])->toBe(['source' => 'example.png', 'alt' => 'A test image.']);
    expect($project)->not->toHaveKey('source');
});
