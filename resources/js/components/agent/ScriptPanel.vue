<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import type { Script } from '@/types';

type Props = {
    script: Script | null;
};

defineProps<Props>();
</script>

<template>
    <div class="rounded-lg border bg-card p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Script</h2>

        <!-- No script configured -->
        <div v-if="!script" class="flex flex-col gap-3">
            <Skeleton class="h-4 w-48" />
            <Skeleton class="h-3 w-full" />
            <Skeleton class="h-3 w-5/6" />
            <Skeleton class="h-3 w-4/6" />
            <p class="text-sm text-muted-foreground text-center py-2">No script configured</p>
        </div>

        <!-- Script content -->
        <div v-else>
            <p class="text-sm font-medium text-foreground mb-3">{{ script.name }}</p>
            <div
                class="prose prose-sm max-w-none text-sm max-h-96 overflow-y-auto"
                v-html="script.body"
            />
        </div>
    </div>
</template>
