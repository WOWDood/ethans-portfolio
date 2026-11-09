<?php

namespace App\Services\Portfolio;

use Dom\HTMLDocument;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use JsonException;
use Livewire\Livewire;
use RuntimeException;

class StaticSiteExporter
{
    public function __construct(
        private Filesystem $files,
        private Kernel $kernel,
        private Vite $vite,
        private ImageProcessor $images,
        private ContentRepository $content,
        private PageMetadata $metadata,
        private BrowserArtifacts $artifacts,
        private ContentValidator $validator,
    ) {}

    public function export(): int
    {
        $output = config()->string('portfolio.output_directory');
        $staging = $output.'.building';
        $this->assertSafeDirectory($output);
        $this->assertSafeDirectory($staging);
        $configuration = config()->getMany(['app.debug', 'livewire.inject_assets']);
        $hotFile = $this->vite->hotFile();

        try {
            config(['app.debug' => false, 'livewire.inject_assets' => false]);
            $this->vite->useHotFile(storage_path('framework/site-build.hot'));

            if ($this->files->exists($this->vite->hotFile())) {
                throw new RuntimeException('Remove storage/framework/site-build.hot before exporting.');
            }

            $this->files->deleteDirectory($staging);
            $this->files->ensureDirectoryExists($staging);
            $this->copyAssets($staging);
            $this->copyFonts($staging);

            $pages = $this->pages();
            $this->metadata->origin();

            if ($pages === []) {
                throw new RuntimeException('Add at least one public route to portfolio.routes.');
            }

            foreach ($pages as $page) {
                $routeName = $page['route'];
                $filename = $page['file'];
                $this->assertRelativePath($filename);
                $request = Request::create('http://localhost'.route($routeName, $page['parameters'], absolute: false));
                $response = $this->kernel->handle($request);

                try {
                    if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                        throw new RuntimeException("Route [{$routeName}] did not return a successful HTML page.");
                    }

                    $html = $this->prepareHtml((string) $response->getContent());
                    $this->copyPageImages($html, $staging);
                    $this->files->ensureDirectoryExists(dirname($staging.'/'.$filename));
                    $this->files->put($staging.'/'.$filename, $html);
                } finally {
                    $this->kernel->terminate($request, $response);
                    Livewire::flushState();
                }
            }

            $this->artifacts->generate($staging, $this->images->prepare($this->content->load()));
            $this->writeSearchFiles($staging, $pages);
            $this->validateLinks($staging, array_column($pages, 'file'));

            if ($this->files->isDirectory($output)) {
                $this->files->deleteDirectory($output);
            }

            if (! $this->files->moveDirectory($staging, $output) && ! $this->files->copyDirectory($staging, $output)) {
                throw new RuntimeException('Could not publish the completed export into its output directory.');
            }

            return count($pages);
        } finally {
            config($configuration);
            $this->vite->useHotFile($hotFile);
            $this->files->deleteDirectory($staging);
        }
    }

    /** @param list<array{route: string, parameters: array<string, string>, file: string}> $pages */
    private function writeSearchFiles(string $staging, array $pages): void
    {
        if ($this->metadata->origin() === null) {
            $this->files->put($staging.'/robots.txt', "User-agent: *\nDisallow: /\n");
            $this->files->put($staging.'/sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

            return;
        }

        $urls = '';

        foreach ($pages as $page) {
            $path = preg_replace('/index\.html$/', '', $page['file']) ?? $page['file'];
            $urls .= '<url><loc>'.htmlspecialchars($this->metadata->url($path) ?? '', ENT_XML1, 'UTF-8').'</loc></url>';
        }

        $this->files->put($staging.'/sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>');
        $this->files->put($staging.'/robots.txt', "User-agent: *\nAllow: /\nSitemap: ".$this->metadata->url('/sitemap.xml')."\n");
    }

    /** @return list<array{route: string, parameters: array<string, string>, file: string}> */
    private function pages(): array
    {
        /** @var array<string, string> $routes */
        $routes = config()->array('portfolio.routes');
        $pages = [];

        foreach ($routes as $route => $file) {
            if ($route === 'projects.show') {
                foreach ($this->content->load()['projects'] as $project) {
                    $pages[] = ['route' => $route, 'parameters' => ['slug' => $project['slug']], 'file' => str_replace('{slug}', $project['slug'], $file)];
                }
            } else {
                $pages[] = ['route' => $route, 'parameters' => [], 'file' => $file];
            }
        }

        if (count(array_unique(array_column($pages, 'file'))) !== count($pages)) {
            throw new RuntimeException('Public pages must have unique output filenames.');
        }

        return $pages;
    }

    private function assertSafeDirectory(string $directory): void
    {
        $workspace = realpath(base_path());
        $parent = realpath(dirname($directory));

        if ($workspace === false || $parent === false || is_link($directory)) {
            throw new RuntimeException('The export directory must have an existing parent inside this project and cannot be a symlink.');
        }

        $resolved = str_replace('\\', '/', $parent.'/'.basename($directory));
        $root = rtrim(str_replace('\\', '/', $workspace), '/');
        $existing = realpath($directory);

        if ($existing !== false && strtolower(str_replace('\\', '/', $existing)) !== strtolower($resolved)) {
            throw new RuntimeException('The export directory cannot resolve through a link to another location.');
        }

        if (! str_starts_with(strtolower($resolved), strtolower($root.'/')) || in_array(basename($directory), ['.', '..'], true)) {
            throw new RuntimeException('The export directory must be inside this project.');
        }

        $allowed = [$root.'/dist', $root.'/dist.building'];
        $testRoot = $root.'/storage/framework/testing/';

        if (! in_array($resolved, $allowed, true) && ! str_starts_with($resolved, $testRoot)) {
            throw new RuntimeException('Export only to dist or a directory under storage/framework/testing.');
        }
    }

    private function assertRelativePath(string $path): void
    {
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/') || str_contains($path, ':')) {
            throw new RuntimeException("Invalid export path [{$path}]. Use a relative path inside the output directory.");
        }
    }

    private function copyAssets(string $staging): void
    {
        $buildDirectory = config()->string('portfolio.build_directory');
        $this->assertRelativePath($buildDirectory);
        $manifestPath = public_path($buildDirectory.'/manifest.json');

        if (! $this->files->isFile($manifestPath)) {
            throw new RuntimeException('Production assets are missing. Run php artisan site:build without --skip-assets.');
        }

        try {
            /** @var array<string, array{file: string, imports?: list<string>, dynamicImports?: list<string>, css?: list<string>, assets?: list<string>}> $manifest */
            $manifest = json_decode($this->files->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The Vite manifest is invalid. Rebuild production assets.', previous: $exception);
        }

        /** @var list<string> $pending */
        $pending = config()->array('portfolio.entrypoints');
        $visited = [];

        while ($pending !== []) {
            $entrypoint = array_pop($pending);

            if (isset($visited[$entrypoint])) {
                continue;
            }

            if (! isset($manifest[$entrypoint])) {
                throw new RuntimeException("Production asset [{$entrypoint}] is missing from the Vite manifest.");
            }

            $visited[$entrypoint] = true;
            $entry = $manifest[$entrypoint];
            array_push($pending, ...($entry['imports'] ?? []), ...($entry['dynamicImports'] ?? []));

            foreach ([$entry['file'], ...($entry['css'] ?? []), ...($entry['assets'] ?? [])] as $asset) {
                $this->assertRelativePath($asset);
                $source = public_path($buildDirectory.'/'.$asset);

                if (! $this->files->isFile($source) || is_link($source)) {
                    throw new RuntimeException("Production asset [{$asset}] is missing or is a symlink.");
                }

                if (in_array(pathinfo($asset, PATHINFO_EXTENSION), ['js', 'css'], true)) {
                    $this->assertNoRuntime($this->files->get($source), $asset);
                }

                $destination = $staging.'/'.$buildDirectory.'/'.$asset;
                $this->files->ensureDirectoryExists(dirname($destination));
                $this->files->copy($source, $destination);
            }
        }
    }

    private function prepareHtml(string $html): string
    {
        $document = HTMLDocument::createFromString($html, overrideEncoding: 'UTF-8');

        foreach ($document->getElementsByTagName('*') as $element) {
            $attributeNames = [];

            foreach ($element->attributes as $attribute) {
                $attributeNames[] = $attribute->name;
            }

            foreach ($attributeNames as $name) {
                if (str_starts_with($name, 'data-navigate-')) {
                    $element->removeAttribute($name);

                    continue;
                }

                if (! str_starts_with($name, 'wire:')) {
                    continue;
                }

                if (! in_array($name, ['wire:id', 'wire:name', 'wire:snapshot', 'wire:effects', 'wire:key'], true)) {
                    throw new RuntimeException("Server interaction [{$name}] cannot be exported. Use plain JavaScript on public pages.");
                }

                $element->removeAttribute($name);
            }
        }

        foreach ($document->querySelectorAll('script[src], link[href]:not([rel="canonical"])') as $element) {
            $attribute = $element->localName === 'script' ? 'src' : 'href';
            $value = $element->getAttribute($attribute);

            if (str_starts_with($value, 'data:')) {
                continue;
            }

            if (str_starts_with($value, 'http://localhost/')) {
                $element->setAttribute($attribute, substr($value, strlen('http://localhost')));
            } elseif (str_contains($value, '://') || str_starts_with($value, '//')) {
                throw new RuntimeException("Remote browser asset [{$value}] cannot be exported. Use a local production asset.");
            }
        }

        $result = $document->saveHtml();
        $this->validator->validatePublicMarkup($result);
        $this->assertNoRuntime($result, 'rendered HTML');

        return $result;
    }

    private function copyFonts(string $staging): void
    {
        foreach (['portfolio.font_file', 'portfolio.font_license'] as $setting) {
            $file = config($setting);

            if ($file === null) {
                continue;
            }

            if (! is_string($file) || ! preg_match('/^fonts\/[a-zA-Z0-9._-]+\.(woff2|txt)$/', $file)) {
                throw new RuntimeException('Font assets must be WOFF2 or licence files under public/fonts/.');
            }

            $this->copyPublicFile($file, $staging);
        }
    }

    private function copyPageImages(string $html, string $staging): void
    {
        $document = HTMLDocument::createFromString($html, overrideEncoding: 'UTF-8');

        foreach ($document->querySelectorAll('img, source[srcset]') as $image) {
            $sources = [];

            if ($image->localName === 'img') {
                if (trim($image->getAttribute('alt')) === '') {
                    throw new RuntimeException('Every exported image needs descriptive alt text.');
                }

                $sources[] = $image->getAttribute('src');
            }

            if ($image->hasAttribute('srcset')) {
                foreach (explode(',', $image->getAttribute('srcset')) as $candidate) {
                    $sources[] = explode(' ', trim($candidate))[0];
                }
            }

            foreach ($sources as $source) {
                if (! preg_match('/^\/media\/[a-z0-9-]+\.webp$/', $source)) {
                    throw new RuntimeException('Export only processed WebP images from public/media/.');
                }

                $relative = ltrim($source, '/');
                $this->images->assertCleanWebp(public_path($relative));
                $this->copyPublicFile($relative, $staging);
            }
        }
    }

    private function copyPublicFile(string $relative, string $staging): void
    {
        $root = realpath(public_path());
        $source = realpath(public_path($relative));

        if ($root === false || $source === false || ! str_starts_with(str_replace('\\', '/', $source), rtrim(str_replace('\\', '/', $root), '/').'/') || ! $this->files->isFile($source)) {
            throw new RuntimeException("Public asset [{$relative}] is missing or outside the public directory.");
        }

        $destination = $staging.'/'.$relative;
        $this->files->ensureDirectoryExists(dirname($destination));

        if (! $this->files->copy($source, $destination)) {
            throw new RuntimeException("Could not export public asset [{$relative}].");
        }
    }

    private function assertNoRuntime(string $content, string $source): void
    {
        if ($source === 'rendered HTML') {
            $document = HTMLDocument::createFromString($content, overrideEncoding: 'UTF-8');
            $content = '';

            foreach ($document->querySelectorAll('script') as $script) {
                $content .= $script->getAttribute('src').' '.$script->textContent;
            }

            if ($document->querySelector('[data-update-uri]') !== null) {
                throw new RuntimeException('A Livewire update configuration cannot be exported.');
            }
        }

        if (preg_match('/\$wire\b|Livewire\s*\.\s*start\s*\(|window\.Livewire\s*=|\/livewire(?:-[a-f0-9]+)?\/(?:update|livewire|upload-file)|@vite\/client|livewireScriptConfig|data-update-uri/i', $content)) {
            throw new RuntimeException("Server or development runtime found in [{$source}]. The previous static export was kept.");
        }
    }

    /**
     * @param  list<string>  $routes
     */
    private function validateLinks(string $staging, array $routes): void
    {
        foreach ($routes as $filename) {
            $document = HTMLDocument::createFromString($this->files->get($staging.'/'.$filename), overrideEncoding: 'UTF-8');

            foreach ($document->querySelectorAll('a[href], script[src], link[href], img[src]') as $element) {
                $url = $element->getAttribute(in_array($element->localName, ['script', 'img'], true) ? 'src' : 'href');
                $parts = parse_url($url);

                if ($parts === false) {
                    throw new RuntimeException("Invalid link [{$url}] in [{$filename}].");
                }

                if (preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $url)) {
                    continue;
                }

                $path = rawurldecode($parts['path'] ?? '');
                $fragment = rawurldecode($parts['fragment'] ?? '');
                $relative = $path === '' ? $filename : (str_starts_with($path, '/') ? ltrim($path, '/') : dirname($filename).'/'.$path);
                $relative = preg_replace('/^\.\//', '', $relative) ?? $relative;
                $relative = $relative === '' || str_ends_with($relative, '/') ? $relative.'index.html' : $relative;
                $this->assertRelativePath($relative);

                if (! $this->files->isFile($staging.'/'.$relative)) {
                    throw new RuntimeException("Broken link [{$url}] in [{$filename}].");
                }

                if ($fragment !== '' && str_ends_with($relative, '.html')) {
                    $target = $relative === $filename ? $document : HTMLDocument::createFromString($this->files->get($staging.'/'.$relative), overrideEncoding: 'UTF-8');

                    if ($target->getElementById($fragment) === null) {
                        throw new RuntimeException("Missing anchor [{$url}] in [{$filename}].");
                    }
                }
            }
        }
    }
}
