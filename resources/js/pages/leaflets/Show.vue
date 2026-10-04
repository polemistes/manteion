<script setup lang="ts">
import { usePoll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LeafletCard from '@/components/leaflets/LeafletCard.vue';
import ResponseThread from '@/components/leaflets/ResponseThread.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import type { LeafletSummary } from '@/leaflets';
import { index as leafletsIndex } from '@/routes/leaflets';

/** One leaflet with its responses. */
const props = defineProps<{
    leaflet: LeafletSummary;
}>();

const { t } = useI18n();

usePoll(30_000, { only: ['leaflet'] });
</script>

<template>
    <PageHeader
        :title="t('leaflets.show.title', { name: props.leaflet.owner.name })"
        :back="leafletsIndex().url"
        :back-label="t('leaflets.index.title')"
    />

    <PageContainer class="pt-4 pb-16 sm:pt-0">
        <LeafletCard :leaflet="props.leaflet" full />
        <div class="mt-8">
            <ResponseThread
                :leaflet-id="props.leaflet.id"
                :responses="props.leaflet.responses ?? []"
            />
        </div>
    </PageContainer>
</template>
