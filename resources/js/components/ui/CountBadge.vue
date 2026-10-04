<script setup lang="ts">
import { computed } from 'vue';

/**
 * A count in a pill. `attention` (terracotta) marks things that need you;
 * `neutral` is a plain tally. Nothing renders for zero. `ring` draws a gap in
 * the parent's background (set `--badge-ring` on a non-canvas parent).
 */
const props = withDefaults(
    defineProps<{
        count: number;
        tone?: 'attention' | 'neutral';
        ring?: boolean;
        label?: string;
    }>(),
    { tone: 'attention', ring: false, label: undefined },
);

const text = computed(() => (props.count > 99 ? '99+' : String(props.count)));
</script>

<template>
    <span
        v-if="props.count > 0"
        class="inline-flex h-[18px] min-w-[18px] shrink-0 items-center justify-center rounded-full px-[5px] text-center text-kicker leading-[18px] tracking-normal tabular-nums"
        :class="[
            props.tone === 'attention'
                ? 'bg-accent text-on-accent'
                : 'bg-surface-3 text-ink',
            props.ring
                ? 'ring-2 ring-[var(--badge-ring,var(--color-canvas))]'
                : '',
        ]"
    >
        <span aria-hidden="true">{{ text }}</span>
        <span class="sr-only">{{ props.label ?? text }}</span>
    </span>
</template>
