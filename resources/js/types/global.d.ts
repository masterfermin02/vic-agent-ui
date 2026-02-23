import type Echo from 'laravel-echo';
import type { Auth } from '@/types/auth';

declare global {
    interface Window {
        Echo: Echo;
        Pusher: typeof import('pusher-js').default;
    }
}

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_REVERB_APP_KEY: string;
        readonly VITE_REVERB_HOST: string;
        readonly VITE_REVERB_PORT: string;
        readonly VITE_REVERB_SCHEME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
