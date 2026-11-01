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
import type { ThemeMode, ThemeStyle } from '@/theme';

/** The look, light or dark or as the device has it, and the message sound. */
const props = defineProps<{
    theme_mode: ThemeMode;
    theme_style: ThemeStyle;
}>();

const { t } = useI18n();

const mode = ref<ThemeMode>(props.theme_mode);
const style = ref<ThemeStyle>(props.theme_style);

const styles = computed(() => [
    {
        value: 'classic',
        title: t('settings.appearance.classic'),
        description: t('settings.appearance.classicHint'),
        swatch: ['#F7F3EC', '#C23132', '#1F1A17'],
    },
    {
        value: 'aegean',
        title: t('settings.appearance.aegean'),
        description: t('settings.appearance.aegeanHint'),
        swatch: ['#EFE5D3', '#B4552E', '#5C6B2A', '#1D5EA6'],
    },
    {
        value: 'taverna',
        title: t('settings.appearance.taverna'),
        description: t('settings.appearance.tavernaHint'),
        swatch: ['#B34A23', '#E9C27E', '#1F5FB5', '#FFF6EA'],
    },
]);

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

/**
 * Applies the choice at once and saves it; a failed save puts the previous
 * look back.
 */
function save(next: { mode: ThemeMode; style: ThemeStyle }): void {
    const previous = { mode: mode.value, style: style.value };

    mode.value = next.mode;
    style.value = next.style;
    applyTheme(next);
    router.put(
        updateAppearance().url,
        { theme_mode: next.mode, theme_style: next.style },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                mode.value = previous.mode;
                style.value = previous.style;
                applyTheme(previous);
                toastError(t('settings.saveFailed'));
            },
        },
    );
}

function setMode(next: string | undefined): void {
    if (next && next !== mode.value) {
        save({ mode: next as ThemeMode, style: style.value });
    }
}

function setStyle(next: string | undefined): void {
    if (next && next !== style.value) {
        save({ mode: mode.value, style: next as ThemeStyle });
    }
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
                {{ t('settings.appearance.look') }}
            </h3>
            <OptionCards
                :model-value="style"
                :options="styles"
                :label="t('settings.appearance.look')"
                @update:model-value="setStyle"
            />
        </section>

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
