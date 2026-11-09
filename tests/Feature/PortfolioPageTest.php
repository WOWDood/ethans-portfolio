<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../Fixtures/PortfolioContent.php';

test('renders the public page through Livewire without a database or browser runtime', function () {
    $this->withoutVite();
    $database = config('database.default');
    config(['database.default' => 'unavailable']);

    try {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Ethan Oneil Azlan')
            ->assertSee('wire:snapshot', escape: false)
            ->assertSee('data-theme-toggle', escape: false)
            ->assertDontSee('livewire.js', escape: false)
            ->assertDontSee('livewireScriptConfig', escape: false)
            ->assertDontSee('csrf-token', escape: false)
            ->assertHeaderMissing('Set-Cookie');
    } finally {
        config(['database.default' => $database]);
    }
});

test('features the annual dinner above the list of supported events', function () {
    $this->withoutVite();

    $this->get(route('home'))->assertOk()->assertSeeInOrder([
        'Co-emceed the Harrison’s Malaysia annual dinner around June 2026.',
        'Events supported',
        "McDonald's Malaysia",
        'KFC Malaysia',
    ]);
});

test('uses profile edits in page copy and metadata while escaping HTML', function () {
    $directory = portfolioContentFixture();
    $profile = Yaml::parseFile($directory.'/profile.yml');
    $profile['name'] = 'Ethan <script>unsafe</script>';
    $profile['bio'] = 'An updated biography from the content file.';
    $profile['links'] = [['label' => 'Code', 'url' => 'https://github.com/WOWDood']];
    File::put($directory.'/profile.yml', Yaml::dump($profile, 8, 2));
    $this->withoutVite();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Ethan <script>unsafe</script>')
        ->assertSee('An updated biography from the content file.')
        ->assertSee('<meta name="description" content="An updated biography from the content file.">', escape: false)
        ->assertSee('Code')
        ->assertDontSee('<script>unsafe</script>', escape: false)
        ->assertDontSee('A versatile and passionate student');
});
