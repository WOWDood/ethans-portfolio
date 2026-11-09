@props(['credentials'])
@inject('dates', 'App\Services\Portfolio\ContentDates')

<div {{ $attributes->class(['credential-groups']) }}>
    @foreach ($credentials['themes'] as $theme)
        @php($entries = array_filter($credentials['entries'], fn (array $entry): bool => $entry['theme'] === $theme))
        @if ($entries)
            <section class="credential-group" wire:key="credential-theme-{{ $loop->index }}">
                <h3>{{ $theme }}</h3>
                <ul>
                    @foreach ($entries as $entry)
                        <li class="credential-entry" wire:key="credential-{{ $entry['id'] }}">
                            <x-portfolio.badge :badge="$entry['badge'] ?? null" class="credential-badge" />
                            <div class="credential-copy">
                            <h4>{{ $entry['title'] }}</h4>
                            <p class="credential-meta">{{ $entry['issuer'] ?? 'Issuer unconfirmed' }}@if ($entry['date']) <span aria-hidden="true">·</span> {{ $dates->format($entry['date']) }}@endif</p>
                            @if ($entry['provider'])<p class="credential-provider">Training: {{ $entry['provider'] }}</p>@endif
                            @if ($entry['status'] !== 'Completed')<p class="credential-status">{{ $entry['status'] }}</p>@endif
                            @if ($entry['expires'] ?? null)<p class="credential-expiry">{{ $entry['expires'] < now('Asia/Kuala_Lumpur')->toDateString() ? 'Expired' : 'Expires' }} {{ $dates->format($entry['expires']) }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach
</div>
