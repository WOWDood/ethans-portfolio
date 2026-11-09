@props(['content', 'pdf' => false])
@inject('dates', 'App\Services\Portfolio\ContentDates')
@inject('metadata', 'App\Services\Portfolio\PageMetadata')

<article {{ $attributes->class(['resume-paper']) }}>
    <div class="resume-sheet">
    <header class="resume-header">
        <p class="eyebrow">Résumé</p>
        <h1>{{ $content['profile']['name'] }}</h1>
        <p class="resume-position">{{ $content['profile']['headline'] }}</p>
        <p class="resume-intro">{{ $content['profile']['bio'] }}</p>
        <div class="resume-links">
            @foreach ($content['profile']['links'] as $link)
                <a wire:key="resume-link-{{ $loop->index }}" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
            @endforeach
            @if (! $pdf || $metadata->origin())
                <a href="{{ $pdf ? $metadata->url('/#contact') : route('home', absolute: false).'#contact' }}">Contact</a>
            @endif
        </div>
    </header>
    <section class="resume-block">
        <h2>Experience</h2>
        <x-portfolio.experience-list :entries="$content['experience']['entries']" />
    </section>
    <section class="resume-block">
        <h2>Education</h2>
        <x-portfolio.education-list :entries="$content['education']['entries']" />
    </section>
    </div>
    <div class="resume-sheet">
    <section class="resume-block">
        <h2>Projects & competitions</h2>
        @foreach ($content['projects'] as $project)
            <div class="resume-project" wire:key="resume-project-{{ $project['slug'] }}">
                @php($projectPath = route('projects.show', ['slug' => $project['slug']], absolute: false).'/')
                <h3>@if (! $pdf || $metadata->origin())<a href="{{ $pdf ? $metadata->url($projectPath) : $projectPath }}">{{ $project['title'] }}</a>@else{{ $project['title'] }}@endif</h3>
                <p>{{ $project['outcome'] }}</p>
            </div>
        @endforeach
    </section>
    <section class="resume-block">
        <h2>Technical skills</h2>
        @foreach ($content['skills']['clusters'] as $cluster)
            <p class="resume-skill" wire:key="resume-skills-{{ $loop->index }}"><strong>{{ $cluster['title'] }}:</strong> {{ implode(', ', $cluster['items']) }}</p>
        @endforeach
        <p class="resume-skill"><strong>Languages:</strong> @foreach ($content['profile']['languages'] as $language){{ $language['name'] }} (speaking: {{ strtolower($language['speaking']) }}, writing: {{ strtolower($language['writing']) }})@unless ($loop->last); @endunless @endforeach</p>
    </section>
    <section class="resume-block">
        <h2>Certifications & learning</h2>
        <x-portfolio.credentials :credentials="$content['certifications']" />
    </section>
    <section class="resume-block">
        <h2>Leadership & roles</h2>
        @foreach ($content['leadership']['entries'] as $entry)
            <div class="resume-role" wire:key="resume-role-{{ $entry['id'] }}">
                <x-portfolio.badge :badge="$entry['badge'] ?? null" class="role-badge" sizes="40px" />
                <div>
                    <p><strong>{{ $entry['role'] }}</strong> · {{ $entry['organisation'] }} · {{ $dates->range($entry['start'], $entry['end'], $entry['ongoing']) }}@if ($entry['engagement']) · {{ $entry['engagement'] }}@endif</p>
                    @if ($entry['activities'] ?? [])
                        <p>{{ implode(' · ', $entry['activities']) }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </section>
    </div>
</article>
