<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import { useDates } from '@/lib/dates';

/**
 * The cover of an event without an image: its date, set large. With the
 * event's `timeZone`, its local time follows the viewer's when the two
 * clocks differ.
 */
const props = defineProps<{
    date: string;
    end?: string | null;
    timeZone?: string | null;
}>();

const { tile, monthYear, time, longDate, elsewhere } = useDates();
const parts = computed(() => tile(props.date));
const there = computed(() =>
    elsewhere(props.date, props.end ?? null, props.timeZone, 'full'),
);
</script>

<template>
    <div class="rounded-lg border border-line bg-surface px-6 py-[22px]">
        <span class="sr-only">{{ longDate(props.date) }}</span>
        <p
            class="text-kicker"
            :class="parts.isToday ? 'text-accent-ink' : 'text-ink-muted'"
            aria-hidden="true"
        >
            {{ parts.weekday }}
        </p>
        <p
            class="mt-3 mb-1.5 font-display text-leaf text-ink lining-nums"
            aria-hidden="true"
        >
            {{ parts.day }}
        </p>
        <p
            class="font-display text-title-3 font-normal text-ink-muted italic"
            aria-hidden="true"
        >
            {{ monthYear(props.date) }}
        </p>
        <div
            class="mt-4 flex flex-wrap items-center gap-x-2 border-t border-line pt-3.5 font-semibold"
        >
            <Clock
                :size="16"
                :stroke-width="2"
                class="text-ink-muted"
                aria-hidden="true"
            />
            <span
                >{{ time(props.date)
                }}<template v-if="props.end"
                    >–{{ time(props.end) }}</template
                ></span
            >
            <span v-if="there" class="text-meta font-normal text-ink-muted">{{
                there
            }}</span>
        </div>
    </div>
</template>
