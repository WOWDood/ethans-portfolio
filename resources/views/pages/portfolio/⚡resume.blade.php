<?php

use App\Services\Portfolio\ContentRepository;
use App\Services\Portfolio\ImageProcessor;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::portfolio')] class extends Component
{
    protected ContentRepository $content;

    protected ImageProcessor $images;

    public function boot(ContentRepository $content, ImageProcessor $images): void
    {
        $this->content = $content;
        $this->images = $images;
    }

    public function render(): View
    {
        $content = $this->images->prepare($this->content->load());

        return $this->view(['content' => $content])->layoutData([
            'profile' => $content['profile'], 'pageTitle' => 'Résumé — '.$content['profile']['name'],
            'pageDescription' => 'Experience, education, projects, and skills of '.$content['profile']['name'].'.',
        ]);
    }
}; ?>

<main id="main" tabindex="-1" class="resume-page page-container">
    <div class="resume-toolbar print-hide">
        <a href="{{ route('home', absolute: false) }}" class="back-link"><span aria-hidden="true">←</span> Back to portfolio</a>
        <div class="resume-actions">
            <a href="{{ route('resume.download', absolute: false) }}" class="primary-link" download>Download PDF <span aria-hidden="true">↓</span></a>
            <button type="button" data-share-resume="{{ route('resume', absolute: false) }}/" hidden class="primary-link">Share résumé</button>
            <button type="button" data-print hidden class="primary-link">Print</button>
            <button type="button" data-resume-view aria-controls="resume-pdf-preview resume-text" hidden class="primary-link">Show PDF preview</button>
        </div>
        <p class="resume-share-status" data-resume-share-status role="status"></p>
        <input class="resume-share-link" data-resume-share-link type="url" aria-label="Résumé link" readonly hidden>
    </div>
    <iframe id="resume-pdf-preview" class="resume-pdf-preview print-hide" data-resume-preview="{{ route('resume.download', ['preview' => '1'], absolute: false) }}" title="Résumé PDF preview" hidden></iframe>
    <div id="resume-text" class="resume-text">
        <x-portfolio.resume-content :content="$content" />
    </div>
</main>
