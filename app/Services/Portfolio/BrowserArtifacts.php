<?php

namespace App\Services\Portfolio;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class BrowserArtifacts
{
    public function __construct(private Filesystem $files) {}

    /** @param array<string, mixed> $content */
    public function generate(string $staging, array $content): void
    {
        $browser = $this->executable();
        $work = storage_path('framework/portfolio-browser-'.Str::uuid());
        $this->files->ensureDirectoryExists($work);

        try {
            foreach (['certifications', 'leadership'] as $section) {
                foreach ($content[$section]['entries'] as $index => $entry) {
                    if (is_string($entry['badge']['asset']['src'] ?? null)) {
                        $path = public_path(ltrim($entry['badge']['asset']['src'], '/'));
                        $content[$section]['entries'][$index]['badge']['asset']['src'] = 'data:image/webp;base64,'.base64_encode($this->files->get($path));
                        $content[$section]['entries'][$index]['badge']['asset']['srcset'] = null;
                    }
                }
            }

            $font = public_path('fonts/dm-sans-latin-variable.woff2');
            $fontData = $this->files->isFile($font) ? base64_encode($this->files->get($font)) : null;
            $this->files->put($work.'/resume.html', view('portfolio.resume-pdf', ['content' => $content, 'fontData' => $fontData])->render());
            $this->run($browser, $work, $work.'/resume.html', ['--no-pdf-header-footer', '--print-to-pdf='.$staging.'/ethan-oneil-azlan-resume.pdf']);

            if (! $this->files->isFile($staging.'/ethan-oneil-azlan-resume.pdf') || ! str_starts_with($this->files->get($staging.'/ethan-oneil-azlan-resume.pdf'), '%PDF-')) {
                throw new RuntimeException('The browser did not produce the public résumé PDF. The previous export was kept.');
            }

            $this->files->put($work.'/social.html', view('portfolio.social-card', ['profile' => $content['profile'], 'fontData' => $fontData])->render());
            $this->run($browser, $work, $work.'/social.html', ['--hide-scrollbars', '--window-size=1200,630', '--screenshot='.$staging.'/social-card.png']);
            $dimensions = @getimagesize($staging.'/social-card.png');

            if ($dimensions === false || $dimensions[0] !== 1200 || $dimensions[1] !== 630) {
                throw new RuntimeException('The browser did not produce the sharing image. The previous export was kept.');
            }
        } finally {
            $this->files->deleteDirectory($work);
        }
    }

    private function executable(): string
    {
        $configured = config('portfolio.browser');
        $candidates = is_string($configured) && $configured !== '' ? [$configured] : [
            'C:/Program Files/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $candidate) {
            if ($this->files->isFile($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Set PORTFOLIO_BROWSER to an installed Chrome or Chromium executable to generate the résumé and sharing image.');
    }

    /** @param list<string> $options */
    private function run(string $browser, string $work, string $html, array $options): void
    {
        $url = 'file:///'.ltrim(str_replace('\\', '/', $html), '/');
        $result = Process::timeout(60)->run([
            $browser, '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--disable-background-networking', '--disable-extensions', '--disable-sync',
            '--user-data-dir='.$work.'/profile', '--timeout=15000', ...$options, $url,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('The browser could not generate the public résumé or sharing image. Check PORTFOLIO_BROWSER. The previous export was kept.');
        }
    }
}
