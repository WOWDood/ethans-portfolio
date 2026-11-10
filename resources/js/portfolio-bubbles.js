import { resolveBubbleCollision } from './portfolio-motion.js';

const bubblePhrases = ['I PLAY', 'WITH', 'CODE'];
const tapRadius = 39;
const maxBubbles = 16;

export function initializeHeroBubbles(hero, reducedMotion) {
    const layer = hero.querySelector('.hero-bubbles');
    if (!layer || typeof layer.animate !== 'function') return;

    const cursor = layer.querySelector('.bubble-cursor');
    const outline = layer.querySelector('.bubble-lasso');
    const template = layer.querySelector('.bubble-template');
    const flights = new Set();
    let frame = null;
    let previousTime = 0;
    let gesture = null;
    let phraseIndex = 0;

    const isBackground = (event) => !event.target.closest('.hero-copy, a, button');
    const position = (event) => {
        const bounds = hero.getBoundingClientRect();
        return { x: Math.round(event.clientX - bounds.left), y: Math.round(event.clientY - bounds.top), time: event.timeStamp };
    };
    const distance = (start, end) => Math.hypot(end.x - start.x, end.y - start.y);

    function trackCursor(point) {
        cursor.setAttribute('transform', `translate(${point.x} ${point.y})`);
    }

    function endGesture() {
        const ended = gesture;
        gesture = null;
        hero.removeAttribute('data-lasso-active');
        outline.removeAttribute('d');
        if (hero.hasPointerCapture(ended.pointerId)) hero.releasePointerCapture(ended.pointerId);
        return ended;
    }

    function lassoPath(points) {
        const [start, ...rest] = points;
        let path = `M${start.x} ${start.y}`;

        for (let index = 0; index < rest.length - 1; index++) {
            const point = rest[index];
            const next = rest[index + 1];
            path += ` Q${point.x} ${point.y} ${(point.x + next.x) / 2} ${(point.y + next.y) / 2}`;
        }

        const end = rest.at(-1);
        return end ? `${path} L${end.x} ${end.y}` : path;
    }

    function launchVelocity(points) {
        const end = points.at(-1);
        const start = points.findLast((point) => point.time <= end.time - 45) ?? points[0];
        const elapsed = Math.max(0.02, (end.time - start.time) / 1000);
        const travel = distance(start, end);
        const momentum = travel ? Math.min(0.24 / elapsed, 100 / travel) : 0;

        return { vx: (end.x - start.x) * momentum, vy: (end.y - start.y) * momentum - 24 };
    }

    function renderBubble(body) {
        body.element.setAttribute('transform', `translate(${body.x} ${body.y}) scale(${body.contactRadius / body.radius})`);
    }

    function moveBubbles(now) {
        frame = null;
        const seconds = Math.min(0.032, Math.max(0, (now - previousTime) / 1000));
        previousTime = now;
        const drag = Math.exp(-seconds * 1.4);
        const active = [...flights];

        for (const body of active) {
            const age = Math.max(0, (now - body.born) / 1000);
            body.contactRadius = body.radius * (1 - 0.18 * Math.exp(-age * 12) * Math.cos(age * 24));
            body.vx *= drag;
            body.vy = (body.vy + 24) * drag - 24;
            body.x += body.vx * seconds;
            body.y += body.vy * seconds;
        }

        for (let first = 0; first < active.length; first++) {
            for (let second = first + 1; second < active.length; second++) {
                resolveBubbleCollision(active[first], active[second]);
            }
        }

        active.forEach(renderBubble);
        if (flights.size) frame = requestAnimationFrame(moveBubbles);
    }

    function releaseBubble(points, selection) {
        const xValues = points.map(({ x }) => x);
        const yValues = points.map(({ y }) => y);
        const left = Math.min(...xValues);
        const right = Math.max(...xValues);
        const top = Math.min(...yValues);
        const bottom = Math.max(...yValues);
        const center = { x: (left + right) / 2, y: (top + bottom) / 2 };
        const radius = selection ? Math.max(12, ...points.map((point) => distance(center, point))) : tapRadius;
        const x = selection ? center.x : Math.max(radius, Math.min(hero.clientWidth - radius, center.x));
        const y = selection ? center.y : Math.max(radius + 35, Math.min(hero.clientHeight - radius, center.y));
        const bubble = template.cloneNode(true);
        bubble.classList.remove('bubble-template');
        bubble.querySelector('.bubble-shell').setAttribute('transform', `scale(${radius / tapRadius})`);
        const label = bubble.querySelector('.bubble-text');
        label.textContent = bubblePhrases[phraseIndex];
        label.style.fontSize = `${Math.min(22, radius / 3.2)}px`;
        layer.insertBefore(bubble, cursor);
        phraseIndex = (phraseIndex + 1) % bubblePhrases.length;

        if (selection) layer.insertBefore(selection, bubble);
        const close = selection?.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 260, easing: 'ease-out', fill: 'forwards' });
        const fade = bubble.querySelector('.bubble-flight').animate([
            { opacity: 0.35, easing: 'ease-out' },
            { opacity: 1, offset: 0.09 },
            { opacity: 1, offset: 0.75, easing: 'ease-in' },
            { opacity: 0 },
        ], { duration: 2400 });
        const echo = bubble.querySelector('.bubble-echo').animate([
            { transform: 'scale(1)', opacity: 0.4 },
            { transform: 'scale(1.15)', opacity: 0 },
        ], { duration: 420, delay: 110, easing: 'ease-out' });

        const body = { x, y, radius, contactRadius: radius * 0.82, ...launchVelocity(points), element: bubble, born: performance.now(), fade };
        renderBubble(body);
        if (flights.size >= maxBubbles) flights.values().next().value.fade.cancel();
        flights.add(body);
        if (frame === null) {
            previousTime = body.born;
            frame = requestAnimationFrame(moveBubbles);
        }
        const cleanUp = () => {
            close?.cancel();
            selection?.remove();
            echo.cancel();
            bubble.remove();
            flights.delete(body);
            if (!flights.size && frame !== null) {
                cancelAnimationFrame(frame);
                frame = null;
            }
        };
        fade.finished.then(cleanUp, cleanUp);
    }

    hero.addEventListener('pointerdown', (event) => {
        if (reducedMotion.matches || gesture || !event.isPrimary || event.button !== 0 || !isBackground(event)) return;

        const start = position(event);
        gesture = { pointerId: event.pointerId, points: [start], length: 0 };

        if (event.pointerType !== 'touch') {
            event.preventDefault();
            hero.setPointerCapture(event.pointerId);
            trackCursor(start);
            hero.setAttribute('data-lasso-active', '');
        }
    });

    hero.addEventListener('pointermove', (event) => {
        if (gesture?.pointerId !== event.pointerId || event.pointerType === 'touch') return;

        const point = position(event);
        trackCursor(point);
        const step = distance(gesture.points.at(-1), point);
        if (step < 4) return;

        if (gesture.points.length >= 240) gesture.points = gesture.points.filter((_, index) => index % 2 === 0);
        gesture.points.push(point);
        gesture.length += step;
        outline.setAttribute('d', lassoPath(gesture.points));
    }, { passive: true });

    hero.addEventListener('pointerup', (event) => {
        if (gesture?.pointerId !== event.pointerId) return;

        const { points, length } = endGesture();
        const point = position(event);
        if (event.pointerType === 'touch') {
            if (distance(points[0], point) <= 12) releaseBubble([point], null);
            return;
        }

        if (length + distance(points.at(-1), point) < 18) return;
        points.push(point);
        const selection = outline.cloneNode();
        selection.classList.add('bubble-lasso-selection');
        selection.setAttribute('d', `${lassoPath(points)} Z`);
        releaseBubble(points, selection);
    });

    function cancelGesture(event) {
        if (gesture?.pointerId === event.pointerId) endGesture();
    }

    hero.addEventListener('pointercancel', cancelGesture);
    hero.addEventListener('lostpointercapture', cancelGesture);
    reducedMotion.addEventListener('change', () => {
        if (gesture) endGesture();
        flights.forEach((body) => body.fade.cancel());
    });
}
