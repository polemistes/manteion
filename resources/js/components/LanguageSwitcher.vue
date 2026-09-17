<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { update as updateLocale } from '@/routes/locale';

const page = usePage();
const { locale } = useI18n({ useScope: 'global' });

function onChange(event: Event): void {
    const selected = (event.target as HTMLSelectElement).value;

    locale.value = selected;

    router.put(
        updateLocale().url,
        { locale: selected },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <select
        :value="page.props.locale"
        class="border border-edge bg-surface-raised px-2 py-1 text-xs"
        @change="onChange"
    >
        <option
            v-for="(label, code) in page.props.availableLocales"
            :key="code"
            :value="code"
        >
            {{ label }}
        </option>
    </select>
</template>
