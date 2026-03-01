<script setup lang="ts">
import { Mic, MicOff, Phone, PhoneOff, Wifi, WifiOff } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { SipStatus } from '@/composables/useSipPhone';

const emit = defineEmits<{
    answer: [];
    hangup: [];
    toggleMute: [];
    audioMounted: [el: HTMLAudioElement];
}>();

const audioRef = ref<HTMLAudioElement | null>(null);

onMounted(() => {
    if (audioRef.value) {
        emit('audioMounted', audioRef.value);
    }
});

const statusLabel: Record<SipStatus, string> = {
    idle: 'Not connected',
    connecting: 'Connecting…',
    registered: 'Ready',
    unregistered: 'Unregistered',
    failed: 'Registration failed',
};
</script>

<template>
    <audio ref="audioRef" autoplay playsinline style="display: none" />

    <div class="rounded-lg border bg-card p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-muted-foreground uppercase tracking-wide">Softphone</h2>

        <!-- Registration status -->
        <div class="mb-4 flex items-center gap-2 text-sm">
            <Wifi
                v-if="sipStatus === 'registered'"
                class="h-4 w-4 text-green-500"
            />
            <WifiOff
                v-else
                class="h-4 w-4"
                :class="{
                    'text-amber-500 animate-pulse': sipStatus === 'connecting',
                    'text-red-500': sipStatus === 'failed',
                    'text-muted-foreground': sipStatus === 'idle' || sipStatus === 'unregistered',
                }"
            />
            <span :class="{
                'text-green-600': sipStatus === 'registered',
                'text-amber-600': sipStatus === 'connecting',
                'text-red-600': sipStatus === 'failed',
                'text-muted-foreground': sipStatus === 'idle' || sipStatus === 'unregistered',
            }">
                {{ statusLabel[sipStatus] }}
            </span>
        </div>

        <!-- Ringing -->
        <div v-if="sipCallStatus === 'ringing'" class="flex flex-col items-center gap-3 py-2">
            <div class="relative flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100">
                <Phone class="h-6 w-6 text-yellow-600" />
                <span class="absolute inset-0 animate-ping rounded-full bg-yellow-300 opacity-50" />
            </div>
            <p class="text-sm text-muted-foreground">Incoming call…</p>
            <Button variant="default" size="sm" class="bg-green-600 hover:bg-green-700" @click="emit('answer')">
                <Phone class="mr-1.5 h-4 w-4" />
                Answer
            </Button>
        </div>

        <!-- Active call controls -->
        <div v-else-if="sipCallStatus === 'active'" class="flex items-center justify-center gap-3 py-2">
            <Button
                variant="outline"
                size="sm"
                :class="{ 'border-amber-400 text-amber-600': isMuted }"
                @click="emit('toggleMute')"
            >
                <MicOff v-if="isMuted" class="mr-1.5 h-4 w-4" />
                <Mic v-else class="mr-1.5 h-4 w-4" />
                {{ isMuted ? 'Unmute' : 'Mute' }}
            </Button>

            <Button variant="destructive" size="sm" @click="emit('hangup')">
                <PhoneOff class="mr-1.5 h-4 w-4" />
                Hang up
            </Button>
        </div>

        <!-- Idle / registered -->
        <div v-else-if="sipStatus === 'registered'" class="py-2 text-center text-sm text-muted-foreground">
            Waiting for call…
        </div>
    </div>
</template>
