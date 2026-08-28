<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Modal from '@/components/Modal.vue';
import { resolveConfirm, useConfirmState } from '@/composables/useConfirm';

const { t } = useI18n();

const state = useConfirmState();
</script>

<template>
    <Modal :open="state.isOpen" @update:open="resolveConfirm(false)">
        <p class="mb-6 text-sm">{{ state.message }}</p>
        <div class="flex justify-end gap-2">
            <button
                type="button"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
                @click="resolveConfirm(false)"
            >
                {{ state.cancelLabel ?? t('common.cancel') }}
            </button>
            <button
                type="button"
                class="border border-black bg-black px-3 py-2 text-sm text-white hover:bg-white hover:text-black"
                @click="resolveConfirm(true)"
            >
                {{ state.confirmLabel ?? t('common.delete') }}
            </button>
        </div>
    </Modal>
</template>
