<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsGroup from '@/components/settings/SettingsGroup.vue';
import SettingsLayout from '@/components/settings/SettingsLayout.vue';
import OptionCards from '@/components/ui/OptionCards.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import { toastError } from '@/lib/toast';
import {
    isMessageSoundEnabled,
    playMessageSound,
    setMessageSoundEnabled,
} from '@/realtime';
import { update as updateAppearance } from '@/routes/settings/appearance';
import { applyTheme } from '@/theme';
import type { ThemeMode } from '@/theme';

/** Light, dark or as the device has it, and the message sound. */
const props = defineProps<{
    theme_mode: ThemeMode;
}>();

const { t } = useI18n();

const mode = ref<string>(props.theme_mode);

const modes = computed(() => [
    {
        value: 'light',
        title: t('settings.appearance.light'),
        description: t('settings.appearance.lightHint'),
        icon: Sun,
    },
    {
        value: 'dark',
        title: t('settings.appearance.dark'),
        description: t('settings.appearance.darkHint'),
        icon: Moon,
    },
    {
        value: 'system',
        title: t('settings.appearance.system'),
        description: t('settings.appearance.systemHint'),
        icon: Monitor,
    },
]);

function setMode(next: string | undefined): void {
    if (!next || next === mode.value) {
        return;
    }

    const previous = mode.value;

    mode.value = next;
    applyTheme({ mode: next as ThemeMode });
    router.put(
        updateAppearance().url,
        { theme_mode: next },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                mode.value = previous;
                applyTheme({ mode: previous as ThemeMode });
                toastError(t('settings.saveFailed'));
            },
        },
    );
}

// The sound is this browser's choice (stored in it), known once mounted.
const sound = ref(true);

onMounted(() => {
    sound.value = isMessageSoundEnabled();
});

function setSound(on: boolean): void {
    sound.value = on;
    setMessageSoundEnabled(on);

    if (on) {
        playMessageSound();
    }
}
</script>

<template>
    <SettingsLayout
        section="appearance"
        :title="t('settings.sections.appearance')"
    >
        <section>
            <h3 class="mb-2.5 text-kicker text-ink-muted uppercase">
                {{ t('settings.appearance.mode') }}
            </h3>
            <OptionCards
                :model-value="mode"
                :options="modes"
                :label="t('settings.appearance.mode')"
                @update:model-value="setMode"
            />
        </section>

        <SettingsGroup
            :label="t('settings.appearance.sound')"
            :note="t('settings.appearance.soundNote')"
        >
            <SwitchRow
                :model-value="sound"
                :title="t('settings.appearance.soundTitle')"
                :description="t('settings.appearance.soundHint')"
                @update:model-value="setSound"
            />
        </SettingsGroup>
    </SettingsLayout>
</template>
