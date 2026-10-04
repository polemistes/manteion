<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { index as settingsIndex } from '@/routes/settings';
import type { SettingsSection } from './sections';
import SettingsNav from './SettingsNav.vue';

/**
 * A settings panel. Under 768 it is a screen of its own, with a way back
 * to Settings; from 768 it sits beside the settings nav under one
 * "Settings" heading.
 */
const props = withDefaults(
    defineProps<{
        section: SettingsSection;
        title: string;
        lede?: string;
    }>(),
    { lede: undefined },
);

const { t } = useI18n();
</script>

<template>
    <PageHeader
        :title="props.title"
        :document-title="`${props.title} · ${t('settings.title')}`"
        :back="settingsIndex().url"
        :back-label="t('settings.title')"
        header-hidden
    />

    <PageContainer width="wide" class="pb-16">
        <div class="hidden pt-7 pb-4.5 sm:block md:hidden">
            <Link
                :href="settingsIndex().url"
                class="mb-3 -ml-3 inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-label font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink"
            >
                <ArrowLeft :size="16" :stroke-width="2" aria-hidden="true" />
                {{ t('settings.title') }}
            </Link>
            <h1 class="font-display text-title-1">{{ props.title }}</h1>
        </div>

        <h1
            class="hidden pt-7 pb-5 font-display text-title-1 md:block lg:pt-9 lg:text-display"
        >
            {{ t('settings.title') }}
        </h1>

        <div
            class="md:grid md:grid-cols-[220px_minmax(0,40rem)] md:gap-12 lg:grid-cols-[240px_minmax(0,40rem)]"
        >
            <div class="hidden md:block">
                <div class="sticky top-6">
                    <SettingsNav :current="props.section" />
                </div>
            </div>

            <div class="min-w-0 pt-4 sm:pt-0">
                <h2 class="hidden font-display text-title-2 md:block">
                    {{ props.title }}
                </h2>
                <p v-if="props.lede" class="text-body text-ink-muted md:mt-2">
                    {{ props.lede }}
                </p>
                <div class="mt-6 flex flex-col gap-7">
                    <slot />
                </div>
            </div>
        </div>
    </PageContainer>
</template>
