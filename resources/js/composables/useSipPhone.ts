import JsSIP from 'jssip';
import type { RTCSession } from 'jssip/lib/RTCSession';
import type { IncomingRTCSessionEvent, UAConfiguration } from 'jssip/lib/UA';
import type { Ref } from 'vue';
import { onUnmounted, ref, watch } from 'vue';
import type { SipConfig } from '@/types';

export type SipStatus = 'idle' | 'connecting' | 'registered' | 'unregistered' | 'failed';
export type SipCallStatus = 'none' | 'ringing' | 'active';

export function useSipPhone(sip: Ref<SipConfig | null>) {
    const sipStatus = ref<SipStatus>('idle');
    const sipCallStatus = ref<SipCallStatus>('none');
    const isMuted = ref(false);

    let ua: InstanceType<typeof JsSIP.UA> | null = null;
    let currentSession: RTCSession | null = null;
    let audioEl: HTMLAudioElement | null = null;

    function attachAudio(el: HTMLAudioElement): void {
        audioEl = el;
    }

    // Fallback: if the peerconnection 'track' event already fired before we
    // could listen (rare race), grab audio tracks directly from the receivers.
    function attachAudioFallback(session: RTCSession): void {
        if (!audioEl || audioEl.srcObject) { return; }

        const pc = (session as any).connection as RTCPeerConnection | undefined;
        const audioTracks = pc?.getReceivers()
            .map((r) => r.track)
            .filter((t) => t.kind === 'audio') ?? [];

        if (audioTracks.length > 0) {
            audioEl.srcObject = new MediaStream(audioTracks);
            audioEl.play().catch(console.warn);
        }
    }

    function bindSessionEvents(session: RTCSession): void {
        // Hook into the RTCPeerConnection the moment it is created so we never
        // miss 'track' events, which fire during SDP negotiation (before 'accepted').
        (session as any).on('peerconnection', (data: { peerconnection: RTCPeerConnection }) => {
            data.peerconnection.addEventListener('track', (event: RTCTrackEvent) => {
                if (event.track.kind === 'audio' && audioEl) {
                    audioEl.srcObject = event.streams[0] ?? new MediaStream([event.track]);
                    audioEl.play().catch(console.warn);
                }
            });
        });

        session.on('progress', () => {
            sipCallStatus.value = 'ringing';
        });

        session.on('accepted', () => {
            sipCallStatus.value = 'active';
            attachAudioFallback(session);
        });

        session.on('confirmed', () => {
            sipCallStatus.value = 'active';
            attachAudioFallback(session);
        });

        session.on('ended', () => {
            sipCallStatus.value = 'none';
            isMuted.value = false;
            currentSession = null;
            if (audioEl) { audioEl.srcObject = null; }
        });

        session.on('failed', () => {
            sipCallStatus.value = 'none';
            isMuted.value = false;
            currentSession = null;
            if (audioEl) { audioEl.srcObject = null; }
        });
    }

    function register(): void {
        const config = sip.value;
        if (!config || ua) { return; }

        if (config.debug) {
            JsSIP.debug.enable('JsSIP:*');
        }

        const rawAttempts = [
            { authUser: (config.sipAuthUser || config.extension).trim(), password: config.sipPassword },
            { authUser: (config.sipAltAuthUser || '').trim(), password: config.sipPassword },
            { authUser: (config.sipAuthUser || config.extension).trim(), password: (config.sipAltPassword || '').trim() },
            { authUser: (config.sipAltAuthUser || '').trim(), password: (config.sipAltPassword || '').trim() },
        ].filter((attempt) => attempt.authUser !== '' && attempt.password !== '');

        const attempts = rawAttempts.filter((attempt, index) =>
            rawAttempts.findIndex((other) => other.authUser === attempt.authUser && other.password === attempt.password) === index,
        );

        if (attempts.length === 0) {
            sipStatus.value = 'failed';
            console.error('SIP registration failed: no credentials available');

            return;
        }

        let attemptIndex = 0;

        const startUa = ({ authUser, password }: { authUser: string; password: string }): void => {
            sipStatus.value = 'connecting';

            const socket = new JsSIP.WebSocketInterface(config.wsUrl);

            const uaConfig: UAConfiguration = {
                sockets: [socket],
                uri: `sip:${config.extension}@${config.sipServer}`,
                authorization_user: authUser,
                password,
                register: true,
                register_expires: 300,
                session_timers: false,
                user_agent: 'VicAgent/1.0',
            };

            ua = new JsSIP.UA(uaConfig);

            ua.on('connecting', () => { sipStatus.value = 'connecting'; });
            ua.on('registered', () => { sipStatus.value = 'registered'; });
            ua.on('unregistered', () => { sipStatus.value = 'unregistered'; });
            ua.on('registrationFailed', (e) => {
                const cause = String((e as any).cause ?? '');
                const isAuthError = /auth/i.test(cause);

                if (isAuthError && attemptIndex < attempts.length - 1) {
                    attemptIndex += 1;
                    const retryAttempt = attempts[attemptIndex];

                    console.warn('SIP auth failed; retrying with alternate SIP credentials', {
                        cause,
                        authUser,
                        retryAuthUser: retryAttempt.authUser,
                        uri: `sip:${config.extension}@${config.sipServer}`,
                        wsUrl: config.wsUrl,
                    });
                    ua?.stop();
                    ua = null;
                    startUa(retryAttempt);

                    return;
                }

                sipStatus.value = 'failed';
                console.error('SIP registration failed:', {
                    cause,
                    uri: `sip:${config.extension}@${config.sipServer}`,
                    authUser,
                    wsUrl: config.wsUrl,
                });
            });
            ua.on('disconnected', () => {
                sipStatus.value = 'unregistered';
                // If the WebSocket dropped mid-call (DTLS failure, network blip, etc.)
                // the BYE may never arrive — reset call state so the UI doesn't stay stuck.
                if (sipCallStatus.value !== 'none') {
                    sipCallStatus.value = 'none';
                    isMuted.value = false;
                    currentSession = null;
                    if (audioEl) { audioEl.srcObject = null; }
                }
            });

            ua.on('newRTCSession', (e) => {
                const event = e as IncomingRTCSessionEvent;
                if (event.originator !== 'remote') { return; }
                console.info('Incoming SIP session received', {
                    uri: `sip:${config.extension}@${config.sipServer}`,
                    authUser,
                });

                const session = event.session;
                currentSession = session;

                bindSessionEvents(session);

                if (config.autoAnswer) {
                    // Skip the ringing UI entirely — go straight to active so the
                    // manual Answer button never appears on an auto-answered call.
                    sipCallStatus.value = 'active';
                    answerSession(session);
                } else {
                    sipCallStatus.value = 'ringing';
                }
            });

            ua.start();
        };

        startUa(attempts[0]);
    }

    function unregister(): void {
        if (!ua) { return; }
        ua.unregister({ all: true });
        ua.stop();
        ua = null;
        sipStatus.value = 'unregistered';
    }

    const answerOptions = {
        mediaConstraints: { audio: true, video: false },
        pcConfig: { iceServers: [] as RTCIceServer[] },
    };

    function answerSession(session: RTCSession): void {
        // JsSIP throws INVALID_STATE_ERROR if answer() is called after the
        // session already moved past the incoming-answerable state.
        if (session.isEstablished()) {
            sipCallStatus.value = 'active';

            return;
        }

        if ((session as any).isEnded?.()) {
            sipCallStatus.value = 'none';

            return;
        }

        if (!session.isInProgress()) {
            return;
        }

        try {
            session.answer(answerOptions);
        } catch (e) {
            console.warn('SIP answer error:', e);
        }
    }

    function answer(): void {
        if (currentSession && sipCallStatus.value === 'ringing') {
            answerSession(currentSession);
        }
    }

    function hangup(): void {
        currentSession?.terminate();
    }

    function toggleMute(): void {
        if (!currentSession?.isEstablished()) { return; }

        if (isMuted.value) {
            currentSession.unmute({ audio: true });
            isMuted.value = false;
        } else {
            currentSession.mute({ audio: true });
            isMuted.value = true;
        }
    }

    // Auto-register once sip config is available.
    watch(sip, (config) => {
        if (config && !ua) {
            register();
        }
    }, { immediate: true });

    onUnmounted(() => {
        if (currentSession?.isEstablished()) {
            currentSession.terminate();
        }
        unregister();
    });

    return { sipStatus, sipCallStatus, isMuted, attachAudio, answer, hangup, toggleMute };
}
