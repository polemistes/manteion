<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import { update as updateLocale } from '@/routes/locale';

/** English / Norsk, as a segmented switch. Saves at once. */
const page = usePage();
const { t, locale } = useI18n({ useScope: 'global' });

const segments = computed(() =>
    Object.entries(page.props.availableLocales).map(([code, label]) => ({
        value: code,
        label,
        shortLabel: code.toUpperCase(),
    })),
);

const current = computed({
    get: () => locale.value,
    set: (code: string) => {
        locale.value = code;
        router.put(
            updateLocale().url,
            { locale: code },
            { preserveState: true, preserveScroll: true },
        );
    },
});
</script>

<template>
    <SegmentedControl
        v-model="current"
        :segments="segments"
        :label="t('locale.label')"
    />
</template>
