<?php

return [
    'url' => env('PORTFOLIO_URL'),
    'browser' => env('PORTFOLIO_BROWSER'),
    'contact' => [
        'phone' => env('PORTFOLIO_CONTACT_PHONE'),
        'email' => env('PORTFOLIO_CONTACT_EMAIL'),
    ],
    'content_directory' => base_path('content'),
    'output_directory' => base_path('dist'),
    'build_directory' => 'build',
    'font_file' => 'fonts/dm-sans-latin-variable.woff2',
    'font_license' => 'fonts/OFL.txt',
    'routes' => [
        'home' => 'index.html',
        'resume' => 'resume/index.html',
        'projects.show' => 'projects/{slug}/index.html',
    ],
    'entrypoints' => [
        'resources/css/portfolio.css',
        'resources/js/portfolio.js',
    ],
];
