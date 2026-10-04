<script setup lang="ts">
import { Primitive } from 'reka-ui';
import type { Component } from 'vue';

/**
 * One row of a list card: optional leading avatar or icon tile, a title
 * and sub line, and a trailing control. The divider above it starts where
 * the text starts (`inset`: 16 with nothing leading, 74 after a 44 avatar)
 * and is left out on the first row. An `interactive` row (a link or a
 * label) is itself the target.
 */
const props = withDefaults(
    defineProps<{
        as?: string | Component;
        title?: string;
        sub?: string;
        inset?: number;
        interactive?: boolean;
        divider?: boolean;
    }>(),
    {
        as: 'div',
        title: undefined,
        sub: undefined,
        inset: 16,
        interactive: false,
        divider: true,
    },
);
</script>

<template>
    <Primitive
        :as="props.as"
        class="group/row relative flex min-h-15 items-center gap-3.5 px-4 py-3"
        :class="
            props.interactive
                ? 'transition-colors duration-120 hover:bg-surface-2/55'
                : ''
        "
    >
        <span
            v-if="props.divider"
            class="pointer-events-none absolute top-0 right-0 h-px bg-line group-first/row:hidden"
            :style="{ left: `${props.inset}px` }"
            aria-hidden="true"
        />
        <slot name="leading" />
        <span class="min-w-0 flex-1">
            <span
                v-if="props.title || $slots.title"
                class="block truncate font-medium text-ink"
            >
                <slot name="title">{{ props.title }}</slot>
            </span>
            <span
                v-if="props.sub || $slots.sub"
                class="mt-0.5 block truncate text-meta text-ink-muted"
            >
                <slot name="sub">{{ props.sub }}</slot>
            </span>
            <slot />
        </span>
        <slot name="trailing" />
    </Primitive>
</template>
