import assert from 'node:assert/strict';
import test from 'node:test';
import { audioLevel, updateSpeakingState } from './conferenceSpeaking.ts';

test('audio level distinguishes silence from a voice sample', () => {
    assert.equal(audioLevel(new Uint8Array([128, 128, 128, 128])), 0);
    assert.ok(audioLevel(new Uint8Array([96, 160, 96, 160])) > 0.2);
});

test('speaker remains active briefly between words and then clears', () => {
    const speaking = updateSpeakingState(
        { speaking: false, lastVoiceAt: 0 },
        0.08,
        1000,
    );
    const held = updateSpeakingState(speaking, 0, 1500);
    const cleared = updateSpeakingState(held, 0, 1700);

    assert.deepEqual(speaking, {
        speaking: true,
        lastVoiceAt: 1000,
    });
    assert.equal(held.speaking, true);
    assert.equal(cleared.speaking, false);
});
