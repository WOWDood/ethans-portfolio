import test from 'node:test';
import assert from 'node:assert/strict';
import { stepSpring } from '../../resources/js/portfolio-motion.js';

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
