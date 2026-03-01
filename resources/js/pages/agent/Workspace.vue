<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AgentStatusBar from '@/components/agent/AgentStatusBar.vue';
import CallPanel from '@/components/agent/CallPanel.vue';
import DispositionModal from '@/components/agent/DispositionModal.vue';
import LeadPanel from '@/components/agent/LeadPanel.vue';
import ManualDialer from '@/components/agent/ManualDialer.vue';
import { useAgentSession } from '@/composables/useAgentSession';
import { useCallState } from '@/composables/useCallState';
import type { AgentSession, BreadcrumbItem, Disposition, Lead } from '@/types';

type Props = {
    session: AgentSession;
    dispositions: Disposition[];
    lead: Lead | null;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Agent Workspace', href: '/agent/workspace' },
];

const page = usePage();
const userId = page.props.auth.user.id as number;

const { session, updateSession } = useAgentSession(props.session);
const { callStatus } = useCallState(userId, updateSession);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Agent Workspace" />

        <div class="flex flex-col gap-6 p-6">
            <AgentStatusBar :session="session" />

            <div class="grid gap-6 lg:grid-cols-3">
                <CallPanel :session="session" :call-status="callStatus" />
                <LeadPanel :lead="lead" />
                <ManualDialer :session="session" />
            </div>
        </div>

        <DispositionModal :session="session" :dispositions="dispositions" />
    </AppLayout>
</template>
