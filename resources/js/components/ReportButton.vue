<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/components/Modal.vue';
import { store as reportsStore } from '@/routes/reports';

const { t } = useI18n();

const props = defineProps<{
    reportableType: 'message' | 'event' | 'comment';
    reportableId: number;
}>();

const open = ref(false);
const reason = ref('');
const reported = ref(false);

function submit(): void {
    router.post(
        reportsStore().url,
        {
            reportable_type: props.reportableType,
            reportable_id: props.reportableId,
            reason: reason.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                reported.value = true;
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <span v-if="reported" class="text-xs text-ink/50">{{
        t('moderation.reported')
    }}</span>
    <button
        v-else
        type="button"
        class="text-xs text-ink/50 hover:underline"
        @click="open = true"
    >
        {{ t('moderation.report') }}
    </button>

    <Modal :open="open" @update:open="open = $event">
        <p class="mb-2 text-sm font-bold">{{ t('moderation.reportTitle') }}</p>
        <textarea
            v-model="reason"
            rows="3"
            :placeholder="t('moderation.reportReasonPlaceholder')"
            class="mb-4 w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        ></textarea>
        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                @click="open = false"
            >
                {{ t('common.cancel') }}
            </button>
            <button
                type="button"
                class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
                @click="submit"
            >
                {{ t('moderation.report') }}
            </button>
        </div>
    </Modal>
</template>
