import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

export type ConnectionState = 'connected' | 'reconnecting' | 'failed';

export function useConnectionState() {
    const connectionState = ref<ConnectionState>('connected');

    onMounted(() => {
        const pusher = (window.Echo.connector as any).pusher;

        pusher.connection.bind('state_change', ({ current }: { current: string }) => {
            if (current === 'connected') {
                connectionState.value = 'connected';
                router.reload({ only: ['session', 'performance'] });
            } else if (current === 'failed') {
                connectionState.value = 'failed';
            } else {
                connectionState.value = 'reconnecting';
            }
        });
    });

    return { connectionState };
}
