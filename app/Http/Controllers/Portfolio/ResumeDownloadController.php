<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResumeDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $path = config()->string('portfolio.output_directory').'/ethan-oneil-azlan-resume.pdf';
        abort_unless(is_file($path), 404, 'Build the portfolio to generate the public résumé.');

        $disposition = $request->query('preview') === '1' ? 'inline' : 'attachment';

        return response()->download($path, 'ethan-oneil-azlan-resume.pdf', ['Content-Type' => 'application/pdf'], $disposition);
    }
}
