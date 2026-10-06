<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Trash } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import OrganizerForm from '@/components/organizers/OrganizerForm.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import { confirmAction } from '@/composables/useConfirm';
import type { OrganizerSummary } from '@/events';
import { toast } from '@/lib/toast';
import { destroy, show as organizersShow, update } from '@/routes/organizers';

const props = defineProps<{
    organizer: OrganizerSummary & { description: string | null };
}>();

const { t } = useI18n();

async function remove(): Promise<void> {
    const confirmed = await confirmAction({
        title: t('organizers.page.deleteConfirmTitle', {
            name: props.organizer.name,
        }),
        message: t('organizers.page.deleteConfirm'),
        confirmLabel: t('organizers.page.delete'),
    });

    if (confirmed) {
        router.delete(destroy(props.organizer.id).url, {
            onSuccess: () =>
                toast({
                    title: t('organizers.page.deleted', {
                        name: props.organizer.name,
                    }),
                    icon: Trash,
                }),
        });
    }
}
</script>

<template>
    <PageHeader
        :title="t('organizers.form.editTitle')"
        :close="organizersShow(props.organizer.id).url"
        :back="organizersShow(props.organizer.id).url"
        :back-label="props.organizer.name"
        width="panel"
    />

    <PageContainer width="panel" class="pt-5 pb-16 sm:pt-0">
        <OrganizerForm
            :form="update.form(props.organizer.id)"
            :values="props.organizer"
            :submit-label="t('organizers.form.save')"
            :processing-label="t('organizers.form.saving')"
        >
            <template #cancel>
                <Button
                    :as="Link"
                    :href="organizersShow(props.organizer.id).url"
                    variant="ghost"
                >
                    {{ t('organizers.form.cancel') }}
                </Button>
            </template>
        </OrganizerForm>

        <div class="mt-12 border-t border-line pt-6">
            <Button variant="danger" :icon="Trash" @click="remove">
                {{ t('organizers.page.delete') }}
            </Button>
            <p class="mt-2 text-meta text-ink-muted">
                {{ t('organizers.page.deleteConfirm') }}
            </p>
        </div>
    </PageContainer>
</template>
