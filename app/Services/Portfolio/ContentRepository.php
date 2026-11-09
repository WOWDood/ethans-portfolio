<?php

namespace App\Services\Portfolio;

use Illuminate\Filesystem\Filesystem;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ContentRepository
{
    public function __construct(private Filesystem $files, private ContentValidator $validator) {}

    /**
     * @return array{profile: array<string, mixed>, experience: array<string, mixed>, education: array<string, mixed>, skills: array<string, mixed>, certifications: array<string, mixed>, leadership: array<string, mixed>, projects: list<array<string, mixed>>}
     */
    public function load(): array
    {
        $sections = [];

        foreach (['profile', 'experience', 'education', 'skills', 'certifications', 'leadership'] as $section) {
            $file = $section.'.yml';

            try {
                $data = Yaml::parse($this->read($file));
            } catch (ParseException $exception) {
                throw new RuntimeException("content/{$file} → YAML: ".$exception->getMessage(), previous: $exception);
            }

            if (! is_array($data) || array_is_list($data)) {
                throw new RuntimeException("content/{$file} → document: use a YAML mapping of named fields.");
            }

            $this->validator->validate($section, $data, 'content/'.$file);
            $sections[$section] = $this->publicFields($data);
        }

        $projects = [];
        $environment = new Environment([
            'html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 100,
            'heading_permalink' => ['insert' => 'none', 'apply_id_to_heading' => true, 'id_prefix' => '', 'fragment_prefix' => ''],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $converter = new MarkdownConverter($environment);
        $parser = new FrontMatterParser(new SymfonyYamlFrontMatterParser);
        $directory = config()->string('portfolio.content_directory');

        foreach ($this->files->glob($directory.'/projects/*.md') as $path) {
            $file = 'projects/'.basename($path);
            $slug = pathinfo($path, PATHINFO_FILENAME);

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                throw new RuntimeException("content/{$file} → filename: use lowercase words separated by hyphens.");
            }

            try {
                $document = $parser->parse($this->read($file));
            } catch (InvalidFrontMatterException $exception) {
                throw new RuntimeException("content/{$file} → front matter: ".$exception->getMessage(), previous: $exception);
            }

            $data = $document->getFrontMatter();

            if (! is_array($data) || array_is_list($data)) {
                throw new RuntimeException("content/{$file} → front matter: add a YAML mapping between opening and closing --- lines.");
            }

            $this->validator->validate('project', $data, 'content/'.$file);
            $body = $document->getContent();
            $html = (string) $converter->convert($body);
            $this->validator->validateMarkdown($body, $html, 'content/'.$file, $data['published']);

            if ($data['published']) {
                $projects[] = $this->publicFields($data) + ['slug' => $slug, 'body' => $body, 'html' => $html];
            }
        }

        usort($projects, fn (array $left, array $right): int => [$left['order'] ?? 100, $left['slug']] <=> [$right['order'] ?? 100, $right['slug']]);

        return [
            'profile' => $sections['profile'], 'experience' => $sections['experience'],
            'education' => $sections['education'], 'skills' => $sections['skills'],
            'certifications' => $sections['certifications'], 'leadership' => $sections['leadership'],
            'projects' => $projects,
        ];
    }

    private function read(string $file): string
    {
        $root = realpath(config()->string('portfolio.content_directory'));
        $path = $root === false ? false : realpath($root.'/'.$file);

        if ($root === false || $path === false || ! str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $root), '/').'/') || ! $this->files->isFile($path)) {
            throw new RuntimeException("content/{$file} → file: missing or outside the content directory.");
        }

        return $this->files->get($path);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function publicFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if (($key === 'source' && is_array($value)) || in_array($key, ['source_bullets', 'notes'], true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->publicFields($value);
            }
        }

        return $data;
    }
}
