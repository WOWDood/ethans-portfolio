<?php

use App\Services\Portfolio\BrowserArtifacts;
use App\Services\Portfolio\ContentRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

test('explains a missing PDF browser without running a process', function () {
    config(['portfolio.browser' => base_path('missing-browser')]);
    Process::preventStrayProcesses();
    Process::fake();

    expect(fn () => app(BrowserArtifacts::class)->generate(storage_path('framework/testing'), []))
        ->toThrow(RuntimeException::class, 'Set PORTFOLIO_BROWSER');
    Process::assertNothingRan();
});

test('fails and cleans its temporary files when the browser cannot produce a PDF', function (int $exitCode) {
    config(['portfolio.browser' => PHP_BINARY]);
    Process::fake(['*' => Process::result(exitCode: $exitCode)]);
    $directory = storage_path('framework/testing/artifacts-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    $before = glob(storage_path('framework/portfolio-browser-*'));
    $content = app(ContentRepository::class)->load();

    try {
        expect(fn () => app(BrowserArtifacts::class)->generate($directory, $content))->toThrow(RuntimeException::class);
        expect(glob(storage_path('framework/portfolio-browser-*')))->toBe($before);
    } finally {
        File::deleteDirectory($directory);
    }
})->with([0, 1]);
