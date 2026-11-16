<script setup lang="ts">
import { computed } from 'vue';
import { useDates } from '@/lib/dates';

/**
 * An event's time where it happens ("20:00 local time"), as a quiet chip
 * to sit after the viewer's own time. Renders nothing for an event on the
 * viewer's own clock, which is nearly all of them.
 */
const props = defineProps<{
    startsAt: string | null;
    endsAt: string | null;
    timeZone: string | null | undefined;
}>();

const dates = useDates();

const text = computed(() =>
    props.startsAt
        ? dates.elsewhere(props.startsAt, props.endsAt, props.timeZone)
        : null,
);
</script>

<template>
    <span
        v-if="text"
        class="inline-block rounded-xs bg-inset px-1.5 font-medium whitespace-nowrap text-ink-muted"
        >{{ text }}</span
    >
</template>
