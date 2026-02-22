import { onMounted, onUnmounted, ref } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { AgentSession } from '@/types';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo;
    }
}

export type CallStatus = 'idle' | 'ringing' | 'answered' | 'hangup';

export function useCallState(userId: number, onSessionUpdate: (data: Partial<AgentSession>) => void) {
    const callStatus = ref<CallStatus>('idle');

    function createEcho(): Echo {
        window.Pusher = Pusher;

        return new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
            wssPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '443'),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    let echo: Echo | null = null;

    onMounted(() => {
        echo = createEcho();

        echo.private(`agent.${userId}`)
            .listen('.AgentCallStatusUpdated', (event: {
                callStatus: string;
                callerIdNum?: string;
                callerIdName?: string;
                channel?: string;
                leadId?: string;
            }) => {
                callStatus.value = event.callStatus as CallStatus;

                if (event.callStatus === 'answered') {
                    onSessionUpdate({
                        status: 'incall',
                        asterisk_channel: event.channel ?? null,
                        call_started_at: new Date().toISOString(),
                    });
                } else if (event.callStatus === 'hangup') {
                    onSessionUpdate({ status: 'wrapup' });
                }
            })
            .listen('.AgentStatusChanged', (event: { status: string; campaignId: string }) => {
                onSessionUpdate({ status: event.status as AgentSession['status'] });
            });
    });

    onUnmounted(() => {
        echo?.disconnect();
        echo = null;
    });

    return { callStatus };
}
