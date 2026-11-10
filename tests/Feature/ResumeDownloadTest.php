<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

test('serves only the generated public PDF or reports it missing', function (bool $built, bool $preview) {
    $directory = storage_path('framework/testing/download-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    config(['portfolio.output_directory' => $directory]);

    if ($built) {
        File::put($directory.'/ethan-oneil-azlan-resume.pdf', '%PDF-public-fixture');
    }

    try {
        $response = $this->get(route('resume.download', $preview ? ['preview' => '1'] : []));

        if ($built) {
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeaderMissing('Set-Cookie');

            if ($preview) {
                $response->assertHeader('Content-Disposition', 'inline; filename=ethan-oneil-azlan-resume.pdf');
            } else {
                $response->assertDownload('ethan-oneil-azlan-resume.pdf');
            }
        } else {
            $response->assertNotFound();
        }
    } finally {
        File::deleteDirectory($directory);
    }
})->with([true, false])->with([true, false]);
