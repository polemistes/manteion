<script setup lang="ts">
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
    DialogTrigger,
} from 'reka-ui';

/**
 * One component for both a bottom sheet (under 640px) and a centred dialog
 * (from 640). CSS decides, so server and browser render the same markup.
 * Put the buttons in the `actions` slot: they stack full width on phones,
 * main action on top, and sit right-aligned on larger screens.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        hideTitle?: boolean;
        size?: 'md' | 'lg';
    }>(),
    { description: undefined, hideTitle: false, size: 'md' },
);

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-scrim data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[fade-in_280ms_var(--ease-enter)]"
            />
            <DialogContent
                class="fixed inset-x-0 bottom-0 z-50 max-h-[90dvh] w-full overflow-auto overscroll-contain rounded-t-xl bg-surface px-4 pt-2 pb-[calc(16px+env(safe-area-inset-bottom))] text-ink shadow-lg focus:outline-none data-[state=closed]:animate-[sheet-out_200ms_var(--ease-exit)] data-[state=open]:animate-[sheet-in_280ms_var(--ease-enter)] sm:inset-auto sm:top-1/2 sm:left-1/2 sm:w-[calc(100vw-32px)] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl sm:p-6 sm:data-[state=closed]:animate-[dialog-out_200ms_var(--ease-exit)] sm:data-[state=open]:animate-[dialog-in_280ms_var(--ease-enter)]"
                :class="props.size === 'lg' ? 'sm:max-w-[560px]' : 'sm:max-w-[440px]'"
                v-bind="
                    props.description ? {} : { 'aria-describedby': undefined }
                "
            >
                <div
                    class="mx-auto mb-3.5 h-1 w-9 rounded-full bg-line-strong sm:hidden"
                    aria-hidden="true"
                />
                <DialogTitle
                    class="font-display text-title-3 text-pretty"
                    :class="{ 'sr-only': props.hideTitle }"
                >
                    {{ props.title }}
                </DialogTitle>
                <DialogDescription
                    v-if="props.description"
                    class="mt-1.5 text-ink-muted"
                >
                    {{ props.description }}
                </DialogDescription>
                <div v-if="$slots.default" :class="{ 'mt-5': !props.hideTitle }">
                    <slot />
                </div>
                <div
                    v-if="$slots.actions"
                    class="mt-5 flex flex-col gap-2 sm:flex-row-reverse sm:justify-start"
                >
                    <slot name="actions" />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
