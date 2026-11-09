import { stepSpring } from './portfolio-motion.js';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const activeSprings = new Set();

function spring(initial, render, onRest = () => {}) {
    const values = initial.map((position) => ({ position, velocity: 0, target: position }));
    let frame = null;
    let previousTime = 0;

    function finish() {
        if (frame !== null) cancelAnimationFrame(frame);
        frame = null;
        values.forEach((value) => { value.position = value.target; value.velocity = 0; });
        render(values.map((value) => value.position));
        onRest();
    }

    function update(time) {
        const seconds = Math.min((time - previousTime) / 1000, 0.05);
        previousTime = time;
        let resting = true;
        values.forEach((value) => {
            Object.assign(value, stepSpring(value.position, value.velocity, value.target, seconds));
            resting &&= Math.abs(value.position - value.target) < 0.001 && Math.abs(value.velocity) < 0.01;
        });
        render(values.map((value) => value.position));
        if (resting) finish();
        else frame = requestAnimationFrame(update);
    }

    const controller = {
        to(targets, immediate = false) {
            targets.forEach((target, index) => { values[index].target = target; });
            if (immediate || reducedMotion.matches) { finish(); return; }
            if (frame === null) {
                previousTime = performance.now();
                frame = requestAnimationFrame(update);
            }
        },
        finish,
    };
    activeSprings.add(controller);
    return controller;
}

reducedMotion.addEventListener('change', (event) => {
    if (event.matches) activeSprings.forEach((controller) => controller.finish());
});

const themeButton = document.querySelector('[data-theme-toggle]');
const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)');
let savedTheme = null;
try {
    const stored = localStorage.getItem('portfolio-theme');
    savedTheme = ['light', 'dark'].includes(stored) ? stored : null;
} catch {}

function applyTheme(theme) {
    if (document.documentElement.dataset.theme !== theme) document.documentElement.dataset.theme = theme;
    themeButton?.setAttribute('aria-pressed', String(theme === 'dark'));
    const state = themeButton?.querySelector('[data-theme-state]');
    if (state) state.textContent = theme === 'dark' ? 'on' : 'off';
}
applyTheme(savedTheme ?? (preferredTheme.matches ? 'dark' : 'light'));
themeButton?.removeAttribute('hidden');
themeButton?.addEventListener('click', () => {
    savedTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    applyTheme(savedTheme);
    try { localStorage.setItem('portfolio-theme', savedTheme); } catch {}
});
preferredTheme.addEventListener('change', (event) => {
    if (savedTheme === null) applyTheme(event.matches ? 'dark' : 'light');
});

const hero = document.querySelector('.hero');
const heroHeader = document.querySelector('.home-page .site-header');
if (hero && heroHeader) {
    const headerOverlap = new IntersectionObserver(([entry]) => {
        heroHeader.toggleAttribute('data-over-hero', entry.isIntersecting);
    }, { rootMargin: `-${heroHeader.offsetHeight}px 0px 0px` });
    headerOverlap.observe(hero);
}

const mobileMenu = document.querySelector('[data-mobile-menu]');
if (mobileMenu) {
    const trigger = mobileMenu.querySelector('summary');
    const panel = mobileMenu.querySelector('nav');
    let wantsOpen = mobileMenu.open;
    trigger.setAttribute('aria-controls', panel.id);
    trigger.setAttribute('aria-expanded', String(wantsOpen));
    const menuMotion = spring([wantsOpen ? 1 : 0], ([progress]) => {
        panel.style.opacity = String(Math.max(0, Math.min(1, progress)));
        panel.style.transform = reducedMotion.matches ? 'none' : `translateY(${(1 - progress) * -8}px) scale(${0.96 + progress * 0.04})`;
    }, () => {
        if (!wantsOpen) mobileMenu.open = false;
    });

    function setMenu(open, returnFocus = false) {
        wantsOpen = open;
        trigger.setAttribute('aria-expanded', String(open));
        panel.inert = !open;
        if (open) mobileMenu.open = true;
        menuMotion.to([open ? 1 : 0]);
        if (returnFocus) trigger.focus();
    }
    trigger.addEventListener('click', (event) => { event.preventDefault(); setMenu(!wantsOpen); });
    mobileMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
    mobileMenu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && wantsOpen) { event.preventDefault(); setMenu(false, true); }
    });
    document.addEventListener('pointerdown', (event) => {
        if (wantsOpen && !mobileMenu.contains(event.target)) setMenu(false);
    });
}

