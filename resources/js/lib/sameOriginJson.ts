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

export async function fetchSameOriginJson<T>(
    url: string,
    options?: RequestInit,
): Promise<T> {
    const isFormData =
        typeof FormData !== 'undefined' && options?.body instanceof FormData;

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: buildHeaders(options?.headers, {
            omitJsonContentType: isFormData,
        }),
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return (await response.json()) as T;
}
