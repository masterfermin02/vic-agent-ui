<script setup lang="ts">
import type { ConnectionState } from '@/composables/useConnectionState';

type Props = {
    connectionState: ConnectionState;
};

defineProps<Props>();
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="-translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-full opacity-0"
    >
        <div
            v-if="connectionState !== 'connected'"
            :class="[
                'flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium',
                connectionState === 'failed'
                    ? 'bg-red-600 text-white'
                    : 'bg-amber-400 text-amber-950',
            ]"
        >
            <svg
                v-if="connectionState === 'reconnecting'"
                class="h-4 w-4 animate-spin"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>

            <span v-if="connectionState === 'reconnecting'">Reconnecting…</span>
            <span v-else>Connection failed — please refresh</span>
        </div>
    </Transition>
</template>