const filters = document.querySelector('.project-filters');
const items = Array.from(document.querySelectorAll('[data-project-item]'));
const count = document.querySelector('[data-project-count]');
function initializeFilters() {
    filters.removeAttribute('hidden');
    const indicator = filters.querySelector('.filter-indicator');
    const buttons = Array.from(filters.querySelectorAll('[data-project-filter]'));
    const initial = filters.querySelector('[aria-pressed="true"]');
    const indicatorMotion = spring([initial.offsetLeft, initial.offsetWidth], ([left, width]) => {
        indicator.style.transform = `translateX(${left}px) scaleX(${Math.max(1, width)})`;
        indicator.style.borderRadius = `${22 / Math.max(1, width)}px / 22px`;
    });
    filters.dataset.enhanced = 'true';
    indicatorMotion.to([initial.offsetLeft, initial.offsetWidth], true);
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            let visible = 0;
            items.forEach((item) => {
                item.hidden = button.dataset.projectFilter !== 'all' && item.dataset.projectType !== button.dataset.projectFilter;
                visible += item.hidden ? 0 : 1;
            });
            buttons.forEach((option) => option.setAttribute('aria-pressed', String(option === button)));
            indicatorMotion.to([button.offsetLeft, button.offsetWidth]);
            if (count) count.textContent = `${visible} ${visible === 1 ? 'project' : 'projects'}`;
        });
    });
    new ResizeObserver(() => {
        const selected = filters.querySelector('[aria-pressed="true"]');
        indicatorMotion.to([selected.offsetLeft, selected.offsetWidth], true);
    }).observe(filters);
}

if (filters) {
    const nearbyProjects = new IntersectionObserver((entries, observer) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        observer.disconnect();
        initializeFilters();
    }, { rootMargin: '600px' });
    nearbyProjects.observe(filters.parentElement);
}

if (items.length) {
    const hoverPointer = window.matchMedia('(any-hover: hover) and (any-pointer: fine)');
    const projectEntrance = new IntersectionObserver((entries, observer) => {
        entries.forEach(({ target, isIntersecting }) => {
            if (!isIntersecting) return;
            if (!reducedMotion.matches) target.dataset.arrived = '';
            observer.unobserve(target);
        });
    }, { threshold: 0.2 });

    items.forEach((item) => {
        const card = item.querySelector('.project-entry');
        const visual = card.querySelector('.project-visual');
        projectEntrance.observe(visual);

        const tilt = spring([0, 0, 0], ([x, y, lift]) => {
            visual.style.setProperty('--project-x', x.toFixed(4));
            visual.style.setProperty('--project-y', y.toFixed(4));
            visual.style.setProperty('--project-lift', lift.toFixed(4));
        });
        const reset = () => tilt.to([0, 0, 0]);
        card.addEventListener('pointermove', (event) => {
            if (reducedMotion.matches || !hoverPointer.matches || event.pointerType === 'touch') return;
            const bounds = card.getBoundingClientRect();
            const x = Math.max(-1, Math.min(1, (event.clientX - bounds.left) / bounds.width * 2 - 1));
            const y = Math.max(-1, Math.min(1, (event.clientY - bounds.top) / bounds.height * 2 - 1));
            tilt.to([x, y, 1]);
        }, { passive: true });
        card.addEventListener('pointerleave', reset);
        card.addEventListener('pointercancel', reset);
        hoverPointer.addEventListener('change', reset);
        reducedMotion.addEventListener('change', (event) => {
            if (event.matches) {
                reset();
                visual.removeAttribute('data-arrived');
            }
        });
    });
}

const contactPhone = document.querySelector('[data-contact-phone]');
if (contactPhone) {
    const phone = atob(contactPhone.dataset.contactPhone);
    contactPhone.href = `tel:${phone}`;
    contactPhone.textContent = phone;
    contactPhone.hidden = false;
}

const contactEmail = document.querySelector('[data-contact-email]');
if (contactEmail) {
    contactEmail.href = `mailto:${atob(contactEmail.dataset.contactEmail)}`;
    contactEmail.hidden = false;
}

document.querySelectorAll('[data-print]').forEach((button) => {
    button.removeAttribute('hidden');
    button.addEventListener('click', () => window.print());
});
