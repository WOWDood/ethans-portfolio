@props(['entries'])
@inject('dates', 'App\Services\Portfolio\ContentDates')

<div {{ $attributes->class(['education-list']) }}>
    @foreach ($entries as $entry)
        <article class="timeline-entry" wire:key="education-{{ $entry['id'] }}">
            <p class="entry-date">{{ $dates->range($entry['start'], $entry['end'], expected: $entry['expected']) }}</p>
            <div class="timeline-copy">
                <div class="education-institution">
                    <x-portfolio.badge :badge="$entry['badge'] ?? null" class="education-badge" :data-education="$entry['id']" sizes="48px" />
                    <div>
                        <h3>{{ $entry['qualification'] }}</h3>
                        <p class="entry-organisation">{{ $entry['institution'] }}</p>
                    </div>
                </div>
                @if ($entry['highlights'])
                    <ul class="education-highlights">
                        @foreach ($entry['highlights'] as $highlight)
                            <li wire:key="education-{{ $entry['id'] }}-highlight-{{ $loop->index }}">{{ $highlight }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </article>
    @endforeach
</div>
