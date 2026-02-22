import { computed, ref } from 'vue';
import type { AgentSession } from '@/types';

export function useAgentSession(initialSession: AgentSession) {
    const session = ref<AgentSession>({ ...initialSession });

    const isInCall = computed(() => session.value.status === 'incall');
    const isPaused = computed(() => session.value.status === 'paused');
    const isReady = computed(() => session.value.status === 'ready');
    const isWrapup = computed(() => session.value.status === 'wrapup');

    function updateSession(data: Partial<AgentSession>): void {
        session.value = { ...session.value, ...data };
    }

    return { session, isInCall, isPaused, isReady, isWrapup, updateSession };
}
