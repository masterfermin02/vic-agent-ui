<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import type { Lead } from '@/types';

type Props = {
    lead: Lead | null;
};

defineProps<Props>();

function formatDuration(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return m > 0 ? `${m}m ${s}s` : `${s}s`;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <div class="rounded-lg border bg-card p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Lead Info</h2>

        <!-- No active lead -->
        <div v-if="!lead" class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <Skeleton class="h-10 w-10 rounded-full" />
                <div class="space-y-2">
                    <Skeleton class="h-4 w-32" />
                    <Skeleton class="h-3 w-24" />
                </div>
            </div>
            <p class="text-sm text-muted-foreground text-center py-2">No active lead</p>
        </div>

        <!-- Lead data -->
        <div v-else class="flex flex-col gap-5">
            <!-- Header: name + phone + status -->
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-base font-semibold leading-tight">{{ lead.firstName }} {{ lead.lastName }}</p>
                    <p class="text-sm text-muted-foreground">+{{ lead.phoneCode }} {{ lead.phone }}</p>
                    <p v-if="lead.email" class="text-xs text-muted-foreground mt-0.5">{{ lead.email }}</p>
                </div>
                <Badge variant="outline" class="shrink-0">{{ lead.status }}</Badge>
            </div>

            <!-- Address -->
            <div v-if="lead.address" class="text-sm text-muted-foreground">
                <span class="font-medium text-foreground">Address: </span>{{ lead.address }}
            </div>

            <!-- Notes -->
            <div v-if="lead.notes" class="text-sm">
                <p class="font-medium text-foreground mb-1">Notes</p>
                <p class="text-muted-foreground">{{ lead.notes }}</p>
            </div>

            <!-- Custom fields -->
            <div v-if="Object.keys(lead.customFields).some(k => lead.customFields[k])" class="text-sm">
                <p class="font-medium text-foreground mb-1">Details</p>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                    <template v-for="(value, key) in lead.customFields" :key="key">
                        <dt v-if="value" class="text-muted-foreground capitalize">{{ String(key).replace(/_/g, ' ') }}</dt>
                        <dd v-if="value" class="truncate">{{ value }}</dd>
                    </template>
                </dl>
            </div>

            <!-- Disposition history -->
            <div v-if="lead.previousDispositions.length > 0" class="text-sm">
                <p class="font-medium text-foreground mb-2">Call History <span class="text-muted-foreground font-normal">(last {{ lead.previousDispositions.length }})</span></p>
                <ul class="space-y-2">
                    <li
                        v-for="(dispo, index) in lead.previousDispositions"
                        :key="index"
                        class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-xs"
                    >
                        <div class="flex flex-col gap-0.5">
                            <span class="font-medium">{{ dispo.status }}</span>
                            <span class="text-muted-foreground">{{ dispo.agentId }} &middot; {{ formatDuration(dispo.durationSeconds) }}</span>
                        </div>
                        <span class="text-muted-foreground shrink-0">{{ formatDate(dispo.calledAt) }}</span>
                    </li>
                </ul>
            </div>

            <!-- Called count -->
            <p class="text-xs text-muted-foreground">Called {{ lead.calledCount }} time{{ lead.calledCount === 1 ? '' : 's' }}</p>
        </div>
    </div>
</template>
