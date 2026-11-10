<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../Fixtures/PortfolioContent.php';

test('renders each published project with its own title and navigation', function (string $slug, string $title) {
    $this->withoutVite();

    $this->get(route('projects.show', ['slug' => $slug]))
        ->assertOk()->assertSee($title)->assertSee('My role')->assertSee('All projects')
        ->assertSee('Up next')->assertHeaderMissing('Set-Cookie');
})->with([
    ['big-bang-bites', 'Big Bang Bites'],
    ['restaurant-pos', 'POS System XE'],
    ['kiar-posters-and-short-film', 'KIAR Posters & Short Film'],
    ['acotech-hackathon-2025', 'ACOTech Hackathon 2025'],
    ['wind-powered-vehicle', 'Wind-powered vehicle prototype'],
    ['wordpress-personal-website', 'WordPress personal website'],
    ['arduino-automatic-curtain', 'Arduino automatic curtain system'],
    ['english-asean-olympiad-2023', 'English ASEAN Science & Maths Olympiad 2023'],
]);

test('returns not found for missing and unpublished projects', function (string $slug) {
    $content = portfolioContentFixture();
    writePortfolioProject($content, 'draft-project', ['published' => false]);
    $this->withoutVite();

    $this->get(route('projects.show', ['slug' => $slug]))->assertNotFound();
})->with(['missing-project', 'draft-project']);

test('renders new Markdown content with safe markup and heading anchors', function () {
    $content = portfolioContentFixture();
    writePortfolioProject($content, 'new-project', ['title' => 'Fresh <script>title</script>'], "## Result\n\nA **working** build.\n\n<script>unsafe()</script>\n\n[Result](#result)");
    $this->withoutVite();

    $this->get(route('projects.show', ['slug' => 'new-project']))
        ->assertOk()->assertSee('Fresh <script>title</script>')
        ->assertSee('<strong>working</strong>', escape: false)
        ->assertSee('id="result"', escape: false)
        ->assertDontSee('<script>unsafe()</script>', escape: false);
});

test('shows public resume sections and accurate credential labels', function () {
    $this->withoutVite();

    $this->get(route('resume'))->assertOk()
        ->assertSee('27 Sep 2025')->assertSee('Expected Oct 2029')
        ->assertSee('Core 1 passed with training')->assertSee('Partial Completion')
        ->assertSee('data-resume-preview="/ethan-oneil-azlan-resume.pdf?preview=1"', escape: false)
        ->assertSee('Download PDF')->assertSee('Share résumé')
        ->assertSee('Projects & competitions', escape: false)->assertDontSee('proton.me');
});

test('shows a contact form only when its public endpoint is configured', function (bool $configured) {
    $content = portfolioContentFixture();
    $profile = Yaml::parseFile($content.'/profile.yml');
    $profile['contact']['form_id'] = $configured ? 'test123' : null;
    File::put($content.'/profile.yml', Yaml::dump($profile, 8, 2));
    $this->withoutVite();

    $response = $this->get(route('home'))->assertOk()->assertDontSee('proton.me');

    if ($configured) {
        $response->assertSee('action="https://formspree.io/f/test123"', escape: false);
    } else {
        $response->assertDontSee('<form', escape: false);
    }
})->with([true, false]);
