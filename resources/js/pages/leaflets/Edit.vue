<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LeafletForm from '@/components/LeafletForm.vue';
import type { LeafletVisibility } from '@/leaflets';
import { update } from '@/routes/leaflets';

const { t } = useI18n();

const props = defineProps<{
    leaflet: {
        id: number;
        body: string;
        visibility: LeafletVisibility;
        link_url: string | null;
        image_url: string | null;
    };
}>();
</script>

<template>
    <Head :title="t('leaflets.edit.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('leaflets.edit.title') }}</h1>

    <div class="max-w-xl">
        <LeafletForm
            :action="update.form(props.leaflet.id)"
            :submit-label="t('leaflets.edit.save')"
            :submitting-label="t('leaflets.edit.saving')"
            :body="props.leaflet.body"
            :visibility="props.leaflet.visibility"
            :link-url="props.leaflet.link_url"
            :image-url="props.leaflet.image_url"
        />
    </div>
</template>
