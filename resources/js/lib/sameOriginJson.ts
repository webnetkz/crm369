const readCookie = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const prefix = `${name}=`;

    return (
        document.cookie
            .split(';')
            .map((part) => part.trim())
            .find((part) => part.startsWith(prefix))
            ?.slice(prefix.length) ?? null
    );
};

const buildHeaders = (
    headers?: HeadersInit,
    options?: {
        omitJsonContentType?: boolean;
    },
): Headers => {
    const mergedHeaders = new Headers({
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    });

    if (!options?.omitJsonContentType) {
        mergedHeaders.set('Content-Type', 'application/json');
    }

    const csrfToken = readCookie('XSRF-TOKEN');

    if (csrfToken) {
        mergedHeaders.set('X-XSRF-TOKEN', decodeURIComponent(csrfToken));
    }

    if (headers) {
        new Headers(headers).forEach((value, key) => {
            mergedHeaders.set(key, value);
        });
    }

    return mergedHeaders;
};

const defaultTimeoutMs = 30_000;

const isJsonResponse = (response: Response): boolean => {
    return (
        response.headers.get('content-type')?.includes('application/json') ??
        false
    );
};

type ErrorPayload = {
    message: string | null;
    code: string | null;
};

export class SameOriginJsonError extends Error {
    public constructor(
        public readonly status: number,
        public readonly code: string | null,
        message: string,
    ) {
        super(message);
        this.name = 'SameOriginJsonError';
    }
}

const responseErrorPayload = async (
    response: Response,
): Promise<ErrorPayload> => {
    if (!isJsonResponse(response)) {
        return {
            message: null,
            code: null,
        };
    }

    const payload = (await response.json().catch(() => null)) as unknown;
    const message =
        typeof payload === 'object' &&
        payload !== null &&
        'message' in payload &&
        typeof payload.message === 'string'
            ? payload.message
            : null;
    const code =
        typeof payload === 'object' &&
        payload !== null &&
        'code' in payload &&
        typeof payload.code === 'string'
            ? payload.code
            : null;

    return { message, code };
};

export async function fetchSameOriginJson<T>(
    url: string,
    options?: RequestInit,
): Promise<T> {
    const isFormData =
        typeof FormData !== 'undefined' && options?.body instanceof FormData;
    const requestController = new AbortController();
    const externalSignal = options?.signal;
    const forwardExternalAbort = (): void => {
        requestController.abort(externalSignal?.reason);
    };
    const timeoutId = setTimeout(() => {
        requestController.abort(
            new DOMException('Request timed out.', 'TimeoutError'),
        );
    }, defaultTimeoutMs);

    if (externalSignal?.aborted) {
        forwardExternalAbort();
    } else {
        externalSignal?.addEventListener('abort', forwardExternalAbort, {
            once: true,
        });
    }

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            signal: requestController.signal,
            headers: buildHeaders(options?.headers, {
                omitJsonContentType: isFormData,
            }),
        });

        if (!response.ok) {
            const error = await responseErrorPayload(response);

            throw new SameOriginJsonError(
                response.status,
                error.code,
                error.message ??
                    `Request failed with status ${response.status}`,
            );
        }

        if (!isJsonResponse(response)) {
            throw new Error(
                `Expected a JSON response but received ${response.headers.get('content-type') ?? 'an unknown content type'}.`,
            );
        }

        return (await response.json()) as T;
    } finally {
        clearTimeout(timeoutId);
        externalSignal?.removeEventListener('abort', forwardExternalAbort);
    }
}
