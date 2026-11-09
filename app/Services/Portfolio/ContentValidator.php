<?php

namespace App\Services\Portfolio;

use Closure;
use Dom\HTMLDocument;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Rule;
use RuntimeException;

class ContentValidator
{
    public function __construct(private Factory $validation) {}

    public function validatePublicMarkup(string $html): void
    {
        $this->assertPublicData(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'rendered HTML', 'public content');
    }

    /** @param array<string, mixed> $data */
    public function validate(string $section, array $data, string $file): void
    {
        $this->assertPublicData($data, $file);
        $rules = $this->rules($section);
        $fields = array_filter(array_keys($rules), fn (string $key): bool => ! str_contains($key, '.'));

        foreach (array_diff(array_keys($data), $fields) as $field) {
            throw new RuntimeException("{$file} → {$field}: unknown field. Check its spelling.");
        }

        $validator = $this->validation->make($data, $rules);

        if ($validator->fails()) {
            $errors = [];

            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[] = "{$file} → {$field}: {$messages[0]}";
            }

            throw new RuntimeException(implode(PHP_EOL, $errors));
        }

        if (isset($data['entries']) && is_array($data['entries'])) {
            foreach ($data['entries'] as $index => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $start = $entry['start'] ?? null;
                $end = $entry['end'] ?? null;

                if (is_string($start) && is_string($end)) {
                    $precision = min(strlen($start), strlen($end));

                    if (strcmp(substr($start, 0, $precision), substr($end, 0, $precision)) > 0) {
                        throw new RuntimeException("{$file} → entries.{$index}.end: must not be before start.");
                    }
                }

                if (($entry['ongoing'] ?? false) === true && $end !== null) {
                    throw new RuntimeException("{$file} → entries.{$index}.end: leave empty for an ongoing role.");
                }

                if (is_string($entry['badge']['source'] ?? null)) {
                    $this->assertImage($entry['badge']['source'], $file, "entries.{$index}.badge.source");
                }
            }
        }

        $image = $section === 'profile' ? ($data['portrait'] ?? null) : ($data['image'] ?? null);

        if (is_array($image) && is_string($image['source'] ?? null)) {
            $this->assertImage($image['source'], $file, $section === 'profile' ? 'portrait.source' : 'image.source');
        }

        if ($section === 'project' && $data['published'] && $data['tech'] === [] && trim($data['tech_notes'] ?? '') === '') {
            throw new RuntimeException("{$file} → tech_notes: explain when technology is unknown or not applicable.");
        }

