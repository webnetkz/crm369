export type ConferenceRoomSyncBatch<Participant, Signal, Message> = {
    participants: Participant[];
    signals: Signal[];
    messages: Message[];
    signal_cursor: number;
    message_cursor: number;
};

type ConferenceRoomSyncHandlers<Participant, Signal, Message> = {
    reconcileParticipants: (participants: Participant[]) => void;
    appendMessages: (messages: Message[]) => Promise<void>;
    setSignalCursor: (cursor: number) => void;
    setMessageCursor: (cursor: number) => void;
    handleSignal: (signal: Signal) => Promise<void>;
    onSignalError?: (signal: Signal, error: unknown) => void;
};

export async function processConferenceRoomSync<Participant, Signal, Message>(
    batch: ConferenceRoomSyncBatch<Participant, Signal, Message>,
    handlers: ConferenceRoomSyncHandlers<Participant, Signal, Message>,
): Promise<Signal[]> {
    handlers.reconcileParticipants(batch.participants);
    await handlers.appendMessages(batch.messages);
    handlers.setMessageCursor(batch.message_cursor);
    handlers.setSignalCursor(batch.signal_cursor);

    const failedSignals: Signal[] = [];

    for (const signal of batch.signals) {
        try {
            await handlers.handleSignal(signal);
        } catch (error) {
            failedSignals.push(signal);
            handlers.onSignalError?.(signal, error);
        }
    }

    return failedSignals;
}
