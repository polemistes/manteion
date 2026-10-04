<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LeafletForm from '@/components/leaflets/LeafletForm.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import type { LeafletVisibility } from '@/leaflets';
import { index as leafletsIndex, update } from '@/routes/leaflets';

const props = defineProps<{
    leaflet: {
        id: number;
        body: string;
        visibility: LeafletVisibility;
        link_url: string | null;
        image_url: string | null;
    };
}>();

const { t } = useI18n();
</script>

<template>
    <PageHeader
        :title="t('leaflets.edit.title')"
        :close="leafletsIndex().url"
        :back="leafletsIndex().url"
        :back-label="t('leaflets.index.title')"
        width="panel"
    />

    <PageContainer width="panel" class="pt-5 pb-16 sm:pt-0">
        <LeafletForm
            :form="update.form(props.leaflet.id)"
            :body="props.leaflet.body"
            :visibility="props.leaflet.visibility"
            :link-url="props.leaflet.link_url"
            :image-url="props.leaflet.image_url"
            :submit-label="t('leaflets.edit.save')"
            :submitting-label="t('leaflets.edit.saving')"
        >
            <template #cancel>
                <Button :as="Link" :href="leafletsIndex().url" variant="ghost">
                    {{ t('ui.cancel') }}
                </Button>
            </template>
        </LeafletForm>
    </PageContainer>
</template>
