<?php

use App\Services\Portfolio\ContentRepository;
use App\Services\Portfolio\ImageProcessor;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../Fixtures/PortfolioContent.php';

test('shows the supplied badges, credential dates, and current student roles', function (string $route) {
    $content = portfolioContentFixture();
    File::ensureDirectoryExists(dirname($content).'/public');
    app()->usePublicPath(dirname($content).'/public');
    $this->withoutVite();
    $this->travelTo(new DateTimeImmutable('2026-11-07T12:00:00+08:00'));

    $response = $this->get(route($route))->assertOk()
        ->assertSee('Access Group')->assertDontSee('Issuer unconfirmed')
        ->assertSee('PMI Project Management Ready™')->assertSee('Candidate status')
        ->assertSee('Expired 6 Jun 2026')->assertSee('Expired 31 Aug 2026')
        ->assertSee('Expires 19 Jun 2030')->assertSee('https://www.credly.com/users/ethan-o-azlan', escape: false)
        ->assertSee('Certified Ethical Hacker (CEH)')->assertSee('EC-Council')->assertSee('3 Mar 2026')
        ->assertDontSee('Expires 1 Apr 2027')
        ->assertSee('Partial Completion')
        ->assertSee('IEM–UMS Student Section')->assertSee('Nov 2025 — Present')
        ->assertSee('Media and Publicity Committee')->assertSee('General Secretary (Secretariat)')
        ->assertSee('Persatuan Mahasiswa Fakulti Kejuruteraan UMS (PMFKJ)')->assertDontSee('PMKJ')
        ->assertSee('Dec 2025 — Present')->assertDontSee('EXCO')->assertDontSee('1st Year')
        ->assertDontSee('Occasional participation')
        ->assertSee('IEM Career Talk')->assertSee('Media and Publicity Unit for Allench Cup Program')
        ->assertSee("Vice Secretary for FTEC'26 Program")->assertSee('Media and Publicity Unit for TechXplore 2025');

    $document = HTMLDocument::createFromString($response->getContent());
    if ($route === 'home') {
        expect($document->querySelectorAll('#leadership details.role-activities'))->toHaveCount(3);
        expect($document->querySelectorAll('#leadership details.role-activities[open]'))->toHaveCount(0);
    }
    $selector = $route === 'home' ? '#certifications .credential-badge img' : '.credential-badge img';
    expect($document->querySelectorAll($selector))->toHaveCount(13);
    foreach (['Google Prompting Essentials completion badge', 'Google logo', 'Access UBS product logo'] as $alt) {
        expect($document->querySelectorAll($selector.'[alt="'.$alt.'"]'))->toHaveCount(1);
    }
    foreach ($document->querySelectorAll($selector) as $badge) {
        expect($badge->getAttribute('src'))->toStartWith('/media/badge-');
        expect($badge->getAttribute('width'))->toBe($badge->getAttribute('height'));
        expect((int) $badge->getAttribute('width'))->toBeBetween(80, 192);
    }

    $roleSelector = $route === 'home' ? '#leadership .role-badge img' : '.resume-role .role-badge img';
    expect($document->querySelectorAll($roleSelector))->toHaveCount(3);
    foreach (['IEM–UMS Student Section logo', 'PMFKJ Engineering Students’ Society logo', 'UMS Computer Engineering Club logo'] as $alt) {
        $logo = $document->querySelector($roleSelector.'[alt="'.$alt.'"]');
        expect($logo)->not->toBeNull();
        expect($logo->getAttribute('src'))->toStartWith('/media/badge-');
    }
})->with(['home', 'resume']);

test('retains every edge of a non-square badge when fitting it into a square asset', function () {
    $content = portfolioContentFixture();
    createPortfolioImage($content, 600, 200);
    File::ensureDirectoryExists(dirname($content).'/public');
    app()->usePublicPath(dirname($content).'/public');
    $data = app(ContentRepository::class)->load();
    $data['certifications']['entries'][0]['badge'] = ['source' => 'test-image.png', 'alt' => 'A wide badge'];

    $prepared = app(ImageProcessor::class)->prepare($data);

    $asset = $prepared['certifications']['entries'][0]['badge']['asset'];
    $image = imagecreatefromwebp(public_path(ltrim($asset['src'], '/')));
    expect(imagesx($image))->toBe(192);
    expect(imagesy($image))->toBe(192);
    expect(imagecolorsforindex($image, imagecolorat($image, 96, 1))['alpha'])->toBe(127);
    expect(imagecolorsforindex($image, imagecolorat($image, 8, 80))['red'])->toBeGreaterThan(180);
    expect(imagecolorsforindex($image, imagecolorat($image, 184, 80))['green'])->toBeGreaterThan(180);
    expect(imagecolorsforindex($image, imagecolorat($image, 8, 114))['blue'])->toBeGreaterThan(180);
    expect(imagecolorsforindex($image, imagecolorat($image, 184, 114))['red'])->toBeGreaterThan(180);
});

test('rejects invalid badge fields with the credential path in the message', function (array $change, string $field) {
    $content = portfolioContentFixture();
    $credentials = Yaml::parseFile($content.'/certifications.yml');
    $credentials['entries'][0] = array_replace($credentials['entries'][0], $change);
    File::put($content.'/certifications.yml', Yaml::dump($credentials, 8, 2));

    expect(fn () => app(ContentRepository::class)->load())->toThrow(RuntimeException::class, 'entries.0.'.$field);
})->with([
    'missing alt' => [['badge' => ['source' => 'cisco-ccst-it-support-badge.png']], 'badge.alt'],
    'missing image' => [['badge' => ['source' => 'missing-badge.png', 'alt' => 'Credential badge']], 'badge.source'],
    'invalid expiry' => [['expires' => '2030-02-30'], 'expires'],
    'imprecise expiry' => [['expires' => '2030-07'], 'expires'],
]);
