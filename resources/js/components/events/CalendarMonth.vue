<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { EventSummary } from '@/events';
import { isoWeekOfDay, useDates } from '@/lib/dates';
import { show as eventsShow } from '@/routes/events';
import { dayPart, noonOf } from './calendar';

/**
 * The month grid, built like the feed's mini-month at full size: ISO week
 * numbers first, today on the terracotta disc. Where there is room a day
 * holds two event chips, then "+N"; narrower, dots stand in for them.
 * Choosing a day shows its events in the list beside or below.
 */
const props = defineProps<{
    days: string[];
    month: string;
    eventsOn: Map<string, EventSummary[]>;
}>();

const selected = defineModel<string>('selected', { required: true });

const { t } = useI18n();
const dates = useDates();

const today = computed(() => dates.todayKey());

const weeks = computed(() =>
    Array.from({ length: props.days.length / 7 }, (_, index) => {
        const days = props.days.slice(index * 7, index * 7 + 7);

        return { week: isoWeekOfDay(days[0]), days };
    }),
);

const weekdays = computed(() =>
    props.days.slice(0, 7).map((day) => ({
        narrow: dates.weekday(noonOf(day), 'narrow'),
        short: dates.weekday(noonOf(day), 'short'),
        long: dates.weekday(noonOf(day), 'long'),
    })),
);

function eventsOf(day: string): EventSummary[] {
    return props.eventsOn.get(day) ?? [];
}

function chipClass(event: EventSummary): string {
    if (event.is_host || event.my_status === 'attending') {
        return 'bg-accent-soft font-semibold text-accent-ink';
    }

    if (event.my_status === 'interested') {
        return 'bg-surface text-ink ring-1 ring-line-strong ring-inset';
    }

    return 'bg-surface-2 text-ink';
}

/** The chip's tooltip carries the time the name has no room for. */
function chipTitle(event: EventSummary, day: string): string {
    const part = dayPart(event, day, dates.dayKey);

    return part === 'single' || part === 'first'
        ? `${dates.time(event.starts_at)} ${event.name}`
        : event.name;
}

function dayLabel(day: string): string {
    const count = eventsOf(day).length;

    return t(
        'events.calendar.dayLabel',
        { date: dates.longDate(noonOf(day)), n: count },
        count,
    );
}

function discClass(day: string): string {
    const isToday = day === today.value;
    const isSelected = day === selected.value;

    if (isToday) {
        return isSelected
            ? 'bg-accent font-semibold text-on-accent ring-2 ring-accent ring-offset-2 ring-offset-surface'
            : 'bg-accent font-semibold text-on-accent';
    }

    if (isSelected) {
        return 'bg-ink font-semibold text-canvas';
    }

    return day.startsWith(props.month)
        ? 'text-ink group-hover:bg-surface-2'
        : 'text-ink-subtle group-hover:bg-surface-2';
}
</script>

<template>
    <div class="@container">
        <div class="overflow-hidden rounded-lg border border-line bg-surface">
            <table class="w-full table-fixed border-collapse">
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="w-8 py-2 text-caption font-semibold text-ink-subtle @xl:w-10"
                        >
                            <span class="sr-only">{{
                                t('events.calendar.weekNumberLabel')
                            }}</span
                            ><span aria-hidden="true">{{
                                t('events.calendar.weekShort')
                            }}</span>
                        </th>
                        <th
                            v-for="(name, index) in weekdays"
                            :key="index"
                            scope="col"
                            class="py-2 text-kicker font-semibold text-ink-muted uppercase"
                        >
                            <abbr :title="name.long" class="no-underline">
                                <span class="@xl:hidden">{{ name.narrow }}</span
                                ><span class="hidden @xl:inline">{{
                                    name.short
                                }}</span>
                            </abbr>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="week in weeks" :key="week.days[0]">
                        <th
                            scope="row"
                            class="border-t border-line pt-2.5 align-top text-caption font-normal text-ink-subtle tabular-nums"
                        >
                            {{ week.week }}
                        </th>
                        <td
                            v-for="day in week.days"
                            :key="day"
                            class="group relative h-16 border-t border-l border-line p-1 align-top @xl:h-[7.5rem] @xl:p-1.5"
                            :class="
                                day.startsWith(props.month) ? '' : 'bg-canvas'
                            "
                        >
                            <!-- The day's button covers the whole cell; chips sit above it. -->
                            <button
                                type="button"
                                class="mx-auto grid size-8 place-items-center rounded-full text-label tabular-nums transition-colors duration-120 after:absolute after:inset-0 @xl:mx-0"
                                :class="discClass(day)"
                                :aria-label="dayLabel(day)"
                                :aria-pressed="day === selected"
                                :aria-current="
                                    day === today ? 'date' : undefined
                                "
                                @click="selected = day"
                            >
                                {{ Number(day.slice(8)) }}
                            </button>

                            <span
                                v-if="eventsOf(day).length"
                                class="mt-1 flex justify-center gap-[3px] @xl:hidden"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="event in eventsOf(day).slice(0, 3)"
                                    :key="event.id"
                                    class="size-[5px] rounded-full"
                                    :class="
                                        event.is_host ||
                                        event.my_status === 'attending'
                                            ? 'bg-accent-ink'
                                            : 'bg-ink-muted'
                                    "
                                />
                            </span>

                            <ul
                                v-if="eventsOf(day).length"
                                class="mt-1 hidden flex-col gap-0.5 @xl:flex"
                            >
                                <li
                                    v-for="event in eventsOf(day).slice(0, 2)"
                                    :key="event.id"
                                    class="min-w-0"
                                >
                                    <Link
                                        :href="eventsShow(event.id).url"
                                        class="relative z-10 block truncate rounded-xs px-1.5 py-[3px] text-caption"
                                        :class="chipClass(event)"
                                        :title="chipTitle(event, day)"
                                    >
                                        {{ event.name }}
                                    </Link>
                                </li>
                                <li v-if="eventsOf(day).length > 2">
                                    <button
                                        type="button"
                                        class="relative z-10 px-1.5 text-caption font-semibold text-ink-muted hover:text-ink"
                                        @click="selected = day"
                                    >
                                        {{
                                            t('events.calendar.more', {
                                                count: eventsOf(day).length - 2,
                                            })
                                        }}
                                    </button>
                                </li>
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
