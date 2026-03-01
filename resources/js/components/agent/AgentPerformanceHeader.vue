<script setup lang="ts">
import type { AgentPerformance } from '@/types';

type Props = {
    performance: AgentPerformance | null;
};

defineProps<Props>();

function formatDuration(seconds: number): string {
    if (seconds <= 0) return '0s';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <!-- Calls Today -->
        <div class="rounded-lg border bg-card px-4 py-3 shadow-sm">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Calls Today</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ performance ? performance.callsToday : '—' }}
            </p>
        </div>

        <!-- Talk Time -->
        <div class="rounded-lg border bg-card px-4 py-3 shadow-sm">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Talk Time</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ performance ? formatDuration(performance.totalTalkSeconds) : '—' }}
            </p>
        </div>

        <!-- Avg Duration -->
        <div class="rounded-lg border bg-card px-4 py-3 shadow-sm">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Avg Duration</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ performance ? formatDuration(performance.avgDurationSeconds) : '—' }}
            </p>
        </div>

        <!-- Conversion -->
        <div class="rounded-lg border bg-card px-4 py-3 shadow-sm">
            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Conversion</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {{ performance ? `${performance.conversionRate}%` : '—' }}
            </p>
        </div>
    </div>
</template>
