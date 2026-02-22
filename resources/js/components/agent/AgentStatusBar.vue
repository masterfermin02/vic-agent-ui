<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AgentSession } from '@/types';
import { updateStatus, destroy } from '@/actions/App/Http/Controllers/Agent/AgentSessionController';

type Props = {
    session: AgentSession;
};

const props = defineProps<Props>();

const statusLabel = computed(() => {
    const labels: Record<AgentSession['status'], string> = {
        waiting: 'Waiting',
        ready: 'Ready',
        incall: 'In Call',
        paused: 'Paused',
        wrapup: 'Wrap-Up',
        logged_out: 'Logged Out',
    };
    return labels[props.session.status] ?? props.session.status;
});

const statusVariant = computed((): 'default' | 'secondary' | 'destructive' | 'outline' => {
    const variants: Record<AgentSession['status'], 'default' | 'secondary' | 'destructive' | 'outline'> = {
        waiting: 'secondary',
        ready: 'default',
        incall: 'destructive',
        paused: 'outline',
        wrapup: 'secondary',
        logged_out: 'outline',
    };
    return variants[props.session.status] ?? 'secondary';
});

const callDuration = computed(() => {
    if (!props.session.call_started_at) {
        return null;
    }
    const start = new Date(props.session.call_started_at).getTime();
    const now = Date.now();
    const seconds = Math.floor((now - start) / 1000);
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

function togglePause(): void {
    const newStatus = props.session.status === 'paused' ? 'ready' : 'paused';
    router.put(updateStatus.url(), { status: newStatus }, { preserveScroll: true });
}

function logout(): void {
    router.delete(destroy.url());
}
</script>

<template>
    <div class="flex items-center justify-between rounded-lg border bg-card p-4 shadow-sm">
        <div class="flex items-center gap-4">
            <div>
                <p class="text-xs text-muted-foreground">Campaign</p>
                <p class="font-semibold">{{ session.campaign_name ?? session.campaign_id }}</p>
            </div>
            <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
            <span v-if="callDuration" class="font-mono text-sm text-muted-foreground">
                {{ callDuration }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <Button
                v-if="session.status === 'ready' || session.status === 'paused'"
                variant="outline"
                size="sm"
                @click="togglePause"
            >
                {{ session.status === 'paused' ? 'Resume' : 'Pause' }}
            </Button>
            <Button variant="destructive" size="sm" @click="logout">
                Logout
            </Button>
        </div>
    </div>
</template>
