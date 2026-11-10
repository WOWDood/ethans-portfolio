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
        $profile = $content['profile'];

        return $this->view(['profile' => $profile, 'content' => $content, 'projectKinds' => array_values(array_unique(array_column($content['projects'], 'kind')))])->layoutData(['profile' => $profile]);
    }
}; ?>

<main id="main" tabindex="-1">
    <div class="homepage-screen">
    <section aria-labelledby="intro-heading" class="hero">
        <div class="hero-atmosphere" aria-hidden="true">
            <div class="hero-light"></div>
            <div class="hero-studio-light"></div>
        </div>
        <svg class="hero-bubbles" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="bubble-glass" x2=".25" y2="1">
                    <stop stop-color="#343c40" stop-opacity=".88"/>
                    <stop offset="1" stop-color="#0c0e10" stop-opacity=".93"/>
                </linearGradient>
                <linearGradient id="bubble-edge" x2=".25" y2="1">
                    <stop stop-color="#f2f6f8" stop-opacity=".92"/>
                    <stop offset="1" stop-color="#b7c4ca" stop-opacity=".36"/>
                </linearGradient>
            </defs>
            <path class="bubble-lasso"/>
            <g class="bubble-template">
                <g class="bubble-flight">
                    <g class="bubble-shell">
                        <circle class="bubble-echo" r="39"/>
                        <circle class="bubble-ring" r="39"/>
                        <path class="bubble-highlight" d="M-25-23a34 34 0 0 1 24-11"/>
                    </g>
                    <text class="bubble-text" text-anchor="middle" dominant-baseline="central"></text>
                </g>
            </g>
            <circle class="bubble-cursor" r="4"/>
        </svg>
        <div class="hero-inner page-container">
            <div class="hero-copy">
                <p class="hero-name">{{ $profile['name'] }}</p>
                <h1 id="intro-heading">
                    @foreach ($profile['hero_lines'] as $line)
                        <span wire:key="hero-line-{{ $loop->index }}" @class(['hero-emphasis' => $loop->last])>{{ $line }}</span>
                    @endforeach
                </h1>
                <p class="hero-position">{{ $profile['headline'] }}</p>
                <div class="hero-meta">
                    <p class="hero-availability"><span class="status-dot" aria-hidden="true"></span>{{ $profile['availability']['primary'] }}</p>
                    <a href="{{ route('resume', absolute: false) }}/" class="hero-resume">View résumé <span aria-hidden="true">↗</span></a>
                </div>
                <a href="#work" class="hero-scroll">Explore my work <span aria-hidden="true">↓</span></a>
            </div>
            @if ($portrait = $profile['portrait']['asset'])
                @php($landscapePortrait = $profile['portrait']['landscape'] ?? false)
                <figure @class(['hero-portrait', 'hero-portrait-wide' => $landscapePortrait])>
                    <img src="{{ $portrait['src'] }}" srcset="{{ $portrait['srcset'] }}" sizes="{{ $landscapePortrait ? '(min-width: 768px) min(92vw, 164svh), max(100vw, 84svh)' : '(min-width: 768px) 110vw, 100vw' }}" width="{{ $portrait['width'] }}" height="{{ $portrait['height'] }}" alt="{{ $profile['portrait']['alt'] }}" fetchpriority="high" decoding="async">
                </figure>
            @endif
        </div>
    </section>

    <section id="work" aria-labelledby="work-heading" class="section-space page-container">
        <x-portfolio.section-heading eyebrow="Ideas into practice" title="Selected work." heading-id="work-heading" />
        <div class="project-controls">
            <div class="project-filters" role="group" aria-label="Filter projects" hidden>
                <span class="filter-indicator" aria-hidden="true"></span>
                <button type="button" data-project-filter="all" aria-pressed="true" aria-controls="projects-list">All</button>
                @foreach ($projectKinds as $kind)
                    <button type="button" wire:key="filter-{{ $kind }}" data-project-filter="{{ $kind }}" aria-pressed="false" aria-controls="projects-list">{{ ['project' => 'Projects', 'hackathon' => 'Hackathons', 'competition' => 'Competitions'][$kind] }}</button>
                @endforeach
            </div>
            <p data-project-count class="filter-count" aria-live="polite">{{ count($content['projects']) }} {{ count($content['projects']) === 1 ? 'project' : 'projects' }}</p>
        </div>
        <ul id="projects-list" class="projects-list">
            @foreach ($content['projects'] as $project)
                <li wire:key="project-entry-{{ $project['slug'] }}" data-project-item data-project-type="{{ $project['kind'] }}">
                    <x-portfolio.project-entry :project="$project" :number="str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)" :href="route('projects.show', ['slug' => $project['slug']], absolute: false).'/'" />
                </li>
            @endforeach
        </ul>
        @if (! $content['projects'])<p class="secondary-copy">No published projects yet.</p>@endif
    </section>

    <section id="about" aria-labelledby="about-heading" class="about-section section-space">
        <div class="about-panel page-container">
            <div>
                <x-portfolio.section-heading eyebrow="Introduction" :title="$profile['about']['heading']" heading-id="about-heading" />
                <p class="about-note">{{ implode(' ', $profile['focus_lines']) }}</p>
            </div>
            <div class="about-copy">
                <p class="about-lead">{{ $profile['bio'] }}</p>
                @foreach ($profile['about']['paragraphs'] as $paragraph)
                    <p wire:key="about-paragraph-{{ $loop->index }}" class="secondary-copy">{{ $paragraph }}</p>
                @endforeach
                <div class="availability-note">
                    <p class="availability-title"><span class="status-dot" aria-hidden="true"></span>{{ $profile['availability']['primary'] }}</p>
                    <p class="secondary-copy">{{ $profile['availability']['secondary'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section id="experience" aria-labelledby="experience-heading" class="experience-section section-space">
        <div class="page-container">
            <x-portfolio.section-heading eyebrow="Experience" title="Work, in the real world." heading-id="experience-heading" />
            <x-portfolio.experience-list :entries="$content['experience']['entries']" class="section-list" />
        </div>
    </section>

    <div class="background-section section-space">
    <div class="background-grid page-container">
    <section id="education" aria-labelledby="education-heading">
        <x-portfolio.section-heading eyebrow="Education" title="Building the foundations." heading-id="education-heading" />
        <x-portfolio.education-list :entries="$content['education']['entries']" class="section-list" />
    </section>

    <section id="skills" aria-labelledby="skills-heading">
            <x-portfolio.section-heading eyebrow="Tools & practice" title="What I work with." heading-id="skills-heading" />
            <div class="skill-clusters">
                @foreach ($content['skills']['clusters'] as $cluster)
                    <section class="skill-cluster" wire:key="skill-cluster-{{ $loop->index }}">
                        <h3>{{ $cluster['title'] }}</h3>
                        <ul>@foreach ($cluster['items'] as $item)<li wire:key="skill-{{ $loop->parent->index }}-{{ $loop->index }}">{{ $item }}</li>@endforeach</ul>
                    </section>
                @endforeach
            </div>
            <div class="language-list" aria-label="Languages">
                @foreach ($profile['languages'] as $language)
                    <p wire:key="language-{{ $loop->index }}"><strong>{{ $language['name'] }}</strong><span>Speaking: {{ $language['speaking'] }} · Writing: {{ $language['writing'] }}</span></p>
                @endforeach
            </div>
    </section>
    </div>
    </div>

    <section id="certifications" aria-labelledby="certifications-heading" class="section-space page-container">
        <div class="credential-heading">
            <x-portfolio.section-heading eyebrow="Continuing to learn" title="Certifications & learning." heading-id="certifications-heading" />
            @foreach ($profile['links'] as $link)
                @if ($link['label'] === 'Credly')<a class="credential-profile-link" href="{{ $link['url'] }}">View my Credly profile <span aria-hidden="true">↗</span></a>@endif
            @endforeach
        </div>
        <x-portfolio.credentials :credentials="$content['certifications']" class="section-list" />
    </section>

    <section id="leadership" aria-labelledby="leadership-heading" class="leadership-section section-space">
        <div class="page-container">
            <x-portfolio.section-heading eyebrow="Beyond the coursework" title="Roles I’ve taken on." heading-id="leadership-heading" />
            @inject('dates', 'App\Services\Portfolio\ContentDates')
            <div class="role-list">
                @foreach ($content['leadership']['entries'] as $entry)
                    <article wire:key="leadership-{{ $entry['id'] }}">
                        <x-portfolio.badge :badge="$entry['badge'] ?? null" class="role-badge" sizes="64px" />
                        <div>
                            <p class="entry-date">{{ $dates->range($entry['start'], $entry['end'], $entry['ongoing']) }}</p>
                            <h3>{{ $entry['role'] }}</h3>
                            <p class="entry-organisation">{{ $entry['organisation'] }}</p>
                            @if ($entry['engagement'])<p class="entry-engagement">{{ $entry['engagement'] }}</p>@endif
                            @if ($entry['activities'] ?? [])
                                <details class="event-clients role-activities">
                                    <summary>Activities</summary>
                                    <ul>
                                        @foreach ($entry['activities'] as $activity)
                                            <li>{{ $activity }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="contact" aria-labelledby="contact-heading" class="contact-section section-space">
        <div class="page-container">
            <div class="contact-panel">
                <div>
                    <x-portfolio.section-heading eyebrow="Let’s connect" :title="$profile['contact']['heading']" heading-id="contact-heading" />
                    <p class="secondary-copy contact-intro">{{ $profile['contact']['intro'] }}</p>
                </div>
                <div class="contact-options">
                    <div class="contact-actions">
                        @if ($email = config('portfolio.contact.email'))
                            <a class="primary-link" data-contact-email="{{ base64_encode($email) }}" hidden>Email me <span aria-hidden="true">↗</span></a>
                        @endif
                        @if ($phone = config('portfolio.contact.phone'))
                            <a class="contact-phone" data-contact-phone="{{ base64_encode($phone) }}" hidden>Call me</a>
                        @endif
                    </div>
                    @if ($profile['contact']['form_id'])
                        <form class="contact-form" action="https://formspree.io/f/{{ $profile['contact']['form_id'] }}" method="POST">
                            <div><label for="contact-name">Your name</label><input id="contact-name" name="name" autocomplete="name" required maxlength="120"></div>
                            <div><label for="contact-email">Your email</label><input id="contact-email" type="email" name="email" autocomplete="email" required maxlength="254"></div>
                            <div><label for="contact-message">What do you have in mind?</label><textarea id="contact-message" name="message" rows="5" required minlength="10" maxlength="5000"></textarea></div>
                            <div class="honeypot" aria-hidden="true"><label for="contact-company">Leave this field blank</label><input id="contact-company" name="_gotcha" tabindex="-1" autocomplete="off"></div>
                            <button type="submit" class="primary-link">Send message <span aria-hidden="true">↗</span></button>
                        </form>
                    @endif
                    <nav class="contact-links" aria-label="Professional profiles">
                        @foreach ($profile['links'] as $link)
                            <a wire:key="contact-link-{{ $loop->index }}" href="{{ $link['url'] }}">{{ $link['label'] }} <span aria-hidden="true">↗</span></a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </div>
    </section>
    </div>
    <div class="homepage-print"><x-portfolio.resume-content :content="$content" /></div>
</main>
