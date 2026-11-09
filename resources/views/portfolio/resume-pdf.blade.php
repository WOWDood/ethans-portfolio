<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $content['profile']['name'] }} - Résumé</title>
    <style>
        @if ($fontData)
            @font-face { font-family: 'DM Sans'; src: url(data:font/woff2;base64,{{ $fontData }}) format('woff2'); font-weight: 100 1000; }
        @endif
        {!! file_get_contents(resource_path('css/resume-print.css')) !!}
    </style>
</head>
<body><x-portfolio.resume-content :content="$content" :pdf="true" /></body>
</html>
