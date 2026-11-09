export function stepSpring(position, velocity, target, seconds, response = 26) {
    const displacement = position - target;
    const coefficient = velocity + response * displacement;
    const decay = Math.exp(-response * seconds);

    return {
        position: target + (displacement + coefficient * seconds) * decay,
        velocity: (velocity - response * coefficient * seconds) * decay,
    };
}
