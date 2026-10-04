<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsLayout from '@/components/settings/SettingsLayout.vue';
import OptionCards from '@/components/ui/OptionCards.vue';
import { update as updateLocale } from '@/routes/locale';

/** English or Norwegian, saved at once for this account. */
const { t, locale } = useI18n({ useScope: 'global' });
const page = usePage();

const languages = computed(() =>
    // Each language by its own name, and the other one also in yours.
    Object.entries(page.props.availableLocales).map(([code, name]) => ({
        value: code,
        title: name,
        description:
            code === locale.value ? undefined : t(`settings.language.${code}`),
    })),
);

function setLanguage(code: string | undefined): void {
    if (!code || code === locale.value) {
        return;
    }

    locale.value = code;
    router.put(
        updateLocale().url,
        { locale: code },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <SettingsLayout
        section="language"
        :title="t('settings.sections.language')"
        :lede="t('settings.language.lede')"
    >
        <OptionCards
            :model-value="locale"
            :options="languages"
            :label="t('settings.sections.language')"
            @update:model-value="setLanguage"
        />
    </SettingsLayout>
</template>
