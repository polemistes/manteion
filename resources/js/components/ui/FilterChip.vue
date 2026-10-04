<script setup lang="ts">
import { Check } from '@lucide/vue';
import { Toggle } from 'reka-ui';
import type { Component } from 'vue';
import CountBadge from './CountBadge.vue';

/**
 * A chip that is on or off (`aria-pressed`). Selected chips are ink, never
 * terracotta. `count` is a neutral tally, `badge` an attention count.
 */
const props = withDefaults(
    defineProps<{
        icon?: Component;
        count?: number;
        badge?: number;
        small?: boolean;
        disabled?: boolean;
    }>(),
    { icon: undefined, count: 0, badge: 0, small: false, disabled: false },
);

const pressed = defineModel<boolean>('pressed', { default: false });
</script>

<template>
    <Toggle
        v-model="pressed"
        :disabled="props.disabled"
        class="group relative inline-flex shrink-0 items-center gap-1.5 rounded-full bg-surface font-medium whitespace-nowrap text-ink ring-1 ring-line-strong transition-colors duration-200 ring-inset after:absolute hover:bg-surface-2 disabled:opacity-45 data-[state=on]:bg-ink data-[state=on]:text-canvas data-[state=on]:ring-transparent"
        :class="
            props.small
                ? 'h-8 px-3 text-meta after:inset-x-0 after:-inset-y-1.5'
                : 'h-9 px-3.5 text-label after:-inset-x-0.5 after:-inset-y-1'
        "
    >
        <Check
            v-if="props.small && pressed"
            :size="14"
            :stroke-width="2.2"
            aria-hidden="true"
        />
        <component
            :is="props.icon"
            v-else-if="props.icon"
            :size="16"
            :stroke-width="2"
            class="text-ink-muted group-data-[state=on]:text-canvas"
            aria-hidden="true"
        />
        <slot />
        <CountBadge :count="props.badge" />
        <span
            v-if="props.count > 0"
            class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-surface-3 px-[5px] text-kicker leading-[18px] tracking-normal text-ink tabular-nums group-data-[state=on]:bg-canvas/22 group-data-[state=on]:text-canvas"
        >
            {{ props.count }}
        </span>
    </Toggle>
</template>
