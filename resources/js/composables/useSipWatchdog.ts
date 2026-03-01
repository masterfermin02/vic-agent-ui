import { onUnmounted, ref, watch } from 'vue';
import type { Ref } from 'vue';
import type { CallStatus } from '@/composables/useCallState';
import type { SipCallStatus } from '@/composables/useSipPhone';
import type { AgentSession } from '@/types';

export function useSipWatchdog(
    session: Ref<AgentSession>,
    callStatus: Ref<CallStatus>,
    sipCallStatus: Ref<SipCallStatus>,
) {
    const sipWarning = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;

    watch(
        [() => session.value.status, callStatus, sipCallStatus],
        ([status, cs, sipCs]) => {
            clearTimeout(timer!);
            if (status === 'incall' && cs === 'idle' && sipCs === 'none') {
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
