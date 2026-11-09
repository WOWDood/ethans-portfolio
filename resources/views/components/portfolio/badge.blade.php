@props(['badge', 'sizes' => '80px'])

@if ($asset = $badge['asset'] ?? null)
    <div {{ $attributes->class(['portfolio-badge']) }}>
        <img src="{{ $asset['src'] }}" @if ($asset['srcset'] ?? null) srcset="{{ $asset['srcset'] }}" sizes="{{ $sizes }}" @endif width="{{ $asset['width'] }}" height="{{ $asset['height'] }}" alt="{{ $badge['alt'] }}" loading="lazy" decoding="async">
    </div>
@endif
