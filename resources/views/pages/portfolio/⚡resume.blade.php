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
            <a href="{{ route('resume.download', absolute: false) }}" class="primary-link" download>Download résumé PDF <span aria-hidden="true">↓</span></a>
            <button type="button" data-print hidden class="primary-link">Print or save PDF <span aria-hidden="true">↗</span></button>
        </div>
    </div>
    <x-portfolio.resume-content :content="$content" />
</main>
