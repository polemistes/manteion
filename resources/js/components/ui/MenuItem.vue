<script setup lang="ts">
import { DropdownMenuItem } from 'reka-ui';
import type { Component } from 'vue';

/** One action in a Menu. `danger` items are red text with a red icon. */
const props = withDefaults(
    defineProps<{
        icon?: Component;
        danger?: boolean;
        hint?: string;
        disabled?: boolean;
        asChild?: boolean;
    }>(),
    { icon: undefined, danger: false, hint: undefined, disabled: false, asChild: false },
);

const emit = defineEmits<{ select: [event: Event] }>();
</script>

<template>
    <DropdownMenuItem
        :disabled="props.disabled"
        :as-child="props.asChild"
        class="flex h-11 w-full cursor-default items-center gap-3 rounded-sm px-3 text-left text-body outline-none select-none data-[disabled]:opacity-45 data-[highlighted]:bg-surface-2"
        :class="props.danger ? 'text-danger' : 'text-ink'"
        @select="emit('select', $event)"
    >
        <slot name="leading">
            <component
                :is="props.icon"
                v-if="props.icon"
                :size="18"
                :stroke-width="1.9"
                class="shrink-0"
                :class="props.danger ? 'text-danger' : 'text-ink-muted'"
                aria-hidden="true"
            />
        </slot>
        <span class="min-w-0 flex-1 truncate"><slot /></span>
        <span v-if="props.hint" class="text-meta text-ink-subtle">{{
            props.hint
        }}</span>
    </DropdownMenuItem>
</template>
