<?php

use App\Services\Portfolio\ContentRepository;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../../../Fixtures/PortfolioContent.php';

test('names the file and invalid project field', function (array $changes, string $field) {
    $directory = portfolioContentFixture();
    writePortfolioProject($directory, 'invalid-build', $changes);

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/projects/invalid-build.md → '.$field.':');
})->with([
    'missing title' => [['title' => null], 'title'],
    'missing outcome' => [['outcome' => null], 'outcome'],
    'missing role' => [['role' => null], 'role'],
    'impossible date' => [['date' => '2025-02-30'], 'date'],
    'unquoted year' => [['date' => 2025], 'date'],
    'unknown technology without explanation' => [['tech' => [], 'tech_notes' => null], 'tech_notes'],
    'missing image' => [['image' => ['source' => 'missing.jpg', 'alt' => 'A prototype.']], 'image.source'],
    'image traversal' => [['image' => ['source' => '../profile.yml', 'alt' => 'A prototype.']], 'image.source'],
    'missing alt' => [['image' => ['source' => 'missing.jpg', 'alt' => null]], 'image.alt'],
    'unsafe URL' => [['links' => [['label' => 'Demo', 'url' => 'javascript:alert(1)']]], 'links.0.url'],
    'unknown field' => [['titel' => 'A typo'], 'titel'],
    'private field' => [['phone' => 'not-for-public-content'], 'phone'],
    'private editorial field' => [['source' => ['email' => 'person@example.com']], 'source.email'],
]);

test('rejects exposed contact details and invalid images in Markdown', function (string $body, string $field) {
    $directory = portfolioContentFixture();
    writePortfolioProject($directory, 'invalid-body', body: $body);

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/projects/invalid-body.md → '.$field.':');
})->with([
    'empty body' => ['', 'body'],
    'exposed email' => ['Contact person@example.com', 'body'],
    'encoded email' => ['Contact person&#64;example.com', 'body'],
    'exposed phone' => ['Contact +60 12 3456789', 'body'],
    'formatted phone' => ['Contact +60 (12) 345-6789', 'body'],
    'international phone' => ['Contact +44 7700 900123', 'body'],
    'image without alt' => ['![](missing.jpg)', 'body.images.0.alt'],
    'missing image' => ['![Prototype](missing.jpg)', 'body.images.0.source'],
    'insecure link' => ['[Project](http://example.com)', 'body.links.0'],
]);

test('reports malformed YAML and missing front matter with the source filename', function (string $contents, string $field) {
    $directory = portfolioContentFixture();
    File::put($directory.'/projects/broken.md', $contents);

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/projects/broken.md → '.$field.':');
})->with([
    'malformed YAML' => ["---\ntitle: [unterminated\n---\nBody\n", 'front matter'],
    'missing front matter' => ['# A project without front matter', 'front matter'],
]);

test('rejects reversed or contradictory role dates', function (string $end, bool $ongoing, string $message) {
    $directory = portfolioContentFixture();
    $data = Yaml::parseFile($directory.'/experience.yml');
    $data['entries'][0]['end'] = $end;
    $data['entries'][0]['ongoing'] = $ongoing;
    File::put($directory.'/experience.yml', Yaml::dump($data, 8, 2));

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/experience.yml → entries.0.end: '.$message);
})->with([
    'end before start' => ['2020', false, 'must not be before start.'],
    'ongoing with end date' => ['2026', true, 'leave empty for an ongoing role.'],
]);

test('rejects missing content files before rendering a partial portfolio', function () {
    $directory = portfolioContentFixture();
    File::delete($directory.'/education.yml');

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/education.yml → file: missing');
});

test('rejects invalid leadership badges with the badge field in the error', function (array $badge, string $field) {
    $directory = portfolioContentFixture();
    $data = Yaml::parseFile($directory.'/leadership.yml');
    $data['entries'][0]['badge'] = $badge;
    File::put($directory.'/leadership.yml', Yaml::dump($data, 8, 2));

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/leadership.yml → entries.0.badge.'.$field.':');
})->with([
    'missing alt' => [['source' => 'iem-ums-student-section-logo.jpg'], 'alt'],
    'missing image' => [['source' => 'missing.png', 'alt' => 'Student section logo'], 'source'],
]);

test('reports malformed or incorrectly shaped YAML documents', function (string $contents, string $field) {
    $directory = portfolioContentFixture();
    File::put($directory.'/profile.yml', $contents);

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/profile.yml → '.$field.':');
})->with([
    'malformed mapping' => ['name: [unclosed', 'YAML'],
    'sequence instead of mapping' => ['- Ethan', 'document'],
]);

test('rejects a credential theme that would disappear from grouped views', function () {
    $directory = portfolioContentFixture();
    $data = Yaml::parseFile($directory.'/certifications.yml');
    $data['entries'][0]['theme'] = 'Unknown group';
    File::put($directory.'/certifications.yml', Yaml::dump($data, 8, 2));

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/certifications.yml → entries.0.theme:');
});

test('rejects a non-image file masquerading as an image', function () {
    $directory = portfolioContentFixture();
    File::put($directory.'/assets/not-an-image.png', 'plain text');
    writePortfolioProject($directory, 'bad-image', ['image' => ['source' => 'not-an-image.png', 'alt' => 'A prototype.']]);

    expect(fn () => app(ContentRepository::class)->load())
        ->toThrow(RuntimeException::class, 'content/projects/bad-image.md → image.source: use a readable JPEG, PNG, or WebP image.');
});
