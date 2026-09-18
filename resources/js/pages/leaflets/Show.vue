<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LeafletCard from '@/components/LeafletCard.vue';
import type { LeafletSummary } from '@/leaflets';
import { index as leafletsIndex } from '@/routes/leaflets';

const { t } = useI18n();

const props = defineProps<{ leaflet: LeafletSummary }>();
</script>

<template>
    <Head :title="t('leaflets.index.title')" />

    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold">{{ t('leaflets.index.title') }}</h1>
        <Link :href="leafletsIndex().url" class="text-sm underline">
            {{ t('leaflets.show.backToFeed') }}
        </Link>
    </div>

    <div class="max-w-2xl">
        <LeafletCard :leaflet="props.leaflet" />
    </div>
</template>
