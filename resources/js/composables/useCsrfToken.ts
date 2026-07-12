import { usePage } from '@inertiajs/vue3';

export function useCsrfToken(): string {
    const page = usePage<{ csrfToken?: string }>();

    return typeof page.props.csrfToken === 'string' ? page.props.csrfToken : '';
}
