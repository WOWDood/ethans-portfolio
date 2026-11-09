const bubblePhrases = ['I PLAY', 'WITH', 'CODE'];
const tapRadius = 39;

export function initializeHeroBubbles(hero, reducedMotion) {
    const layer = hero.querySelector('.hero-bubbles');
    if (!layer || typeof layer.animate !== 'function') return;

    const cursor = layer.querySelector('.bubble-cursor');
    const outline = layer.querySelector('.bubble-lasso');
    const template = layer.querySelector('.bubble-template');
    const flights = new Set();
    let gesture = null;
    let phraseIndex = 0;

    const isBackground = (event) => !event.target.closest('.hero-copy, a, button');
    const position = (event) => {
        const bounds = hero.getBoundingClientRect();
        return { x: Math.round(event.clientX - bounds.left), y: Math.round(event.clientY - bounds.top) };
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
        return `M${points.map(({ x, y }) => `${x} ${y}`).join(' L')}`;
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
        bubble.setAttribute('transform', `translate(${x} ${y})`);
        bubble.querySelector('.bubble-ring').setAttribute('r', radius);
        const label = bubble.querySelector('.bubble-text');
        label.textContent = bubblePhrases[phraseIndex];
        label.style.fontSize = `${Math.min(20, radius / 3)}px`;
        layer.insertBefore(bubble, cursor);
        phraseIndex = (phraseIndex + 1) % bubblePhrases.length;

        if (selection) layer.insertBefore(selection, bubble);
        const close = selection?.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 360, fill: 'forwards' });
        const flight = bubble.querySelector('.bubble-flight').animate([
            { transform: 'translateY(0) scale(0.24)', opacity: 0.65, easing: 'ease-out' },
            { transform: 'translateY(-3px) scale(1.17)', opacity: 1, offset: 0.10, easing: 'ease-in-out' },
            { transform: 'translateY(-6px) scale(0.91)', opacity: 1, offset: 0.18, easing: 'ease-in-out' },
            { transform: 'translateY(-9px) scale(1.045)', opacity: 1, offset: 0.24, easing: 'ease-out' },
            { transform: 'translateY(-15px) scale(1)', opacity: 1, offset: 0.32 },
            { transform: 'translateY(-45px) scale(1)', opacity: 1, offset: 0.76 },
            { transform: 'translateY(-60px) scale(1)', opacity: 0 },
        ], { duration: 1500 });
        const popScale = radius / tapRadius;
        const pop = bubble.querySelector('.bubble-rays').animate([
            { transform: `scale(${popScale * 1.1})`, opacity: 0 },
            { transform: `scale(${popScale * 1.4})`, opacity: 1, offset: 0.24 },
            { transform: `scale(${popScale * 2.3})`, opacity: 0 },
        ], { duration: 390, delay: 1070, easing: 'ease-out' });

        flights.add(flight);
        const cleanUp = () => {
            close?.cancel();
            selection?.remove();
            pop.cancel();
            bubble.remove();
            flights.delete(flight);
        };
        flight.finished.then(cleanUp, cleanUp);
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
        flights.forEach((flight) => flight.cancel());
    });
}
