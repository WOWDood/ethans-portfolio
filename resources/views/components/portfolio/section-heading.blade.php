@props(['eyebrow', 'title', 'headingId'])

<header {{ $attributes->class(['section-heading-row']) }}>
    <p class="eyebrow">{{ $eyebrow }}</p>
    <h2 id="{{ $headingId }}" class="section-heading">{{ $title }}</h2>
</header>
