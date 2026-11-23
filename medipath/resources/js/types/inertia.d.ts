import type { SharedProps } from '@/types';

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}

declare global {
    interface Window {
        Echo?: unknown;
    }
}

export {};