<script setup lang="ts">
import {
    DropdownMenuContent,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';

/**
 * A dropdown menu. The `trigger` slot is the button that opens it (rendered
 * as-child, so it keeps its own look); items go in the default slot.
 */
const props = withDefaults(
    defineProps<{
        align?: 'start' | 'end' | 'center';
        side?: 'top' | 'bottom';
    }>(),
    { align: 'end', side: 'bottom' },
);

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <DropdownMenuRoot v-model:open="open" :modal="false">
        <DropdownMenuTrigger as-child>
            <slot name="trigger" />
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                :align="props.align"
                :side="props.side"
                :side-offset="4"
                :collision-padding="16"
                class="z-50 max-w-[calc(100vw-32px)] min-w-58 rounded-md bg-surface p-1.5 text-ink shadow-lg ring-1 ring-line data-[state=closed]:animate-[fade-out_120ms_var(--ease-exit)] data-[state=open]:animate-[dialog-in_160ms_var(--ease-enter)]"
            >
                <slot />
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
