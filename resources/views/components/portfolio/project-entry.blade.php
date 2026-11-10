@props(['project', 'number' => '01', 'href' => null])
@inject('dates', 'App\Services\Portfolio\ContentDates')

<article {{ $attributes->class(['project-entry']) }} data-project="{{ $project['slug'] }}" aria-labelledby="project-{{ $project['slug'] }}">
    <div class="project-visual">
        @if ($project['image']['asset'] ?? null)
            <img src="{{ $project['image']['asset']['src'] }}" srcset="{{ $project['image']['asset']['srcset'] }}" sizes="(min-width: 900px) 440px, calc(100vw - 48px)" width="{{ $project['image']['asset']['width'] }}" height="{{ $project['image']['asset']['height'] }}" alt="{{ $project['image']['alt'] }}" loading="lazy" decoding="async">
        @else
            <x-portfolio.project-art :slug="$project['slug']" :kind="$project['kind']" />
        @endif
    </div>
    <div class="project-copy">
        <div class="project-kicker">
            <p class="eyebrow"><span class="project-number" aria-hidden="true">{{ $number }}</span>{{ ucfirst($project['kind']) }}@if ($project['date']) <span aria-hidden="true"> · </span>{{ $dates->format($project['date']) }}@endif</p>
            @if ($href)<span class="project-arrow" aria-hidden="true">↗</span>@endif
        </div>
        <h3 id="project-{{ $project['slug'] }}" class="project-title">
            @if ($href)<a href="{{ $href }}">{{ $project['title'] }}</a>@else{{ $project['title'] }}@endif
        </h3>
        <p class="project-outcome">{{ $project['outcome'] }}</p>
        <div class="project-contribution">
            <p class="eyebrow">My contribution</p>
            <p>{{ $project['role'] }}</p>
        </div>
        @if ($project['tech'])
            <ul class="tech-list" aria-label="Tools and methods">
                @foreach ($project['tech'] as $technology)
                    <li wire:key="project-{{ $project['slug'] }}-tech-{{ $loop->index }}">{{ $technology }}</li>
                @endforeach
            </ul>
        @endif
        @if ($project['context'] ?? null)<p class="project-context">{{ $project['context'] }}</p>@endif
    </div>
</article>
