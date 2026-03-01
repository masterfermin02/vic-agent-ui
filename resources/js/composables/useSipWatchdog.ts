import { onUnmounted, ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { CallStatus } from '@/composables/useCallState';
import type { AgentSession } from '@/types';

export function useSipWatchdog(session: Ref<AgentSession>, callStatus: Ref<CallStatus>) {
    const sipWarning = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;

    watch(
        [() => session.value.status, callStatus],
        ([status, cs]) => {
            clearTimeout(timer!);
            if (status === 'incall' && cs === 'idle') {
                timer = setTimeout(() => {
                    sipWarning.value = true;
                }, 30_000);
            } else {
                sipWarning.value = false;
            }
        },
        { immediate: true },
    );

    onUnmounted(() => clearTimeout(timer!));

    return { sipWarning };
}
