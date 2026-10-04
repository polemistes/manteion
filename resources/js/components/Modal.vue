<script setup lang="ts">
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';

/**
 * The older modal API (`open` + default slot), drawn as the sheet/dialog.
 * New code uses ui/AppDialog, which also takes a title and actions.
 */
const props = withDefaults(defineProps<{ open: boolean; title?: string }>(), {
    title: undefined,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <DialogRoot :open="props.open" @update:open="emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-scrim data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[fade-in_280ms_var(--ease-enter)]"
            />
            <DialogContent
                :aria-describedby="undefined"
                class="fixed inset-x-0 bottom-0 z-50 max-h-[90dvh] w-full overflow-auto overscroll-contain rounded-t-xl bg-surface px-4 pt-4 pb-[calc(16px+env(safe-area-inset-bottom))] text-ink shadow-lg focus:outline-none data-[state=closed]:animate-[sheet-out_200ms_var(--ease-exit)] data-[state=open]:animate-[sheet-in_280ms_var(--ease-enter)] sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-[calc(100vw-32px)] sm:max-w-[440px] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl sm:p-6 sm:data-[state=closed]:animate-[dialog-out_200ms_var(--ease-exit)] sm:data-[state=open]:animate-[dialog-in_280ms_var(--ease-enter)]"
            >
                <DialogTitle
                    :class="
                        props.title
                            ? 'mb-4 font-display text-title-3'
                            : 'sr-only'
                    "
                >
                    {{ props.title ?? '' }}
                </DialogTitle>
                <slot />
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
