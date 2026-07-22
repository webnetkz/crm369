const NCA_LAYER_SOCKET_URL = 'wss://127.0.0.1:13579/';
const NCA_LAYER_CONNECTION_TIMEOUT = 5_000;
const SIGNATURE_EXTENDED_KEY_USAGE_OID = '1.3.6.1.5.5.7.3.4';

type NcaLayerLocale = 'en' | 'ru';

type NcaLayerResponse = {
    status?: boolean;
    code?: string;
    message?: string;
    body?: {
        result?: unknown;
    };
};

export type NcaLayerErrorCode = 'cancelled' | 'connection' | 'response';

export class NcaLayerError extends Error {
    public constructor(
        public readonly code: NcaLayerErrorCode,
        message?: string,
    ) {
        super(message);
        this.name = 'NcaLayerError';
    }
}

const openSocket = (): Promise<WebSocket> => {
    return new Promise((resolve, reject) => {
        const socket = new WebSocket(NCA_LAYER_SOCKET_URL);
        const connectionTimeout = window.setTimeout(() => {
            socket.close();
            reject(new NcaLayerError('connection'));
        }, NCA_LAYER_CONNECTION_TIMEOUT);

        socket.addEventListener(
            'open',
            () => {
                window.clearTimeout(connectionTimeout);
                resolve(socket);
            },
            { once: true },
        );

        socket.addEventListener(
            'error',
            () => {
                window.clearTimeout(connectionTimeout);
                reject(new NcaLayerError('connection'));
            },
            { once: true },
        );
    });
};

const signatureFromResult = (result: unknown): string | null => {
    if (typeof result === 'string' && result.trim() !== '') {
        return result;
    }

    if (Array.isArray(result)) {
        const signature = result.find(
            (value): value is string =>
                typeof value === 'string' && value.trim() !== '',
        );

        return signature ?? null;
    }

    if (
        typeof result !== 'object' ||
        result === null ||
        !('signatures' in result)
    ) {
        return null;
    }

    return signatureFromResult(result.signatures);
};

const errorFromResponse = (response: NcaLayerResponse): NcaLayerError => {
    const responseCode = response.code ?? response.message ?? '';
    const errorCode = /cancel|отмен/i.test(responseCode)
        ? 'cancelled'
        : 'response';

    return new NcaLayerError(errorCode, responseCode);
};

export const signXmlWithNcaLayer = async (
    xml: string,
    locale: NcaLayerLocale,
): Promise<string> => {
    const socket = await openSocket();

    return new Promise((resolve, reject) => {
        let completed = false;

        const finish = (callback: () => void): void => {
            if (completed) {
                return;
            }

            completed = true;
            socket.close();
            callback();
        };

        socket.addEventListener(
            'message',
            (event) => {
                try {
                    const response = JSON.parse(
                        String(event.data),
                    ) as NcaLayerResponse;

                    if (response.status !== true) {
                        finish(() => reject(errorFromResponse(response)));

                        return;
                    }

                    const signature = signatureFromResult(
                        response.body?.result,
                    );

                    if (!signature) {
                        finish(() => reject(new NcaLayerError('response')));

                        return;
                    }

                    finish(() => resolve(signature));
                } catch {
                    finish(() => reject(new NcaLayerError('response')));
                }
            },
            { once: true },
        );

        socket.addEventListener(
            'error',
            () => finish(() => reject(new NcaLayerError('connection'))),
            { once: true },
        );

        socket.addEventListener(
            'close',
            () => finish(() => reject(new NcaLayerError('connection'))),
            { once: true },
        );

        socket.send(
            JSON.stringify({
                module: 'kz.gov.pki.knca.basics',
                method: 'sign',
                args: {
                    format: 'xml',
                    data: xml,
                    signingParams: {
                        decode: false,
                        encapsulate: true,
                        digested: false,
                    },
                    signerParams: {
                        extKeyUsageOids: [SIGNATURE_EXTENDED_KEY_USAGE_OID],
                    },
                    locale,
                },
            }),
        );
    });
};
