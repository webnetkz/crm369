export type SpeakingState = {
    speaking: boolean;
    lastVoiceAt: number;
};

export type SpeakingOptions = {
    threshold: number;
    holdMs: number;
};

const defaultOptions: SpeakingOptions = {
    threshold: 0.025,
    holdMs: 650,
};

export function audioLevel(samples: Uint8Array): number {
    if (samples.length === 0) {
        return 0;
    }

    let sumOfSquares = 0;

    for (const sample of samples) {
        const normalizedSample = (sample - 128) / 128;
        sumOfSquares += normalizedSample * normalizedSample;
    }

    return Math.sqrt(sumOfSquares / samples.length);
}

export function updateSpeakingState(
    currentState: SpeakingState,
    level: number,
    now: number,
    options: SpeakingOptions = defaultOptions,
): SpeakingState {
    if (level >= options.threshold) {
        return {
            speaking: true,
            lastVoiceAt: now,
        };
    }

    return {
        speaking:
            currentState.lastVoiceAt > 0 &&
            now - currentState.lastVoiceAt <= options.holdMs,
        lastVoiceAt: currentState.lastVoiceAt,
    };
}
