<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AgentPerformanceHeader from '@/components/agent/AgentPerformanceHeader.vue';
import AgentStatusBar from '@/components/agent/AgentStatusBar.vue';
import CallPanel from '@/components/agent/CallPanel.vue';
import ConnectionBanner from '@/components/agent/ConnectionBanner.vue';
import DispositionModal from '@/components/agent/DispositionModal.vue';
import LeadPanel from '@/components/agent/LeadPanel.vue';
import ManualDialer from '@/components/agent/ManualDialer.vue';
import SipPhone from '@/components/agent/SipPhone.vue';
import { useAgentSession } from '@/composables/useAgentSession';
import { useCallState } from '@/composables/useCallState';
import { useConnectionState } from '@/composables/useConnectionState';
import { useSipPhone } from '@/composables/useSipPhone';
import { useSipWatchdog } from '@/composables/useSipWatchdog';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AgentPerformance, AgentSession, BreadcrumbItem, Disposition, Lead, SipConfig } from '@/types';

type Props = {
    session: AgentSession;
    dispositions: Disposition[];
    lead: Lead | null;
    performance: AgentPerformance | null;
    sip: SipConfig | null;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Agent Workspace', href: '/agent/workspace' },
];

const page = usePage();
const userId = page.props.auth.user.id as number;

const { session, updateSession } = useAgentSession(props.session);

// Re-sync local session ref when Inertia updates the prop (e.g. after a redirect).
watch(() => props.session, (incoming) => updateSession(incoming), { deep: true });

const { callStatus } = useCallState(userId, updateSession);
const { connectionState } = useConnectionState();

const sipConfig = computed(() => props.sip);
const { sipStatus, sipCallStatus, isMuted, attachAudio, answer, hangup, toggleMute } = useSipPhone(sipConfig);
const { sipWarning } = useSipWatchdog(session, callStatus, sipCallStatus);
const hasRequestedSoftphoneRing = ref(false);

function onAudioMounted(el: HTMLAudioElement): void {
    attachAudio(el);
}

async function requestSoftphoneRing(): Promise<void> {
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    if (!csrfToken) {
        return;
    }

    const response = await fetch('/agent/call/ring-softphone', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({}),
    });

    if (!response.ok) {
        throw new Error(`Ring request failed with ${response.status}`);
    }
}

watch([sipStatus, () => session.value.status], async ([status, agentStatus]) => {
    if (hasRequestedSoftphoneRing.value) {
        return;
    }

    if (status !== 'registered') {
        return;
    }

    if (!['paused', 'ready', 'waiting'].includes(agentStatus)) {
        return;
    }

    hasRequestedSoftphoneRing.value = true;

    try {
        await requestSoftphoneRing();
    } catch (error) {
        hasRequestedSoftphoneRing.value = false;
        console.error('Unable to ring softphone after SIP registration', error);
    }
}, { immediate: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Agent Workspace" />

        <ConnectionBanner :connection-state="connectionState" />

        <div class="flex flex-col gap-6 p-6">
            <AgentPerformanceHeader :performance="performance" />
            <AgentStatusBar :session="session" />

            <div class="grid gap-6 lg:grid-cols-3">
                <CallPanel :session="session" :call-status="callStatus" :sip-warning="sipWarning" />
                <LeadPanel :lead="lead" />
                <ManualDialer :session="session" />
            </div>

            <div v-if="sip" class="grid gap-6 lg:grid-cols-3">
                <SipPhone
                    :sip-status="sipStatus"
                    :sip-call-status="sipCallStatus"
                    :is-muted="isMuted"
                    @answer="answer"
                    @hangup="hangup"
                    @toggle-mute="toggleMute"
                    @audio-mounted="onAudioMounted"
                />
            </div>
        </div>

        <DispositionModal :session="session" :dispositions="dispositions" />
    </AppLayout>
</template>