        if ($section === 'certifications') {
            foreach ($data['entries'] as $index => $entry) {
                if (! in_array($entry['theme'], $data['themes'], true)) {
                    throw new RuntimeException("{$file} → entries.{$index}.theme: choose a theme listed in themes.");
                }
            }
        }
    }

    public function validateMarkdown(string $markdown, string $html, string $file, bool $published): void
    {
        $this->assertPublicData($markdown, $file, 'body');
        $this->assertPublicData(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $file, 'body');

        if ($published && trim($markdown) === '') {
            throw new RuntimeException("{$file} → body: add a project description before publishing.");
        }

        $document = HTMLDocument::createFromString('<!doctype html><html><head><title>Project content</title></head><body>'.$html.'</body></html>', overrideEncoding: 'UTF-8');

        foreach ($document->querySelectorAll('img') as $index => $image) {
            if (trim($image->getAttribute('alt')) === '') {
                throw new RuntimeException("{$file} → body.images.{$index}.alt: describe this image.");
            }

            $this->assertImage($image->getAttribute('src'), $file, "body.images.{$index}.source");
        }

        foreach ($document->querySelectorAll('a[href]') as $index => $link) {
            $href = $link->getAttribute('href');

            if (! str_starts_with($href, '#') && (! str_starts_with($href, 'https://') || filter_var($href, FILTER_VALIDATE_URL) === false)) {
                throw new RuntimeException("{$file} → body.links.{$index}: use an HTTPS URL or a local heading anchor.");
            }
        }
    }

    private function assertPublicData(mixed $value, string $file, string $path = ''): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $field = $path === '' ? (string) $key : $path.'.'.$key;
                $normalised = strtolower(str_replace(['_', '-'], '', (string) $key));

                if (in_array($normalised, ['phone', 'phonenumber', 'telephone', 'mobile', 'address', 'homeaddress', 'streetaddress', 'email'], true)) {
                    throw new RuntimeException("{$file} → {$field}: private contact details do not belong in public content.");
                }

                $this->assertPublicData($child, $file, $field);
            }
        } elseif (is_string($value) && preg_match('/[a-z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[a-z0-9.-]+\.[a-z]{2,}|(?<!\d)(?:\+?60[\s().-]*1\d|01\d)(?:[\s().-]*\d){7,8}(?!\d)|\+\d{1,3}(?:[\s().-]*\d){7,12}(?!\d)/i', $value)) {
            throw new RuntimeException("{$file} → {$path}: remove the exposed email or phone number; use the contact provider instead.");
        }
    }

    private function assertImage(string $source, string $file, string $field): void
    {
        $root = realpath(config()->string('portfolio.content_directory').'/assets');
        $path = $root === false ? false : realpath($root.'/'.$source);

        if ($root === false || $path === false || ! str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $root), '/').'/') || ! is_file($path)) {
            throw new RuntimeException("{$file} → {$field}: image must exist inside content/assets/.");
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true) || @getimagesize($path) === false) {
            throw new RuntimeException("{$file} → {$field}: use a readable JPEG, PNG, or WebP image.");
        }
    }

    /** @return array<string, list<mixed>> */
    private function rules(string $section): array
    {
        $date = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/', $value, $parts) || ! checkdate((int) ($parts[2] ?? 1), (int) ($parts[3] ?? 1), (int) $parts[1])) {
                $fail('Use a quoted date: YYYY, YYYY-MM, or YYYY-MM-DD.');
            }
        };
        $text = ['required', 'string'];
        $source = ['source' => ['sometimes', 'array']];
        $period = [
            'entries.*.start' => ['present', 'nullable', $date],
            'entries.*.end' => ['present', 'nullable', $date],
        ];
        $entries = [
            'entries' => ['required', 'array', 'list', 'min:1'],
            'entries.*.id' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'distinct:strict'],
            'entries.*.notes' => ['sometimes', 'array', 'list'],
            'entries.*.notes.*' => $text,
            'entries.*.source_bullets' => ['sometimes', 'array', 'list'],
            'entries.*.source_bullets.*' => $text,
            'entries.*.source' => ['sometimes', 'array'],
        ];
        $roles = [
            'entries.*.role' => $text,
            'entries.*.ongoing' => ['required', 'boolean:strict'],
            'entries.*.engagement' => ['present', 'nullable', 'string'],
        ];
        $badge = [
            'entries.*.badge' => ['sometimes', 'nullable', 'array:source,alt'],
            'entries.*.badge.source' => ['required_with:entries.*.badge', 'string'],
            'entries.*.badge.alt' => ['required_with:entries.*.badge', 'string'],
        ];

        return match ($section) {
            'profile' => $source + [
                'name' => $text, 'headline' => $text, 'bio' => $text, 'tagline' => $text,
                'hero_lines' => ['required', 'array', 'list', 'min:1', 'max:4'],
                'hero_lines.*' => $text,
                'focus_lines' => ['required', 'array', 'list', 'min:1'], 'focus_lines.*' => $text,
                'availability' => ['required', 'array:primary,secondary'],
                'availability.primary' => $text, 'availability.secondary' => $text,
                'about' => ['required', 'array:heading,paragraphs'], 'about.heading' => $text,
                'about.paragraphs' => ['required', 'array', 'list', 'min:1'], 'about.paragraphs.*' => $text,
                'contact' => ['required', 'array:heading,intro,method,form_id'],
                'contact.heading' => $text, 'contact.intro' => $text,
                'contact.method' => ['required', Rule::in(['formspree'])],
                'contact.form_id' => ['present', 'nullable', 'string', 'regex:/^[a-zA-Z0-9]+$/'],
                'links' => ['required', 'array', 'list', 'min:1'], 'links.*' => ['array:label,url'],
                'links.*.label' => $text, 'links.*.url' => ['required', 'url:https'],
                'portrait' => ['required', 'array:source,alt,focal_point'],
                'portrait.source' => ['present', 'nullable', 'string'], 'portrait.alt' => $text,
                'portrait.focal_point' => ['required', 'array', 'list', 'size:2'],
                'portrait.focal_point.*' => ['required', 'numeric', 'between:0,1'],
                'languages' => ['required', 'array', 'list', 'min:1'],
                'languages.*' => ['array:name,speaking,writing'],
                'languages.*.name' => $text, 'languages.*.speaking' => $text, 'languages.*.writing' => $text,
            ],
            'experience' => $source + $entries + $period + $roles + [
                'entries.*' => ['array:id,employer,role,start,end,ongoing,engagement,bullets,clients,event_highlight,source_bullets,notes,source'],
                'entries.*.employer' => $text,
                'entries.*.bullets' => ['required', 'array', 'list', 'min:1'], 'entries.*.bullets.*' => $text,
                'entries.*.clients' => ['sometimes', 'array', 'list'], 'entries.*.clients.*' => $text,
                'entries.*.event_highlight' => ['sometimes', ...$text],
            ],
            'education' => $source + $entries + $period + [
                'entries.*' => ['array:id,institution,qualification,start,end,expected,highlights,source_bullets,notes,source'],
                'entries.*.institution' => $text, 'entries.*.qualification' => $text,
                'entries.*.expected' => ['required', 'boolean:strict'],
                'entries.*.highlights' => ['present', 'array', 'list'], 'entries.*.highlights.*' => $text,
            ],
            'leadership' => $source + $entries + $period + $roles + $badge + [
                'entries.*' => ['array:id,organisation,role,start,end,ongoing,engagement,badge,activities,notes,source_bullets,source'],
                'entries.*.organisation' => $text,
                'entries.*.activities' => ['sometimes', 'array', 'list'],
                'entries.*.activities.*' => $text,
            ],
            'skills' => $source + [
                'clusters' => ['required', 'array', 'list', 'min:1'],
                'clusters.*' => ['array:title,items'], 'clusters.*.title' => $text,
                'clusters.*.items' => ['required', 'array', 'list', 'min:1'], 'clusters.*.items.*' => $text,
            ],
            'certifications' => $source + $entries + $badge + [
                'themes' => ['required', 'array', 'list', 'min:1'], 'themes.*' => ['required', 'string', 'distinct:strict'],
                'entries.*' => ['array:id,title,theme,kind,issuer,provider,date,status,expires,badge,notes,source,source_bullets'],
                'entries.*.title' => $text,
                'entries.*.theme' => ['required', 'string'],
                'entries.*.kind' => ['required', Rule::in(['certification', 'course', 'exam', 'assessment', 'candidate'])],
                'entries.*.issuer' => ['present', 'nullable', 'string'], 'entries.*.provider' => ['present', 'nullable', 'string'],
                'entries.*.date' => ['present', 'nullable', $date], 'entries.*.status' => $text,
                'entries.*.expires' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}-\d{2}$/', $date],
            ],
            'project' => $source + [
                'title' => $text, 'kind' => ['required', Rule::in(['project', 'hackathon', 'competition'])],
                'published' => ['required', 'boolean:strict'], 'order' => ['sometimes', 'integer', 'min:0'],
                'date' => ['present', 'nullable', $date], 'context' => ['sometimes', 'nullable', 'string'],
                'outcome' => ['required_if:published,true', 'nullable', 'string'],
                'role' => ['required_if:published,true', 'nullable', 'string'],
                'tech' => ['present', 'array', 'list'], 'tech.*' => $text,
                'tech_notes' => ['sometimes', 'nullable', 'string'],
                'image' => ['required', 'array:source,alt'], 'image.source' => ['present', 'nullable', 'string'],
                'image.alt' => ['required_with:image.source', 'nullable', 'string'],
                'links' => ['present', 'array', 'list'], 'links.*' => ['array:label,url'],
                'links.*.label' => $text, 'links.*.url' => ['required', 'url:https'],
            ],
            default => throw new RuntimeException("Unknown content section [{$section}]."),
        };
    }
}
