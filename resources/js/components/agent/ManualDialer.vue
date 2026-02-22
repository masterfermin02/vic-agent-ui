<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Phone } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AgentSession } from '@/types';
import { dial } from '@/actions/App/Http/Controllers/Agent/CallController';

type Props = {
    session: AgentSession;
};

const props = defineProps<Props>();

const isDisabled = () => props.session.status === 'incall' || props.session.status === 'paused';
</script>

<template>
    <div class="rounded-lg border bg-card p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Manual Dial</h2>

        <Form v-bind="dial.form()" class="flex items-end gap-2">
            <div class="flex-1 grid gap-2">
                <Label for="phone">Phone Number</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    placeholder="Enter number to dial"
                    :disabled="isDisabled()"
                />
            </div>
            <Button type="submit" :disabled="isDisabled()">
                <Phone class="mr-2 h-4 w-4" />
                Dial
            </Button>
        </Form>
    </div>
</template>
