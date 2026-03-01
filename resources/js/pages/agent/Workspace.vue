<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AgentPerformanceHeader from '@/components/agent/AgentPerformanceHeader.vue';
import AgentStatusBar from '@/components/agent/AgentStatusBar.vue';
import CallPanel from '@/components/agent/CallPanel.vue';
import ConnectionBanner from '@/components/agent/ConnectionBanner.vue';
import DispositionModal from '@/components/agent/DispositionModal.vue';
import LeadPanel from '@/components/agent/LeadPanel.vue';
import ManualDialer from '@/components/agent/ManualDialer.vue';
import { useAgentSession } from '@/composables/useAgentSession';
import { useCallState } from '@/composables/useCallState';
import { useConnectionState } from '@/composables/useConnectionState';
import { useSipWatchdog } from '@/composables/useSipWatchdog';
import type { AgentPerformance, AgentSession, BreadcrumbItem, Disposition, Lead } from '@/types';

type Props = {
    session: AgentSession;
    dispositions: Disposition[];
    lead: Lead | null;
    performance: AgentPerformance | null;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Agent Workspace', href: '/agent/workspace' },
];

const page = usePage();
const userId = page.props.auth.user.id as number;

const { session, updateSession } = useAgentSession(props.session);
const { callStatus } = useCallState(userId, updateSession);
const { connectionState } = useConnectionState();
const { sipWarning } = useSipWatchdog(session, callStatus);
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
        </div>

        <DispositionModal :session="session" :dispositions="dispositions" />
    </AppLayout>
</template>
