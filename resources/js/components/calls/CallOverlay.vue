<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Maximize2,
    Mic,
    MicOff,
    Minimize2,
    PhoneIncoming,
    PhoneMissed,
    PhoneOff,
    Video,
    VideoOff,
    Volume1,
    Volume2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import { toast } from '@/lib/toast';
import {
    acceptCall,
    declineCall,
    dismissCall,
    hangUp,
    onMissedCall,
    retryCall,
    toggleCamera,
    toggleLoudspeaker,
    toggleMic,
    useCalls,
} from '@/realtime';
import { show as messagesShow } from '@/routes/messages';

/**
 * The video call UI for the whole app: the ring banner at the top, and
 * the call window — full screen on a phone, a floating panel (or, on
 * request, the whole viewport) on larger screens — which outlives page
 * visits because it lives in the layout. Ends with a card that says how
 * the call ended and, when it never connected, why.
 */

const { t } = useI18n();
const { call, incoming, localStream, remoteStream } = useCalls();

const remoteVideo = ref<HTMLVideoElement | null>(null);
const localVideo = ref<HTMLVideoElement | null>(null);
const expanded = ref(false);
const now = ref(Date.now());
let clock: ReturnType<typeof setInterval> | undefined;

watch(
    [remoteStream, remoteVideo],
    ([stream, element]) => {
        if (element && element.srcObject !== stream) {
            element.srcObject = stream;
            // autoplay covers this; the explicit play() is for browsers that
            // still wait for one after the stream changes.
            void element.play().catch(() => {});
        }
    },
    { flush: 'post' },
);

watch(
    [localStream, localVideo],
    ([stream, element]) => {
        if (element && element.srcObject !== stream) {
            element.srcObject = stream;
            // autoplay covers this; the explicit play() is for browsers that
            // still wait for one after the stream changes.
            void element.play().catch(() => {});
        }
    },
    { flush: 'post' },
);

