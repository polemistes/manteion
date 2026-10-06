<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import OrganizerForm from '@/components/organizers/OrganizerForm.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import { store } from '@/routes/organizers';
import { organizers as settingsOrganizers } from '@/routes/settings';

const { t } = useI18n();
</script>

<template>
    <PageHeader
        :title="t('organizers.form.createTitle')"
        :close="settingsOrganizers().url"
        :back="settingsOrganizers().url"
        :back-label="t('organizers.title')"
        width="panel"
    />

    <PageContainer width="panel" class="pt-5 sm:pt-0">
        <OrganizerForm
            :form="store.form()"
            :values="{ name: '', handle: '', description: '' }"
            :submit-label="t('organizers.form.create')"
            :processing-label="t('organizers.form.creating')"
            :note="t('organizers.form.youBecomeMain')"
        >
            <template #cancel>
                <Button
                    :as="Link"
                    :href="settingsOrganizers().url"
                    variant="ghost"
                >
                    {{ t('organizers.form.cancel') }}
                </Button>
            </template>
        </OrganizerForm>
    </PageContainer>
</template>
