<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { BellRing, Share, SquarePlus, X } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import IconButton from '@/components/ui/IconButton.vue';
import { toast, toastError } from '@/lib/toast';
import { PUSH_PROMPT_DISMISSED_KEY } from '@/native/pushPrompt';
import { isNativeShell } from '@/pwa/platform';
import {
    isSubscribed,
    permission,
    pushConfigured,
    pushSupport,
    subscribe,
} from '@/pwa/push';

/**
 * Invites the user to turn on message notifications on this device, or (on
 * iPhone/iPad in Safari) explains how to install the app first. Only shown
 * in the browser or the Android app, once it knows the device can get
 * pushes and doesn't yet; dismissing it is remembered on the device (and
 * shared with the app's first-run sheet, AppPushSheet).
 */
const DISMISSED_KEY = PUSH_PROMPT_DISMISSED_KEY;

const { t } = useI18n();
const page = usePage();

const state = ref<'hidden' | 'offer' | 'install'>('hidden');
const busy = ref(false);

onMounted(async () => {
    try {
        if (localStorage.getItem(DISMISSED_KEY) === '1') {
            return;
        }
    } catch {
        // Storage blocked: just show the prompt.
    }

    const support = pushSupport();

    if (support === 'needs-install') {
        state.value = 'install';

        return;
    }

    if (
        support === 'supported' &&
        pushConfigured(page.props) &&
        (await permission()) !== 'denied' &&
        !(await isSubscribed())
    ) {
        state.value = 'offer';
    }
});

function dismiss(): void {
    state.value = 'hidden';

    try {
        localStorage.setItem(DISMISSED_KEY, '1');
    } catch {
        // Nothing to remember it in; it just shows again next time.
    }
}

async function turnOn(): Promise<void> {
    if (!pushConfigured(page.props)) {
        return;
    }

    busy.value = true;

    try {
        const result = await subscribe(
            page.props.vapidPublicKey ?? '',
            page.props.auth.user?.id ?? null,
        );

        if (result === 'subscribed') {
            state.value = 'hidden';
            toast({ title: t('messages.push.enabled'), icon: BellRing });
        } else if (result === 'denied') {
            state.value = 'hidden';
            toastError(
                isNativeShell() ? t('app.blocked') : t('messages.push.denied'),
            );
        } else if (result === 'failed') {
            toastError(t('messages.push.failed'));
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <!-- In a narrow column (the list beside an open chat) the icon goes,
         so the text keeps the width. -->
    <div v-if="state !== 'hidden'" class="@container">
        <div
            class="relative flex gap-3 rounded-lg border border-line bg-surface p-4 pr-12 @max-xs:pr-10"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-full bg-accent-soft text-accent-ink @max-xs:hidden"
            >
                <BellRing :size="20" :stroke-width="1.8" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">
                    {{
                        state === 'install'
                            ? t('messages.push.installTitle')
                            : t('messages.push.title')
                    }}
                </p>
                <p class="mt-0.5 text-meta text-ink-muted">
                    <template v-if="state === 'install'">
                        {{ t('messages.push.installBody') }}
                        <span class="mt-1.5 flex items-center gap-2 text-ink">
                            <Share
                                :size="16"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            <span aria-hidden="true">→</span>
                            <SquarePlus
                                :size="16"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </span>
                    </template>
                    <template v-else>{{ t('messages.push.body') }}</template>
                </p>
                <Button
                    v-if="state === 'offer'"
                    variant="primary"
                    size="sm"
                    class="mt-3"
                    :icon="BellRing"
                    :disabled="busy"
                    @click="turnOn"
                >
                    {{
                        busy
                            ? t('messages.push.turningOn')
                            : t('messages.push.turnOn')
                    }}
                </Button>
            </div>
            <IconButton
                :icon="X"
                :label="t('ui.dismiss')"
                size="sm"
                class="absolute top-2 right-2"
                @click="dismiss"
            />
        </div>
    </div>
</template>
