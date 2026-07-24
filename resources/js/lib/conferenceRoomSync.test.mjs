import assert from 'node:assert/strict';
import test from 'node:test';
import { processConferenceRoomSync } from './conferenceRoomSync.ts';

test('conference messages and cursors are committed when a WebRTC signal fails', async () => {
    const processed = [];
    const errors = [];
    let signalCursor = 0;
    let messageCursor = 0;

    const failedSignals = await processConferenceRoomSync(
        {
            participants: ['first', 'second'],
            signals: ['invalid-offer', 'valid-candidate'],
            messages: ['shared-message'],
            signal_cursor: 12,
            message_cursor: 7,
        },
        {
            reconcileParticipants(participants) {
                processed.push(`participants:${participants.join(',')}`);
            },
            async appendMessages(messages) {
                processed.push(`messages:${messages.join(',')}`);
            },
            setSignalCursor(cursor) {
                signalCursor = cursor;
            },
            setMessageCursor(cursor) {
                messageCursor = cursor;
            },
            async handleSignal(signal) {
                processed.push(`signal:${signal}`);

                if (signal === 'invalid-offer') {
                    throw new Error('Unsupported candidate');
                }
            },
            onSignalError(signal) {
                errors.push(signal);
            },
        },
    );

    assert.deepEqual(failedSignals, ['invalid-offer']);
    assert.equal(signalCursor, 12);
    assert.equal(messageCursor, 7);
    assert.deepEqual(errors, ['invalid-offer']);
    assert.deepEqual(processed, [
        'participants:first,second',
        'messages:shared-message',
        'signal:invalid-offer',
        'signal:valid-candidate',
    ]);
});

test('a failed WebRTC signal can be retried without replaying messages', async () => {
    let shouldFail = true;
    const deliveredMessages = [];
    const handledSignals = [];
    const batch = {
        participants: ['first', 'second'],
        signals: ['offer'],
        messages: ['shared-message'],
        signal_cursor: 15,
        message_cursor: 9,
    };
    const handlers = {
        reconcileParticipants() {},
        async appendMessages(messages) {
            deliveredMessages.push(...messages);
        },
        setSignalCursor() {},
        setMessageCursor() {},
        async handleSignal(signal) {
            handledSignals.push(signal);

            if (shouldFail) {
                throw new Error('Temporary signaling failure');
            }
        },
    };

    const failedSignals = await processConferenceRoomSync(batch, handlers);
    shouldFail = false;
    const retriedSignals = await processConferenceRoomSync(
        {
            ...batch,
            signals: failedSignals,
            messages: [],
        },
        handlers,
    );

    assert.deepEqual(failedSignals, ['offer']);
    assert.deepEqual(retriedSignals, []);
    assert.deepEqual(deliveredMessages, ['shared-message']);
    assert.deepEqual(handledSignals, ['offer', 'offer']);
});
