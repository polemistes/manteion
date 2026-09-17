<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Modal from '@/components/Modal.vue';
import {
    resolveStaffDelete,
    useStaffDeleteState,
} from '@/composables/useStaffDelete';

const { t } = useI18n();

const state = useStaffDeleteState();
</script>

<template>
    <Modal :open="state.isOpen" @update:open="resolveStaffDelete(null)">
        <p class="mb-6 text-sm">{{ state.message }}</p>
        <div class="flex flex-col gap-2">
            <button
                type="button"
                class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
                @click="resolveStaffDelete('placeholder')"
            >
                {{ t('moderation.deleteWithPlaceholder') }}
            </button>
            <button
                type="button"
                class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                @click="resolveStaffDelete('delete')"
            >
                {{ t('moderation.deleteWithoutPlaceholder') }}
            </button>
            <button
                type="button"
                class="px-3 py-2 text-sm text-ink/70 hover:underline"
                @click="resolveStaffDelete(null)"
            >
                {{ t('common.cancel') }}
            </button>
        </div>
    </Modal>
</template>
