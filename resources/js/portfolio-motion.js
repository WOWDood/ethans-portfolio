export function stepSpring(position, velocity, target, seconds, response = 26) {
    const displacement = position - target;
    const coefficient = velocity + response * displacement;
    const decay = Math.exp(-response * seconds);

    return {
        position: target + (displacement + coefficient * seconds) * decay,
        velocity: (velocity - response * coefficient * seconds) * decay,
    };
}

export function resolveBubbleCollision(first, second) {
    const dx = second.x - first.x;
    const dy = second.y - first.y;
    const distance = Math.hypot(dx, dy);
    const overlap = first.contactRadius + second.contactRadius - distance;
    if (overlap <= 0) return;

    const normalX = distance ? dx / distance : 1;
    const normalY = distance ? dy / distance : 0;
    const firstMass = first.radius ** 2;
    const secondMass = second.radius ** 2;
    const firstShare = secondMass / (firstMass + secondMass);
    const secondShare = 1 - firstShare;

    first.x -= normalX * overlap * firstShare;
    first.y -= normalY * overlap * firstShare;
    second.x += normalX * overlap * secondShare;
    second.y += normalY * overlap * secondShare;

    const separatingSpeed = (second.vx - first.vx) * normalX + (second.vy - first.vy) * normalY;
    const rebound = Math.max(0, -separatingSpeed * 0.84);
    const spawnPush = overlap > Math.min(first.contactRadius, second.contactRadius) * 0.35 ? 44 : 0;
    const velocityChange = Math.max(rebound, spawnPush) - separatingSpeed;
    if (velocityChange <= 0) return;

    first.vx -= velocityChange * firstShare * normalX;
    first.vy -= velocityChange * firstShare * normalY;
    second.vx += velocityChange * secondShare * normalX;
    second.vy += velocityChange * secondShare * normalY;
}
