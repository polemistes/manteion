<script setup lang="ts">
import { computed } from 'vue';
import { useDates } from '@/lib/dates';

/**
 * A date tile: weekday, the day in the serif, month. Today is tinted
 * terracotta. `sm` (44 wide) drops the month for lists; `lg` (64) sits
 * beside a title when an event has no image.
 */
const props = withDefaults(
    defineProps<{
        date: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 'md' },
);

const { tile, longDate } = useDates();
const parts = computed(() => tile(props.date));
</script>

<template>
    <span
        class="inline-flex shrink-0 flex-col items-center justify-center text-center"
        :class="[
            parts.isToday ? 'bg-accent-soft' : 'bg-surface-2',
            {
                'w-11 rounded-sm py-1.5': props.size === 'sm',
                'w-[58px] rounded-md pt-2 pb-[9px]': props.size === 'md',
                'w-16 rounded-md pt-2.5 pb-3': props.size === 'lg',
            },
        ]"
    >
        <span class="sr-only">{{ longDate(props.date) }}</span>
        <span
            class="text-kicker"
            :class="parts.isToday ? 'text-accent-ink' : 'text-ink-muted'"
            aria-hidden="true"
            >{{ parts.weekday }}</span
        >
        <span
            class="font-display text-ink lining-nums"
            :class="{
                'text-title-3 font-medium': props.size === 'sm',
                'my-[3px] text-numeral': props.size === 'md',
                'my-1 text-title-1': props.size === 'lg',
            }"
            aria-hidden="true"
            >{{ parts.day }}</span
        >
        <span
            v-if="props.size !== 'sm'"
            class="text-kicker"
            :class="parts.isToday ? 'text-accent-ink' : 'text-ink-muted'"
            aria-hidden="true"
            >{{ parts.month }}</span
        >
    </span>
</template>
