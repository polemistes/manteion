<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { BellRing } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import { toast, toastError } from '@/lib/toast';
import {
    APP_PUSH_ASKED_KEY,
    PUSH_PROMPT_DISMISSED_KEY,
    remember,
    remembered,
} from '@/native/pushPrompt';
import { isNativeShell } from '@/pwa/platform';
import {
    isSubscribed,
    permission,
    pushConfigured,
    subscribe,
} from '@/pwa/push';

/**
 * The Android app's one question at its first signed-in start: turn on
 * notifications? "Turn on" brings up Android's own permission prompt and
 * registers the device; "Not now" leaves it to Settings → Notifications.
 * Asked once per install, and not at all where the answer is already
 * known (on, or blocked in Android's settings).
 */
const { t } = useI18n();
const page = usePage();

const open = ref(false);
const busy = ref(false);

onMounted(async () => {
    if (
        !isNativeShell() ||
        !pushConfigured(page.props) ||
        remembered(APP_PUSH_ASKED_KEY) ||
        remembered(PUSH_PROMPT_DISMISSED_KEY)
    ) {
        return;
    }

    if ((await permission()) === 'denied' || (await isSubscribed())) {
        return;
    }

    remember(APP_PUSH_ASKED_KEY);
    open.value = true;
});

function later(): void {
    remember(PUSH_PROMPT_DISMISSED_KEY);
    open.value = false;
}

async function turnOn(): Promise<void> {
    busy.value = true;

    try {
        const result = await subscribe('', page.props.auth.user?.id ?? null);

        if (result === 'subscribed') {
            toast({ title: t('messages.push.enabled'), icon: BellRing });
        } else if (result === 'denied') {
            toastError(t('app.blocked'));
        } else if (result === 'failed' || result === 'unsupported') {
            toastError(t('messages.push.failed'));
        }
    } finally {
        busy.value = false;
        open.value = false;
    }
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('app.pushSheet.title')"
        :description="t('app.pushSheet.body')"
    >
        <template #actions>
            <Button
                variant="primary"
                :icon="BellRing"
                :disabled="busy"
                @click="turnOn"
            >
                {{ t('app.pushSheet.allow') }}
            </Button>
            <Button variant="ghost" :disabled="busy" @click="later">
                {{ t('app.pushSheet.later') }}
            </Button>
        </template>
    </AppDialog>
</template>