// The duration counts only while a call is connected.
watch(
    () => call.value?.status === 'connected',
    (connected) => {
        clearInterval(clock);
        clock = undefined;

        if (connected) {
            now.value = Date.now();
            clock = setInterval(() => {
                now.value = Date.now();
            }, 1000);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => clearInterval(clock));

onMissedCall((peer) => {
    toast({ title: t('calls.missed', { name: peer.name }), icon: PhoneMissed });
});

const live = computed(
    () => call.value !== null && call.value.status !== 'ended',
);
const showingPeerVideo = computed(
    () =>
        call.value?.status === 'connected' &&
        call.value.peerVideo &&
        call.value.peerCameraOn,
);

/** "Audio only", and why, while this side has no camera in the call. */
const audioOnlyText = computed(() => {
    const issue = call.value?.cameraIssue;

    return issue ? t(`calls.camera.${issue}`) : t('calls.audioOnly');
});

/** Under the avatar while there is no picture of the peer to show. */
const avatarCaption = computed(() => {
    const live = call.value;

    if (!live || live.status !== 'connected') {
        return statusText.value;
    }

    if (!live.peerHasCamera) {
        return t('calls.peerAudioOnly', { name: live.peer.name });
    }

    if (!live.peerCameraOn) {
        return t('calls.peerCameraOff', { name: live.peer.name });
    }

    return statusText.value;
});

const duration = computed(() => {
    const connectedAt = call.value?.connectedAt;

    if (!connectedAt) {
        return '';
    }

    const end = call.value?.endedAt ?? now.value;
    const seconds = Math.max(0, Math.floor((end - connectedAt) / 1000));
    const minutes = Math.floor(seconds / 60);

    return `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
});

const statusText = computed(() => {
    switch (call.value?.status) {
        case 'ringing':
            return t('calls.calling');
        case 'connecting':
            return t('calls.connecting');
        case 'connected':
            return call.value.reconnecting
                ? t('calls.reconnecting')
                : duration.value;
        default:
            return '';
    }
});

const endedTitle = computed(() =>
    call.value?.reason
        ? t(`calls.ended.${call.value.reason}.title`, {
              name: call.value.peer.name,
          })
        : '',
);

const endedBody = computed(() => {
    const ended = call.value;

    if (!ended?.reason) {
        return '';
    }

    if (ended.reason === 'hungUp') {
        return duration.value
            ? t('calls.ended.hungUp.body', { duration: duration.value })
            : '';
    }

    return t(`calls.ended.${ended.reason}.body`, { name: ended.peer.name });
});

/** Calling again makes sense unless they said no, or this device can't. */
const canRetry = computed(
    () =>
        call.value?.status === 'ended' &&
        call.value.outgoing &&
        !['declined', 'busy', 'unsupported'].includes(call.value.reason ?? ''),
);

const showMessageLink = computed(
    () => call.value?.status === 'ended' && call.value.reason !== 'hungUp',
);

const windowClass = computed(() => {
    if (!live.value) {
        return 'inset-x-4 top-1/2 -translate-y-1/2 rounded-xl bg-surface text-ink shadow-lg ring-1 ring-line sm:inset-x-auto sm:right-6 sm:w-95';
    }

    return expanded.value
        ? 'inset-0 bg-stage text-on-stage sm:inset-4 sm:rounded-xl sm:shadow-lg sm:ring-1 sm:ring-line'
        : 'inset-0 bg-stage text-on-stage sm:inset-auto sm:right-6 sm:bottom-6 sm:h-[560px] sm:max-h-[calc(100dvh-48px)] sm:w-[400px] sm:rounded-xl sm:shadow-lg sm:ring-1 sm:ring-line';
});
</script>

<template>
    <div
        v-if="incoming"
        role="alertdialog"
        :aria-label="t('calls.incoming')"
        class="fixed inset-x-3 top-[calc(12px+env(safe-area-inset-top))] z-[70] sm:right-auto sm:left-1/2 sm:w-[520px] sm:-translate-x-1/2"
    >
        <div
            class="flex animate-[banner-in_280ms_var(--ease-enter)] flex-wrap items-center gap-3 rounded-lg bg-surface p-3 pl-3.5 shadow-lg ring-1 ring-line"
        >
            <Avatar
                :name="incoming.peer.name"
                :src="incoming.peer.avatar_url"
                :size="44"
                decorative
            />
            <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-ink">
                    {{ incoming.peer.name }}
                </p>
                <p class="flex items-center gap-1.5 text-meta text-ink-muted">
                    <PhoneIncoming :size="14" aria-hidden="true" />
                    {{ t('calls.incoming') }}
                </p>
            </div>
            <!-- Phones: the buttons take a row of their own under the name. -->
            <div class="flex w-full gap-2 sm:w-auto">
                <Button
                    variant="danger"
                    size="sm"
                    :icon="PhoneOff"
                    class="flex-1 sm:flex-none"
                    @click="declineCall"
                >
                    {{ t('calls.decline') }}
                </Button>
                <Button
                    variant="primary"
                    size="sm"
                    :icon="Video"
                    class="flex-1 sm:flex-none"
                    @click="acceptCall"
                >
                    {{ t('calls.accept') }}
                </Button>
            </div>
        </div>
    </div>

    <div
        v-if="call"
        role="dialog"
        :aria-label="live ? t('calls.start') : endedTitle"
        class="fixed z-[70] flex flex-col overflow-hidden"
        :class="windowClass"
    >
        <template v-if="live">
            <video
                v-show="showingPeerVideo"
                ref="remoteVideo"
                autoplay
                playsinline
                class="absolute inset-0 size-full object-cover"
            />
            <div
                v-if="!showingPeerVideo"
                class="absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 text-center"
            >
                <Avatar
                    :name="call.peer.name"
                    :src="call.peer.avatar_url"
                    :size="96"
                    decorative
                />
                <div>
                    <p class="text-title-3 font-semibold">
                        {{ call.peer.name }}
                    </p>
                    <p
                        class="mt-1 text-body text-on-stage/70"
                        aria-live="polite"
                    >
                        {{ avatarCaption }}
                    </p>
                </div>
            </div>

            <div
                class="relative flex items-start justify-between gap-3 bg-linear-to-b from-stage/80 to-transparent p-4 pt-[calc(16px+env(safe-area-inset-top))] sm:pt-4"
            >
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 font-semibold">
                        <span class="truncate">{{ call.peer.name }}</span>
                        <MicOff
                            v-if="!call.peerMicOn"
                            :size="14"
                            class="shrink-0 text-on-stage/70"
                            :aria-label="
                                t('calls.peerMuted', { name: call.peer.name })
                            "
                        />
                    </p>
                    <p class="text-meta text-on-stage/80" aria-live="polite">
                        {{ statusText
                        }}<span v-if="!call.hasCamera">
                            · {{ audioOnlyText }}</span
                        >
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <!-- Only where the app can route the sound (Android 1.6+). -->
                    <button
                        v-if="call.loudspeaker !== null"
                        type="button"
                        class="relative grid size-10 place-items-center rounded-full transition-colors after:absolute after:-inset-1"
                        :class="
                            call.loudspeaker
                                ? 'bg-on-stage text-stage'
                                : 'bg-on-stage/10 hover:bg-on-stage/20'
                        "
                        :aria-label="t('calls.loudspeaker')"
                        :aria-pressed="call.loudspeaker"
                        @click="toggleLoudspeaker"
                    >
                        <component
                            :is="call.loudspeaker ? Volume2 : Volume1"
                            :size="18"
                            aria-hidden="true"
                        />
                    </button>
                    <button
                        type="button"
                        class="hidden size-10 place-items-center rounded-full bg-on-stage/10 hover:bg-on-stage/20 sm:grid"
                        :aria-label="
                            expanded ? t('calls.shrink') : t('calls.expand')
                        "
                        @click="expanded = !expanded"
                    >
                        <component
                            :is="expanded ? Minimize2 : Maximize2"
                            :size="18"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </div>

            <div
                v-if="call.hasCamera"
                class="absolute right-3 bottom-28 aspect-[3/4] w-24 overflow-hidden rounded-lg bg-on-stage/10 shadow-lg ring-1 ring-on-stage/20 sm:w-28"
            >
                <video
                    v-show="call.cameraOn"
                    ref="localVideo"
                    autoplay
                    playsinline
                    muted
                    class="size-full -scale-x-100 object-cover"
                />
                <div
                    v-if="!call.cameraOn"
                    class="grid size-full place-items-center text-on-stage/70"
                >
                    <VideoOff :size="20" aria-hidden="true" />
                    <span class="sr-only">{{ t('calls.yourCameraOff') }}</span>
                </div>
            </div>

            <div
                class="relative mt-auto flex items-center justify-center gap-5 p-5 pb-[calc(20px+env(safe-area-inset-bottom))] sm:pb-5"
            >
                <button
                    type="button"
                    class="grid size-13 place-items-center rounded-full transition-colors"
                    :class="
                        call.micOn
                            ? 'bg-on-stage/15 hover:bg-on-stage/25'
                            : 'bg-on-stage text-stage'
                    "
                    :aria-label="
                        call.micOn ? t('calls.mute') : t('calls.unmute')
                    "
                    :aria-pressed="!call.micOn"
                    @click="toggleMic"
                >
                    <component
                        :is="call.micOn ? Mic : MicOff"
                        :size="22"
                        aria-hidden="true"
                    />
                </button>
                <button
                    type="button"
                    class="grid size-15 place-items-center rounded-full bg-hangup text-on-hangup transition-colors hover:bg-hangup/90"
                    :aria-label="t('calls.hangUp')"
                    @click="hangUp"
                >
                    <PhoneOff :size="24" aria-hidden="true" />
                </button>
                <button
                    v-if="call.hasCamera"
                    type="button"
                    class="grid size-13 place-items-center rounded-full transition-colors"
                    :class="
                        call.cameraOn
                            ? 'bg-on-stage/15 hover:bg-on-stage/25'
                            : 'bg-on-stage text-stage'
                    "
                    :aria-label="
                        call.cameraOn
                            ? t('calls.cameraOff')
                            : t('calls.cameraOn')
                    "
                    :aria-pressed="!call.cameraOn"
                    @click="toggleCamera"
                >
                    <component
                        :is="call.cameraOn ? Video : VideoOff"
                        :size="22"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </template>

        <div
            v-else
            class="flex flex-col items-center gap-4 px-6 py-7 text-center"
        >
            <Avatar
                :name="call.peer.name"
                :src="call.peer.avatar_url"
                :size="64"
                decorative
            />
            <div>
                <p class="text-title-3 font-semibold">{{ endedTitle }}</p>
                <p v-if="endedBody" class="mt-2 text-body text-ink-muted">
                    {{ endedBody }}
                </p>
            </div>
            <div class="flex flex-wrap justify-center gap-2">
                <Button
                    v-if="canRetry"
                    variant="primary"
                    :icon="Video"
                    @click="retryCall"
                >
                    {{ t('calls.tryAgain') }}
                </Button>
                <Button
                    v-if="showMessageLink"
                    :as="Link"
                    :href="messagesShow(call.conversationId).url"
                    @click="dismissCall"
                >
                    {{ t('calls.messageInstead') }}
                </Button>
                <Button variant="ghost" @click="dismissCall">
                    {{ t('calls.close') }}
                </Button>
            </div>
        </div>
    </div>
</template>
