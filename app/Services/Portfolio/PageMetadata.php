<?php

namespace App\Services\Portfolio;

class PageMetadata
{
    public function origin(): ?string
    {
        $url = config('portfolio.url');

        if ($url === null || $url === '') {
            return null;
        }

        $parts = is_string($url) ? parse_url($url) : false;

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment']) || ! in_array($parts['path'] ?? '', ['', '/'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('PORTFOLIO_URL must be a public HTTPS origin, for example https://your-domain.com. Leave it empty for a noindex preview.');
        }

        $host = strtolower($parts['host']);

        if (! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) !== false || str_ends_with($host, '.test') || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new \RuntimeException('PORTFOLIO_URL must use a public domain, not a local hostname or IP address.');
        }

        return rtrim($url, '/');
    }

    public function url(string $path): ?string
    {
        $origin = $this->origin();

        return $origin === null ? null : $origin.'/'.ltrim($path, '/');
    }

    /** @param array<string, mixed> $profile */
    public function person(array $profile): string
    {
        $person = ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $profile['name'], 'description' => $profile['bio'], 'sameAs' => array_column($profile['links'], 'url')];

        if ($this->origin() !== null) {
            $person['url'] = $this->url('/');
        }

        return json_encode($person, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
