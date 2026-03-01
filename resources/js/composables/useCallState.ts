import { onMounted, onUnmounted, ref } from 'vue';
import type { AgentSession } from '@/types';

export type CallStatus = 'idle' | 'ringing' | 'answered' | 'hangup';

export function useCallState(userId: number, onSessionUpdate: (data: Partial<AgentSession>) => void) {
    const callStatus = ref<CallStatus>('idle');

    onMounted(() => {
        window.Echo.private(`agent.${userId}`)
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
        window.Echo.leave(`agent.${userId}`);
    });

    return { callStatus };
}
