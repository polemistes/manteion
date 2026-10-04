<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';
import { addDays, isoWeekday, isoWeekOfDay, useDates } from '@/lib/dates';
import { calendar as eventsCalendar } from '@/routes/events';

/**
 * This month at a glance, with ISO week numbers: a dot under days that
 * have events, today in terracotta. Days and arrows open the calendar.
 */
const props = defineProps<{
    month: string;
    days: string[];
}>();

const { t } = useI18n();
const dates = useDates();

const today = computed(() => dates.todayKey());
const eventDays = computed(() => new Set(props.days));

const title = computed(() => dates.monthYear(`${props.month}-15T12:00:00Z`));

const weekdayNames = computed(() =>
    Array.from({ length: 7 }, (_, index) =>
        new Intl.DateTimeFormat(dates.intlLocale.value, {
            weekday: 'narrow',
            timeZone: 'UTC',
        }).format(new Date(Date.UTC(2024, 0, 1 + index))),
    ),
);

const weeks = computed(() => {
    const first = `${props.month}-01`;
    const start = addDays(first, 1 - isoWeekday(first));
    const result: {
        week: number;
        days: { key: string; day: number; inMonth: boolean }[];
    }[] = [];
    let cursor = start;

    while (result.length < 6) {
        const days = Array.from({ length: 7 }, (_, index) => {
            const key = addDays(cursor, index);

            return {
                key,
                day: Number(key.slice(8)),
                inMonth: key.startsWith(props.month),
            };
        });

        result.push({ week: isoWeekOfDay(cursor), days });
        cursor = addDays(cursor, 7);

        if (!cursor.startsWith(props.month)) {
            break;
        }
    }

    return result;
});

function monthOffset(offset: number): string {
    const [year, month] = props.month.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1 + offset, 1));

    return date.toISOString().slice(0, 10);
}
</script>

<template>
    <section class="rounded-lg border border-line bg-surface p-4">
        <div class="mb-2 flex items-center gap-1">
            <h2 class="min-w-0 flex-1 font-display text-title-4 capitalize">
                {{ title }}
            </h2>
            <IconButton
                :as="Link"
                :href="
                    eventsCalendar({
                        query: { view: 'month', date: monthOffset(-1) },
                    }).url
                "
                :icon="ChevronLeft"
                :label="t('events.calendar.previous')"
                size="sm"
            />
            <IconButton
                :as="Link"
                :href="
                    eventsCalendar({
                        query: { view: 'month', date: monthOffset(1) },
                    }).url
                "
                :icon="ChevronRight"
                :label="t('events.calendar.next')"
                size="sm"
            />
        </div>
        <table class="w-full table-fixed text-center text-meta tabular-nums">
            <thead>
                <tr class="text-kicker text-ink-subtle">
                    <th class="w-8 py-1 font-semibold" scope="col">
                        <span class="sr-only">{{
                            t('events.calendar.weekNumberLabel')
                        }}</span
                        ><span aria-hidden="true">{{
                            t('events.calendar.weekShort')
                        }}</span>
                    </th>
                    <th
                        v-for="(name, index) in weekdayNames"
                        :key="index"
                        class="py-1 font-semibold"
                        scope="col"
                    >
                        {{ name }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="week in weeks" :key="week.week">
                    <td class="py-0.5 text-caption text-ink-subtle">
                        {{ week.week }}
                    </td>
                    <td v-for="day in week.days" :key="day.key" class="py-0.5">
                        <Link
                            :href="
                                eventsCalendar({
                                    query: { view: 'day', date: day.key },
                                }).url
                            "
                            class="relative mx-auto grid size-9 place-items-center rounded-full text-label transition-colors duration-120"
                            :class="[
                                day.key === today
                                    ? 'bg-accent font-semibold text-on-accent'
                                    : day.inMonth
                                      ? 'text-ink hover:bg-surface-2'
                                      : 'text-ink-subtle hover:bg-surface-2',
                            ]"
                            :aria-current="
                                day.key === today ? 'date' : undefined
                            "
                        >
                            {{ day.day }}
                            <span
                                v-if="eventDays.has(day.key)"
                                class="absolute bottom-1 size-1 rounded-full"
                                :class="
                                    day.key === today
                                        ? 'bg-on-accent'
                                        : 'bg-ink-muted'
                                "
                                aria-hidden="true"
                            />
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
