<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $profile['name'] }}</title>
    <style>
        @if ($fontData)
            @font-face { font-family: 'DM Sans'; src: url(data:font/woff2;base64,{{ $fontData }}) format('woff2'); font-weight: 100 1000; }
        @endif
        * { box-sizing: border-box; }
        body { margin: 0; width: 1200px; height: 630px; overflow: hidden; background: #151515; color: #fafafa; font-family: 'DM Sans', Arial, sans-serif; padding: 54px 64px; }
        main { height: 100%; border: 1px solid #636363; padding: 46px 52px; position: relative; }
        .name { font-size: 18px; font-weight: 650; letter-spacing: 3px; text-transform: uppercase; margin: 0 0 36px; }
        h1 { font-size: 81px; line-height: 1; font-weight: 700; letter-spacing: -5px; margin: 0; }
        h1 span:last-child { font-weight: 400; color: #dedede; }
        .headline { font-size: 23px; color: #dedede; margin: 29px 0 0; }
        .mark { position: absolute; top: 36px; right: 50px; font-size: 46px; font-weight: 700; letter-spacing: -3px; }
    </style>
</head>
<body>
    <main>
        <p class="name">{{ $profile['name'] }}</p>
        <div class="mark" aria-hidden="true">e.</div>
        <h1>@foreach ($profile['hero_lines'] as $line)<span>{{ $line }}</span>@unless ($loop->last)<br>@endunless @endforeach</h1>
        <p class="headline">{{ $profile['headline'] }}</p>
    </main>
</body>
</html>
