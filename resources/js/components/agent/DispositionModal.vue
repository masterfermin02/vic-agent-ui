<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import { disposition } from '@/actions/App/Http/Controllers/Agent/CallController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AgentSession, Disposition } from '@/types';

type Props = {
    session: AgentSession;
    dispositions: Disposition[];
};

const props = defineProps<Props>();

const isOpen = computed(() => props.session.status === 'wrapup');
</script>

<template>
    <Dialog :open="isOpen">
        <DialogContent :show-close="false">
            <DialogHeader>
                <DialogTitle>Wrap Up</DialogTitle>
                <DialogDescription>Select a disposition for this call</DialogDescription>
            </DialogHeader>

            <div class="mt-2 grid gap-2">
                <Form
                    v-for="disp in dispositions"
                    :key="disp.status"
                    v-bind="disposition.form()"
                >
                    <input type="hidden" name="status" :value="disp.status" />
                    <Button type="submit" variant="outline" class="w-full justify-start">
                        <span class="font-mono text-xs text-muted-foreground mr-2">{{ disp.status }}</span>
                        {{ disp.status_name }}
                    </Button>
                </Form>
            </div>
        </DialogContent>
    </Dialog>
</template>
