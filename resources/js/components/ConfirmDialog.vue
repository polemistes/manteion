<script setup lang="ts">
import { Trash } from '@lucide/vue';
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import { resolveConfirm, useConfirmState } from '@/composables/useConfirm';

/** The one confirm dialog behind `confirmAction()`; mounted by the shell. */
const { t } = useI18n();

const state = useConfirmState();

function onOpenChange(open: boolean): void {
    if (!open && state.isOpen) {
        resolveConfirm(false);
    }
}
</script>

<template>
    <AlertDialogRoot :open="state.isOpen" @update:open="onOpenChange">
        <AlertDialogPortal>
            <AlertDialogOverlay
                class="fixed inset-0 z-50 bg-scrim data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[fade-in_280ms_var(--ease-enter)]"
            />
            <AlertDialogContent
                class="fixed inset-x-0 bottom-0 z-50 w-full rounded-t-xl bg-surface px-4 pt-5 pb-[calc(16px+env(safe-area-inset-bottom))] text-ink shadow-lg focus:outline-none data-[state=closed]:animate-[sheet-out_200ms_var(--ease-exit)] data-[state=open]:animate-[sheet-in_280ms_var(--ease-enter)] sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-[calc(100vw-32px)] sm:max-w-[440px] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl sm:p-6 sm:data-[state=closed]:animate-[dialog-out_200ms_var(--ease-exit)] sm:data-[state=open]:animate-[dialog-in_280ms_var(--ease-enter)]"
            >
                <AlertDialogTitle
                    class="font-display text-title-3 text-pretty"
                    :class="{ 'sr-only': !state.title }"
                >
                    {{ state.title ?? state.message }}
                </AlertDialogTitle>
                <AlertDialogDescription
                    class="text-ink-muted"
                    :class="{ 'mt-1.5': state.title }"
                >
                    {{ state.message }}
                </AlertDialogDescription>
                <div
                    class="mt-5 flex flex-col gap-2 sm:flex-row-reverse sm:justify-start"
                >
                    <AlertDialogAction as-child>
                        <Button
                            :variant="
                                state.tone === 'primary' ? 'primary' : 'danger'
                            "
                            :icon="state.tone === 'primary' ? undefined : Trash"
                            @click="resolveConfirm(true)"
                        >
                            {{ state.confirmLabel ?? t('common.delete') }}
                        </Button>
                    </AlertDialogAction>
                    <AlertDialogCancel as-child>
                        <Button variant="ghost" @click="resolveConfirm(false)">
                            {{ state.cancelLabel ?? t('common.cancel') }}
                        </Button>
                    </AlertDialogCancel>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
