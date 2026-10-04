<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { EventSummary } from '@/events';
import { useDates } from '@/lib/dates';
import { noonOf } from './calendar';
import CalendarEventRow from './CalendarEventRow.vue';

/** The chosen day of the month grid: its events in time order. */
const props = defineProps<{
    day: string;
    events: EventSummary[];
    dayHref: string;
}>();

const { t } = useI18n();
const dates = useDates();

const heading = computed(() => {
    const days = dates.daysFromToday(noonOf(props.day));

    if (days === 0) {
        return t('dates.today');
    }

    if (days === 1) {
        return t('dates.tomorrow');
    }

    return dates.longDate(noonOf(props.day));
});
</script>

<template>
    <section aria-live="polite">
        <div class="flex items-baseline gap-3">
            <h2
                class="min-w-0 flex-1 font-display text-title-3 first-letter:uppercase"
            >
                {{ heading }}
                <span
                    v-if="heading !== dates.longDate(noonOf(props.day))"
                    class="ml-1 font-sans text-meta text-ink-muted"
                    >{{ dates.longDate(noonOf(props.day)) }}</span
                >
            </h2>
            <Link
                v-if="props.events.length"
                :href="props.dayHref"
                class="relative shrink-0 text-meta font-semibold text-ink underline underline-offset-2 after:absolute after:-inset-x-2 after:-inset-y-3"
            >
                {{ t('events.calendar.openDay') }}
            </Link>
        </div>
        <ul v-if="props.events.length" class="-mx-3 mt-2 flex flex-col">
            <li v-for="event in props.events" :key="event.id">
                <CalendarEventRow :event="event" :day="props.day" />
            </li>
        </ul>
        <p v-else class="mt-2 text-meta text-ink-muted">
            {{ t('events.calendar.nothingPlanned') }}
        </p>
    </section>
</template>
