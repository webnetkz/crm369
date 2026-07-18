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

const responseErrorMessage = async (response: Response): Promise<string> => {
    if (!isJsonResponse(response)) {
        return `Request failed with status ${response.status}`;
    }

    const payload = (await response.json().catch(() => null)) as unknown;
    const message =
        typeof payload === 'object' &&
        payload !== null &&
        'message' in payload &&
        typeof payload.message === 'string'
            ? payload.message
            : null;

    return message
        ? `Request failed with status ${response.status}: ${message}`
        : `Request failed with status ${response.status}`;
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
            throw new Error(await responseErrorMessage(response));
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
