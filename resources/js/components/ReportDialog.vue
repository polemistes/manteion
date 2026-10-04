<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Flag } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import TextArea from '@/components/ui/TextArea.vue';
import { markReported, useReportState } from '@/composables/useReport';
import { toast, toastError } from '@/lib/toast';
import { store as reportsStore } from '@/routes/reports';

/** The report dialog behind `report(type, id)`; mounted by the shell. */
const { t } = useI18n();
const state = useReportState();

const reason = ref('');
const sending = ref(false);

const open = computed({
    get: () => state.isOpen,
    set: (value: boolean) => {
        state.isOpen = value;
    },
});

watch(open, (isOpen) => {
    if (isOpen) {
        reason.value = '';
    }
});

function submit(): void {
    if (state.type === null || state.id === null) {
        return;
    }

    const type = state.type;
    const id = state.id;

    sending.value = true;

    router.post(
        reportsStore().url,
        {
            reportable_type: type,
            reportable_id: id,
            reason: reason.value || null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                markReported(type, id);
                state.isOpen = false;
                toast({ title: t('moderation.reportThanks'), icon: Flag });
            },
            onError: () => toastError(t('ui.somethingWentWrong')),
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('moderation.reportTitle')"
        :description="t('moderation.reportDescription')"
    >
        <TextArea
            v-model="reason"
            :label="t('moderation.reportReason')"
            :placeholder="t('moderation.reportReasonPlaceholder')"
            :max-rows="6"
            optional
        />
        <template #actions>
            <Button variant="primary" :disabled="sending" @click="submit">
                {{
                    sending ? t('moderation.reporting') : t('moderation.report')
                }}
            </Button>
            <Button variant="ghost" @click="open = false">
                {{ t('common.cancel') }}
            </Button>
        </template>
    </AppDialog>
</template>
