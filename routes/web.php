<?php

use App\Http\Controllers\Portfolio\ResumeDownloadController;
use App\Http\Middleware\PortfolioPage;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::portfolio.home')
    ->withoutMiddleware('web')
    ->middleware(PortfolioPage::class)
    ->name('home');

Route::livewire('/projects/{slug}', 'pages::portfolio.project')
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->withoutMiddleware('web')
    ->middleware(PortfolioPage::class)
    ->name('projects.show');

Route::livewire('/resume', 'pages::portfolio.resume')
    ->withoutMiddleware('web')
    ->middleware(PortfolioPage::class)
    ->name('resume');

Route::get('/ethan-oneil-azlan-resume.pdf', ResumeDownloadController::class)
    ->withoutMiddleware('web')
    ->name('resume.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
