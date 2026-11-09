@props(['entries'])
@inject('dates', 'App\Services\Portfolio\ContentDates')

<div {{ $attributes->class(['experience-list']) }}>
    @foreach ($entries as $entry)
        <article class="timeline-entry" wire:key="experience-{{ $entry['id'] }}">
            <div class="timeline-meta">
                <p class="entry-date">{{ $dates->range($entry['start'], $entry['end'], $entry['ongoing']) }}</p>
                @if ($entry['engagement'])<p class="entry-engagement">{{ $entry['engagement'] }}</p>@endif
            </div>
            <div class="timeline-copy">
                <h3>{{ $entry['role'] }}</h3>
                <p class="entry-organisation">{{ $entry['employer'] }}</p>
                <ul class="experience-bullets">
                    @foreach ($entry['bullets'] as $bullet)
                        <li wire:key="experience-{{ $entry['id'] }}-bullet-{{ $loop->index }}">{{ $bullet }}</li>
                    @endforeach
                </ul>
                @if ($entry['event_highlight'] ?? null)
                    <p class="event-highlight"><strong>Event highlight</strong> · {{ $entry['event_highlight'] }}</p>
                @endif
                @if ($entry['clients'] ?? [])
                    <details class="event-clients">
                        <summary>Events supported</summary>
                        <p>{{ implode(' · ', $entry['clients']) }}</p>
                    </details>
                @endif
            </div>
        </article>
    @endforeach
</div>
