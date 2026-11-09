<?php

namespace App\Console\Commands;

use App\Services\Portfolio\ContentRepository;
use App\Services\Portfolio\ImageProcessor;
use App\Services\Portfolio\StaticSiteExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class BuildSite extends Command
{
    protected $signature = 'site:build {--skip-assets : Use an existing production Vite build}';

    protected $description = 'Build the public portfolio as standalone static files';

    public function handle(StaticSiteExporter $exporter, ContentRepository $content, ImageProcessor $images): int
    {
        try {
            $images->prepare($content->load());
            $this->components->info('Content validated.');

            if (! $this->option('skip-assets')) {
                $this->components->info('Building production assets...');

                $result = Process::path(base_path())->timeout(120)->run(
                    [PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm', 'run', 'build'],
                    function (string $type, string $output): void {
                        $this->output->write($output);
                    },
                );

                if ($result->failed()) {
                    $this->components->error('Asset build failed. The previous static export was kept.');

                    return self::FAILURE;
                }
            }

            $pages = $exporter->export();
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Exported {$pages} page(s) to ".config('portfolio.output_directory').'.');
        $this->line('The export needs only a static file server. No PHP or Livewire runtime is included.');

        return self::SUCCESS;
    }
}
