import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

export type ConnectionState = 'connected' | 'reconnecting' | 'failed';

export function useConnectionState() {
    const connectionState = ref<ConnectionState>('connected');
    let hasSeenConnected = false;

    onMounted(() => {
        const pusher = (window.Echo.connector as any).pusher;

        pusher.connection.bind('state_change', ({ previous, current }: { previous: string; current: string }) => {
            if (current === 'connected') {
                connectionState.value = 'connected';

                // Avoid a full Inertia reload on the very first connect, which can
                // restart the SIP UA during the initial registration/call ring race.
                if (hasSeenConnected && previous !== 'connected') {
                    router.reload({ only: ['session', 'performance'] });
                }

                hasSeenConnected = true;
            } else if (current === 'failed') {
                connectionState.value = 'failed';
            } else {
                connectionState.value = 'reconnecting';
            }
        });
    });

    return { connectionState };
}
