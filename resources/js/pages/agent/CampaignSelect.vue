<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Form } from '@inertiajs/vue3';
import { Headphones } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Campaign } from '@/types';
import { store } from '@/actions/App/Http/Controllers/Agent/AgentSessionController';

type Props = {
    campaigns: Campaign[];
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Agent Workspace', href: '/agent/campaigns' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Select Campaign" />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold">Select Campaign</h1>
                <p class="text-muted-foreground">Choose a campaign to join as an agent</p>
            </div>

            <div v-if="campaigns.length === 0" class="rounded-lg border bg-card p-8 text-center text-muted-foreground">
                No active campaigns available.
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="campaign in campaigns"
                    :key="campaign.campaign_id"
                    class="rounded-lg border bg-card p-6 shadow-sm flex flex-col gap-4"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                            <Headphones class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p class="font-semibold">{{ campaign.campaign_name }}</p>
                            <p class="text-xs text-muted-foreground font-mono">{{ campaign.campaign_id }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">Dial method: {{ campaign.dial_method }}</p>

                    <Form v-bind="store.form()" class="mt-auto">
                        <input type="hidden" name="campaign_id" :value="campaign.campaign_id" />
                        <Button type="submit" class="w-full">Login to Campaign</Button>
                    </Form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
