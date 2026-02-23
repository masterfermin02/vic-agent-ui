<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Phone, PhoneCall, PhoneOff } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { AgentSession } from '@/types';
import type { CallStatus } from '@/composables/useCallState';
import { hangup } from '@/actions/App/Http/Controllers/Agent/CallController';

type Props = {
    session: AgentSession;
    callStatus: CallStatus;
};

defineProps<Props>();
</script>

<template>
    <div class="rounded-lg border bg-card p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Live Call</h2>

        <!-- Ringing -->
        <div v-if="callStatus === 'ringing'" class="flex flex-col items-center gap-4 py-4">
            <div class="relative flex h-16 w-16 items-center justify-center rounded-full bg-yellow-100">
                <Phone class="h-8 w-8 text-yellow-600" />
                <span class="absolute inset-0 animate-ping rounded-full bg-yellow-300 opacity-50" />
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold">
                    {{ session.current_lead_name ?? 'Incoming Call' }}
                </p>
                <p class="text-sm text-muted-foreground">{{ session.current_phone }}</p>
            </div>
        </div>

        <!-- In Call -->
        <div v-else-if="callStatus === 'answered' || session.status === 'incall'" class="flex flex-col items-center gap-4 py-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <PhoneCall class="h-8 w-8 text-green-600" />
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold">
                    {{ session.current_lead_name ?? 'Connected' }}
                </p>
                <p class="text-sm text-muted-foreground">{{ session.current_phone }}</p>
            </div>
            <Form v-bind="hangup.form()">
                <Button type="submit" variant="destructive" size="sm">
                    <PhoneOff class="mr-2 h-4 w-4" />
                    Hang Up
                </Button>
            </Form>
        </div>

        <!-- Wrap-Up -->
        <div v-else-if="session.status === 'wrapup'" class="flex flex-col items-center gap-4 py-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                <PhoneOff class="h-8 w-8 text-gray-500" />
            </div>
            <p class="text-sm text-muted-foreground">Call ended — select a disposition</p>
        </div>

        <!-- Idle -->
        <div v-else class="flex flex-col items-center gap-4 py-4">
            <Skeleton class="h-16 w-16 rounded-full" />
            <div class="space-y-2 text-center">
                <Skeleton class="mx-auto h-4 w-32" />
                <Skeleton class="mx-auto h-3 w-24" />
            </div>
        </div>
    </div>
</template>
