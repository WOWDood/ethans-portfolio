import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveBubbleCollision, stepSpring } from '../../resources/js/portfolio-motion.js';

test('settles at a resting target without overshooting', () => {
    let position = 0;
    let velocity = 0;
    for (let frame = 0; frame < 120; frame++) {
        ({ position, velocity } = stepSpring(position, velocity, 1, 1 / 60));
        assert.ok(position >= 0 && position <= 1);
    }
    assert.ok(Math.abs(position - 1) < 0.0001);
    assert.ok(Math.abs(velocity) < 0.0001);
});

test('reverses from the current position while carrying existing momentum', () => {
    const opening = stepSpring(0, 0, 1, 0.08);
    const reversing = stepSpring(opening.position, opening.velocity, 0, 0.001);
    assert.ok(Math.abs(reversing.position - opening.position) < 0.01);
    assert.ok(reversing.velocity > 0);
    const settled = stepSpring(reversing.position, reversing.velocity, 0, 2);
    assert.ok(Math.abs(settled.position) < 0.0001);
});

test('produces the same trajectory across different frame rates', () => {
    const whole = stepSpring(80, -30, 0, 0.2);
    const half = stepSpring(80, -30, 0, 0.1);
    const split = stepSpring(half.position, half.velocity, 0, 0.1);
    assert.ok(Math.abs(whole.position - split.position) < 0.000001);
    assert.ok(Math.abs(whole.velocity - split.velocity) < 0.000001);
});

test('colliding bubbles reverse their normal velocity and keep their tangential velocity', () => {
    const first = { x: 0, y: 0, radius: 20, contactRadius: 20, vx: 50, vy: -12 };
    const second = { x: 39, y: 0, radius: 20, contactRadius: 20, vx: -50, vy: 7 };

    resolveBubbleCollision(first, second);

    assert.equal(second.x - first.x, 40);
    assert.equal(first.vx, -42);
    assert.equal(second.vx, 42);
    assert.equal(first.vy, -12);
    assert.equal(second.vy, 7);
});

test('bubbles spawned at the same point push apart with mass-weighted momentum', () => {
    const first = { x: 0, y: 0, radius: 20, contactRadius: 16, vx: 0, vy: -24 };
    const second = { x: 0, y: 0, radius: 40, contactRadius: 40, vx: 0, vy: -24 };

    resolveBubbleCollision(first, second);

    assert.equal(second.x - first.x, 56);
    assert.ok(first.vx < 0 && second.vx > 0 && -first.vx > second.vx);
    assert.ok(Math.abs(first.vx * first.radius ** 2 + second.vx * second.radius ** 2) < 0.000001);
});

test('bubbles that are already separating keep their velocity', () => {
    const first = { x: 0, y: 0, radius: 20, contactRadius: 20, vx: -15, vy: 0 };
    const second = { x: 39, y: 0, radius: 20, contactRadius: 20, vx: 15, vy: 0 };

    resolveBubbleCollision(first, second);

    assert.equal(first.vx, -15);
    assert.equal(second.vx, 15);
});
