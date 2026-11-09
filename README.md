# Ethan’s portfolio

Laravel, Livewire, and Blade compose this site at build time. Deploy **only `dist/`**: it contains ordinary HTML, CSS, JavaScript, images, and the public résumé. No PHP or database is needed on the host.

## Build and preview

Use PHP 8.4 with GD, EXIF, and DOM support, the installed Composer/Node packages, and Chrome or Chromium. The PDF and sharing card are rendered with an isolated temporary browser profile. Common browser locations are detected; set `PORTFOLIO_BROWSER` in `.env` if yours is elsewhere.

```sh
php artisan site:build
```

This validates content, compiles assets, processes images, generates the PDF and sharing card, checks internal links, and writes `dist/`. A failed validation or rendering step preserves the previous export. `--skip-assets` reuses the compiled CSS/JS; it still rebuilds content and the PDF.

Herd serves the Laravel preview. To review the actual export, serve `dist/` with any static file server that supports directory indexes. Deploy at the domain root; subfolder hosting is not supported.

## Update content

| Change | Edit |
| --- | --- |
| Bio, links, availability, portrait, contact | `content/profile.yml` |
| Jobs and tightened experience bullets | `content/experience.yml` |
| Education | `content/education.yml` |
| Technical skill clusters | `content/skills.yml` |
| Credentials and honest completion status | `content/certifications.yml` |
| Leadership and occasional roles | `content/leadership.yml` |
| Projects | One Markdown file per project in `content/projects/` |

**Add a project:** copy an existing project Markdown file to a new lowercase-hyphenated filename, update its front matter and write-up, and set `published: true`. Lower `order` numbers appear first. Keep dates quoted and only as precise as you know: `'2026'`, `'2026-11'`, or `'2026-11-07'`. Use `null` for unknown dates. The filename becomes the detail-page URL automatically. Keep `tech_notes` when tools are unknown or not applicable.

**Add images:** put JPEG, PNG, or WebP originals in `content/assets/`. Set `image.source` to the filename and provide `image.alt`. Markdown body images use `![Descriptive alt text](filename.jpg)`. Builds crop project images to 3:2, resize and re-encode WebP, and reject exported EXIF/XMP/ICC metadata. Originals are never copied into `dist/`. Visually review screenshots or certificates for private text before using them; metadata removal cannot erase text visible in an image.

**Change your bio:** edit `bio` and, if needed, `about.paragraphs` in `content/profile.yml`, then run the build.

**Add a credential badge:** save its artwork in `content/assets/`, then add `badge: { source: filename.png, alt: Descriptive credential badge name }` to its entry in `content/certifications.yml`. The build trims transparent margins and fits the complete artwork inside square WebP assets. An optional `expires: '2030-07-24'` must be a full date; expiry labels are calculated at build time in Asia/Kuala_Lumpur. Candidate designations use `kind: candidate` and remain distinct from certifications. Put public profile links, including Credly, in `content/profile.yml`.

**Regenerate the résumé:** update the content files, then run `php artisan site:build`. The resulting `dist/ethan-oneil-azlan-resume.pdf` is generated fresh from the validated public content. Do not replace it with the original résumé PDF. The résumé page and the home page also print as a two-page A4 résumé with the current content. Additional future content may require print-layout adjustments.

**Swap your photo:** add the replacement to `content/assets/`, update `portrait.source` and `portrait.alt` in `profile.yml`, and build. `focal_point: [0.5, 0.06]` controls the horizontal/vertical crop position between 0 and 1. Review the arch crop after changing the photo.

## Privacy and contact

Keep phone numbers, home addresses, and recipient email addresses out of public content. Editorial `source`, `source_bullets`, and `notes` do not reach the site or PDF. Public HTML receives an additional exposed-email/phone check after component state is removed.

Formspree is the recommended static-host contact option. Set its delivery address in the provider’s dashboard; put only its public endpoint ID in `contact.form_id`. Until configured, the site displays LinkedIn and GitHub. No visitor messages are stored by this application. A configured form submits directly to Formspree and uses its hosted success/error flow and spam controls. Alternatives are a host’s form service or a JavaScript-assembled mail link; the latter is weaker protection against scrapers. Hosting accounts and form-service setup are separate from building this repository.

## Domain and search

Until you choose a domain, leave `PORTFOLIO_URL` empty. Preview builds use `noindex, nofollow`, disallow crawling, and emit an empty sitemap. They have no invented canonical URL.

Once ready, set the final HTTPS origin in `.env`, for example `PORTFOLIO_URL=https://your-domain.com`, then rebuild. This enables canonical URLs, absolute Open Graph/Twitter URLs, Person schema URLs, and the sitemap. Every project and the résumé page gets its own title and description. No phone, address, or email is added to structured data. Publishing at a new domain requires a fresh build.

## Checks

```sh
php vendor/bin/pest --compact tests/Feature/BuildSiteTest.php tests/Feature/PortfolioMetadataTest.php tests/Feature/BrowserArtifactsTest.php tests/Feature/ResumeDownloadTest.php tests/Feature/PortfolioPageTest.php tests/Feature/ProjectPageTest.php tests/Feature/Services/Portfolio tests/Unit/Services/Portfolio/ContentDatesTest.php
php vendor/bin/phpstan analyse --memory-limit=512M
```

The export allowlist excludes starter-kit authentication, dashboards, original PDFs, source images, and application files. Browser checks should use `dist/` and confirm keyboard navigation, the theme/menu/filters, downloads, and no Livewire network requests. Recheck Lighthouse after changing content or hosting; caching and compression depend on the host.
