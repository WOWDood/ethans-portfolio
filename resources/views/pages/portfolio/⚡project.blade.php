<?php

use App\Services\Portfolio\ContentRepository;
use App\Services\Portfolio\ImageProcessor;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::portfolio')] class extends Component
{
    #[Locked]
    public string $slug = '';

    protected ContentRepository $content;
    protected ImageProcessor $images;

    public function boot(ContentRepository $content, ImageProcessor $images): void
    {
        $this->content = $content;
        $this->images = $images;
    }

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render(): View
    {
        $content = $this->images->prepare($this->content->load());
        $index = array_search($this->slug, array_column($content['projects'], 'slug'), true);
        abort_if($index === false, 404);
        $project = $content['projects'][$index];
        $next = count($content['projects']) > 1 ? $content['projects'][($index + 1) % count($content['projects'])] : null;

        return $this->view(['profile' => $content['profile'], 'project' => $project, 'nextProject' => $next])->layoutData([
            'profile' => $content['profile'], 'pageTitle' => $project['title'].' — '.$content['profile']['name'],
            'pageDescription' => $project['outcome'],
        ]);
    }
}; ?>

@inject('dates', 'App\Services\Portfolio\ContentDates')
<main id="main" tabindex="-1" class="project-page page-container">
    <a class="back-link" href="{{ route('home', absolute: false) }}#work"><span aria-hidden="true">←</span> All projects</a>
    <header class="project-page-header">
        <p class="eyebrow">{{ ucfirst($project['kind']) }}@if ($project['date']) / {{ $dates->format($project['date']) }}@endif</p>
        <h1>{{ $project['title'] }}</h1>
        <p class="project-page-outcome">{{ $project['outcome'] }}</p>
    </header>
    @if ($project['image']['asset'])
        <figure class="project-page-image">
            <img src="{{ $project['image']['asset']['src'] }}" srcset="{{ $project['image']['asset']['srcset'] }}" sizes="(min-width: 1200px) 1120px, calc(100vw - 48px)" width="{{ $project['image']['asset']['width'] }}" height="{{ $project['image']['asset']['height'] }}" alt="{{ $project['image']['alt'] }}" fetchpriority="high" decoding="async">
        </figure>
    @else
        <div class="project-feature-art"><x-portfolio.project-art :slug="$project['slug']" :kind="$project['kind']" /></div>
    @endif
    <div class="project-page-grid">
        <aside class="project-facts" aria-label="Project details">
            <div><h2 class="eyebrow">My role</h2><p>{{ $project['role'] }}</p></div>
            @if ($project['context'] ?? null)<div><h2 class="eyebrow">Context</h2><p>{{ $project['context'] }}</p></div>@endif
            <div>
                <h2 class="eyebrow">{{ $project['kind'] === 'competition' ? 'Subjects' : 'Tools & methods' }}</h2>
                @if ($project['tech'])<p>{{ implode(' · ', $project['tech']) }}</p>
                @elseif ($project['kind'] === 'competition')<p>Science & mathematics</p>
                @elseif ($project['kind'] === 'hackathon')<p>Concept, system design, and presentation.</p>
                @else<p>Technology details not recorded.</p>@endif
            </div>
            @foreach ($project['links'] as $link)
                <a wire:key="project-external-{{ $loop->index }}" class="text-link" href="{{ $link['url'] }}">{{ $link['label'] }} <span aria-hidden="true">↗</span></a>
            @endforeach
        </aside>
        <div class="project-prose">{!! $project['html'] !!}</div>
    </div>
    @if ($nextProject)
        <nav class="next-project" aria-label="More projects">
            <p class="eyebrow">Up next</p>
            <a href="{{ route('projects.show', ['slug' => $nextProject['slug']], absolute: false) }}/">{{ $nextProject['title'] }} <span aria-hidden="true">↗</span></a>
        </nav>
    @endif
</main>
