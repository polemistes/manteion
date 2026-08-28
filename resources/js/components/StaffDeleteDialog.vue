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
                class="border border-black bg-black px-3 py-2 text-sm text-white hover:bg-white hover:text-black"
                @click="resolveStaffDelete('placeholder')"
            >
                {{ t('moderation.deleteWithPlaceholder') }}
            </button>
            <button
                type="button"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
                @click="resolveStaffDelete('delete')"
            >
                {{ t('moderation.deleteWithoutPlaceholder') }}
            </button>
            <button
                type="button"
                class="px-3 py-2 text-sm text-black/70 hover:underline"
                @click="resolveStaffDelete(null)"
            >
                {{ t('common.cancel') }}
            </button>
        </div>
    </Modal>
</template>
