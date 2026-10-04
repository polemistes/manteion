<script setup lang="ts">
import type { Component } from 'vue';

/** A label that isn't interactive: an event category, "Private". */
const props = withDefaults(
    defineProps<{
        icon?: Component;
        tone?: 'default' | 'success' | 'host';
    }>(),
    { icon: undefined, tone: 'default' },
);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap"
        :class="{
            'h-[30px] rounded-full bg-surface-2 px-3 text-meta font-medium text-ink-muted':
                props.tone === 'default',
            'rounded-xs bg-success-soft px-[7px] py-[3px] text-kicker text-success uppercase':
                props.tone === 'success',
            'h-5 rounded-xs bg-surface-2 px-[7px] text-kicker text-ink-muted uppercase':
                props.tone === 'host',
        }"
    >
        <component
            :is="props.icon"
            v-if="props.icon"
            :size="14"
            :stroke-width="2.2"
            aria-hidden="true"
        />
        <slot />
    </span>
</template>
