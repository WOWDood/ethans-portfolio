<!DOCTYPE html>
@inject('metadata', 'App\Services\Portfolio\PageMetadata')
@php
    $title = $pageTitle ?? ($profile['name'].' — '.$profile['headline']);
    $description = $pageDescription ?? $profile['bio'];
    $canonicalPath = request()->path() === '/' ? '/' : '/'.trim(request()->path(), '/').'/';
    $canonical = $metadata->url($canonicalPath);
@endphp
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $description }}">
        <meta name="robots" content="{{ $canonical ? 'index, follow' : 'noindex, nofollow' }}">
        <meta name="theme-color" content="#151515">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $profile['name'] }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        @if ($canonical)
            <link rel="canonical" href="{{ $canonical }}">
            <meta property="og:url" content="{{ $canonical }}">
            <meta property="og:image" content="{{ $metadata->url('/social-card.png') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="{{ $profile['name'] }} — {{ $profile['headline'] }}">
            <meta name="twitter:image" content="{{ $metadata->url('/social-card.png') }}">
            <meta name="twitter:image:alt" content="{{ $profile['name'] }} — {{ $profile['headline'] }}">
        @endif
        <script type="application/ld+json">{!! $metadata->person($profile) !!}</script>
        <meta name="color-scheme" content="light dark">
        <script>
            try {
                const theme = localStorage.getItem('portfolio-theme');
                document.documentElement.dataset.theme = theme === 'light' || theme === 'dark' ? theme : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch {}
        </script>
        <title>{{ $title }}</title>
        @if (config('portfolio.font_file'))
            <link rel="preload" href="/{{ config('portfolio.font_file') }}" as="font" type="font/woff2" crossorigin>
        @endif
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Crect width='40' height='40' rx='12' fill='%23383838'/%3E%3Ctext x='20' y='28' text-anchor='middle' font-size='26' fill='white' font-family='sans-serif'%3Ee%3C/text%3E%3C/svg%3E">
        @vite(config('portfolio.entrypoints'), config('portfolio.build_directory'))
    </head>
    <body @class(['home-page' => $canonicalPath === '/'])>
        <a href="#main" class="skip-link">Skip to content</a>
        <header class="site-header" @if ($canonicalPath === '/') data-over-hero @endif>
            <div class="navigation-bar">
            <a href="{{ route('home', absolute: false) }}" class="wordmark" aria-label="{{ $profile['name'] }}, home">{{ strtolower(explode(' ', $profile['name'])[0]) }}<span aria-hidden="true">.</span></a>
            <div class="navigation-actions">
                <nav aria-label="Main navigation" class="desktop-navigation">
                    <a href="{{ route('home', absolute: false) }}#work">Work</a>
                    <a href="{{ route('home', absolute: false) }}#about">About</a>
                    <a href="{{ route('home', absolute: false) }}#experience">Experience</a>
                    <a href="{{ route('resume', absolute: false) }}/">Résumé</a>
                    <a href="{{ route('home', absolute: false) }}#contact" class="nav-contact">Contact <span aria-hidden="true">↗</span></a>
                </nav>
                <button type="button" data-theme-toggle hidden aria-pressed="false" class="theme-toggle"><span class="sr-only">Dark mode</span><span data-theme-state class="sr-only" aria-hidden="true">off</span><svg class="theme-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20.2 15.5A8.5 8.5 0 0 1 8.5 3.8 8.5 8.5 0 1 0 20.2 15.5Z"/></svg></button>
                <details data-mobile-menu class="mobile-menu">
                    <summary><span class="sr-only">Menu</span><span class="menu-lines" aria-hidden="true"></span></summary>
                    <nav id="mobile-navigation" aria-label="Mobile navigation" class="menu-surface">
                        <a href="{{ route('home', absolute: false) }}#work">Work</a>
                        <a href="{{ route('home', absolute: false) }}#about">About</a>
                        <a href="{{ route('home', absolute: false) }}#experience">Experience</a>
                        <a href="{{ route('resume', absolute: false) }}/">Résumé</a>
                        <a href="{{ route('home', absolute: false) }}#contact">Contact</a>
                    </nav>
                </details>
            </div>
            </div>
        </header>
        {{ $slot }}
        <footer class="site-footer">
            <div class="footer-inner page-container">
                <a href="{{ route('home', absolute: false) }}" class="footer-name">{{ $profile['name'] }}</a>
                <p>{{ $profile['tagline'] }}</p>
                <a href="{{ route('resume.download', absolute: false) }}" download>Download résumé <span aria-hidden="true">↓</span></a>
            </div>
        </footer>
    </body>
</html>
