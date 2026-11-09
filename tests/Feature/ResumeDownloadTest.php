<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

test('serves only the generated public PDF or reports it missing', function (bool $built) {
    $directory = storage_path('framework/testing/download-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    config(['portfolio.output_directory' => $directory]);

    if ($built) {
        File::put($directory.'/ethan-oneil-azlan-resume.pdf', '%PDF-public-fixture');
    }

    try {
        $response = $this->get(route('resume.download'));

        if ($built) {
            $response->assertOk()->assertDownload('ethan-oneil-azlan-resume.pdf')
                ->assertHeader('Content-Type', 'application/pdf')->assertHeaderMissing('Set-Cookie');
        } else {
            $response->assertNotFound();
        }
    } finally {
        File::deleteDirectory($directory);
    }
})->with([true, false]);
