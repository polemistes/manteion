<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EventCard from '@/components/EventCard.vue';
import EventFilterPanel from '@/components/EventFilterPanel.vue';
import EventViewSwitch from '@/components/EventViewSwitch.vue';
import type {
    CategoryOption,
    ContactOption,
    EventFilterQuery,
    EventFilterValues,
    EventSummary,
    GroupOption,
} from '@/events';
import { dateFnsLocales } from '@/events';
import {
    calendar as eventsCalendar,
    create as eventsCreate,
} from '@/routes/events';

type CalendarView = 'month' | 'week' | 'day';

const props = defineProps<{
    events: EventSummary[];
    calendar: { view: CalendarView; date: string; today: string };
    categories: CategoryOption[];
    friends: ContactOption[];
    following: ContactOption[];
    friendGroups: GroupOption[];
    followingGroups: GroupOption[];
    filters: EventFilterValues;
}>();

const { t, locale } = useI18n();

usePoll(20000, { only: ['events'] });

// Days are grouped by the viewer's local date, which only the browser
// knows, so the grid renders after mount rather than on the server.
const mounted = ref(false);
onMounted(() => (mounted.value = true));

const views: CalendarView[] = ['month', 'week', 'day'];

function parseDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function toDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function addDays(date: Date, days: number): Date {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);

    return copy;
}

/** Monday-first weeks. */
function startOfWeek(date: Date): Date {
    const offset = (date.getDay() + 6) % 7;

    return addDays(
        new Date(date.getFullYear(), date.getMonth(), date.getDate()),
        -offset,
    );
}

function isoWeek(date: Date): number {
    const target = new Date(
        Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()),
    );
    const dayNumber = target.getUTCDay() || 7;
    target.setUTCDate(target.getUTCDate() + 4 - dayNumber);
    const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));

    return Math.ceil(
        ((target.getTime() - yearStart.getTime()) / 86400000 + 1) / 7,
    );
}

const localeCode = computed(() => dateFnsLocales[locale.value] ?? locale.value);
const anchor = computed(() => parseDate(props.calendar.date));
const todayKey = computed(() => toDateKey(new Date()));

const days = computed<Date[]>(() => {
    const view = props.calendar.view;

    if (view === 'day') {
        return [anchor.value];
    }

    if (view === 'week') {
        const start = startOfWeek(anchor.value);

        return Array.from({ length: 7 }, (_, i) => addDays(start, i));
    }

    const first = new Date(
        anchor.value.getFullYear(),
        anchor.value.getMonth(),
        1,
    );
    const last = new Date(
        anchor.value.getFullYear(),
        anchor.value.getMonth() + 1,
        0,
    );
    const start = startOfWeek(first);
    const end = addDays(startOfWeek(last), 6);
    const count = Math.round((end.getTime() - start.getTime()) / 86400000) + 1;

    return Array.from({ length: count }, (_, i) => addDays(start, i));
});

const eventsByDay = computed(() => {
    const map = new Map<string, EventSummary[]>();

    for (const event of props.events) {
        const key = toDateKey(new Date(event.starts_at));
        map.set(key, [...(map.get(key) ?? []), event]);
    }

    return map;
});

function eventsOn(date: Date): EventSummary[] {
    return eventsByDay.value.get(toDateKey(date)) ?? [];
}

const weekdayLabels = computed(() =>
    days.value
        .slice(0, 7)
        .map((day) =>
            day.toLocaleDateString(localeCode.value, { weekday: 'short' }),
        ),
);

const title = computed(() => {
    const view = props.calendar.view;

    if (view === 'month') {
        return anchor.value.toLocaleDateString(localeCode.value, {
            month: 'long',
            year: 'numeric',
        });
    }

    if (view === 'week') {
        const start = days.value[0];
        const end = days.value[6];
        const range = `${start.toLocaleDateString(localeCode.value, { day: 'numeric', month: 'short' })} – ${end.toLocaleDateString(localeCode.value, { day: 'numeric', month: 'short', year: 'numeric' })}`;

        return `${t('events.calendar.weekNumber', { week: isoWeek(anchor.value) })} · ${range}`;
    }

    return anchor.value.toLocaleDateString(localeCode.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

function formatTime(value: string): string {
    return new Date(value).toLocaleTimeString(localeCode.value, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function urlFor(view: CalendarView, date: string): string {
    return eventsCalendar({ query: { view, date, ...props.filters } }).url;
}

function shifted(step: number): string {
    const view = props.calendar.view;
    const base = anchor.value;

    if (view === 'month') {
        return toDateKey(
            new Date(base.getFullYear(), base.getMonth() + step, 1),
        );
    }

    return toDateKey(addDays(base, view === 'week' ? 7 * step : step));
}

function applyFilters(query: EventFilterQuery): void {
    router.get(
        eventsCalendar().url,
        { view: props.calendar.view, date: props.calendar.date, ...query },
        { preserveState: true, replace: true },
    );
}

function chipClass(event: EventSummary): string {
    if (event.my_status === 'attending') {
        return 'bg-accent text-on-accent';
    }

    if (event.my_status === 'interested') {
        return 'border-accent';
    }

    return 'border-edge';
}

function inMonth(date: Date): boolean {
    return date.getMonth() === anchor.value.getMonth();
}
</script>

<template>
    <Head :title="t('events.calendar.title')" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">{{ t('events.calendar.title') }}</h1>
        <div class="flex flex-wrap items-center gap-3">
            <EventViewSwitch active="calendar" />
            <Link
                :href="eventsCreate().url"
                class="border-edge text-sm hover:bg-surface-raised border bg-accent px-3 py-2 text-on-accent hover:text-ink"
            >
                {{ t('events.index.createEvent') }}
            </Link>
        </div>
    </div>

    <EventFilterPanel
        class="mb-6"
        :categories="categories"
        :friends="friends"
        :following="following"
        :friend-groups="friendGroups"
        :following-groups="followingGroups"
        :filters="filters"
        @change="applyFilters"
    />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-1">
            <Link
                :href="urlFor(calendar.view, shifted(-1))"
                class="border-edge text-sm border px-3 py-1 hover:bg-accent hover:text-on-accent"
                :aria-label="t('events.calendar.previous')"
            >
                ‹
            </Link>
            <Link
                :href="urlFor(calendar.view, calendar.today)"
                class="border-edge text-xs border px-3 py-1 hover:bg-accent hover:text-on-accent"
            >
                {{ t('events.calendar.today') }}
            </Link>
            <Link
                :href="urlFor(calendar.view, shifted(1))"
                class="border-edge text-sm border px-3 py-1 hover:bg-accent hover:text-on-accent"
                :aria-label="t('events.calendar.next')"
            >
                ›
            </Link>
        </div>

        <h2 v-if="mounted" class="text-base font-bold">{{ title }}</h2>

        <nav class="flex" :aria-label="t('events.calendar.viewSwitch')">
            <Link
                v-for="(view, index) in views"
                :key="view"
                :href="urlFor(view, calendar.date)"
                class="border-edge text-xs border px-3 py-1"
                :class="[
                    index > 0 ? '-ml-px' : '',
                    calendar.view === view
                        ? 'bg-accent text-on-accent'
                        : 'hover:bg-accent hover:text-on-accent',
                ]"
                :aria-current="calendar.view === view ? 'page' : undefined"
            >
                {{ t(`events.calendar.${view}`) }}
            </Link>
        </nav>
    </div>

    <template v-if="mounted">
        <!-- Month: a seven-column grid; on small screens each day shows a count and opens the day view. -->
        <div v-if="calendar.view === 'month'" class="border-frame border-edge">
            <div
                class="border-edge text-xs grid grid-cols-7 border-b text-center font-bold uppercase"
            >
                <div v-for="label in weekdayLabels" :key="label" class="py-1">
                    {{ label }}
                </div>
            </div>
            <div class="grid grid-cols-7">
                <div
                    v-for="day in days"
                    :key="toDateKey(day)"
                    class="border-edge/50 min-h-16 min-w-0 border-b border-l p-1 first:border-l-0 md:min-h-28 [&:nth-child(7n+1)]:border-l-0"
                    :class="inMonth(day) ? '' : 'text-ink/50'"
                >
                    <Link
                        :href="urlFor('day', toDateKey(day))"
                        class="text-xs mb-1 inline-block px-1"
                        :class="
                            toDateKey(day) === todayKey
                                ? 'bg-accent font-bold text-on-accent'
                                : 'hover:underline'
                        "
                    >
                        {{ day.getDate() }}
                    </Link>

                    <ul class="hidden flex-col gap-0.5 md:flex">
                        <li
                            v-for="event in eventsOn(day).slice(0, 3)"
                            :key="event.id"
                            class="min-w-0"
                        >
                            <Link
                                :href="`/events/${event.id}`"
                                class="text-xs block truncate border px-1"
                                :class="chipClass(event)"
                                :title="event.name"
                            >
                                {{ formatTime(event.starts_at) }}
                                {{ event.name }}
                            </Link>
                        </li>
                        <li v-if="eventsOn(day).length > 3">
                            <Link
                                :href="urlFor('day', toDateKey(day))"
                                class="text-xs block px-1 text-ink/70 hover:underline"
                            >
                                {{
                                    t('events.calendar.more', {
                                        count: eventsOn(day).length - 3,
                                    })
                                }}
                            </Link>
                        </li>
                    </ul>

                    <Link
                        v-if="eventsOn(day).length"
                        :href="urlFor('day', toDateKey(day))"
                        class="border-edge text-xs mx-auto block w-fit border px-1.5 md:hidden"
                    >
                        {{ eventsOn(day).length }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Week: one column a day on wide screens, stacked on narrow ones. -->
        <div
            v-else-if="calendar.view === 'week'"
            class="grid grid-cols-1 gap-3 md:grid-cols-7 md:gap-2"
        >
            <section
                v-for="day in days"
                :key="toDateKey(day)"
                class="border-frame border-edge min-w-0 p-2"
            >
                <Link
                    :href="urlFor('day', toDateKey(day))"
                    class="text-xs mb-2 block font-bold uppercase hover:underline"
                    :class="{ 'text-accent': toDateKey(day) === todayKey }"
                >
                    {{
                        day.toLocaleDateString(localeCode, {
                            weekday: 'short',
                            day: 'numeric',
                        })
                    }}
                </Link>
                <ul class="flex flex-col gap-1">
                    <li
                        v-for="event in eventsOn(day)"
                        :key="event.id"
                        class="min-w-0"
                    >
                        <Link
                            :href="`/events/${event.id}`"
                            class="text-xs block border px-2 py-1"
                            :class="chipClass(event)"
                        >
                            <span class="font-bold">{{
                                formatTime(event.starts_at)
                            }}</span>
                            <span class="block truncate">{{ event.name }}</span>
                        </Link>
                    </li>
                    <li
                        v-if="!eventsOn(day).length"
                        class="text-xs text-ink/50"
                    >
                        {{ t('events.calendar.noEvents') }}
                    </li>
                </ul>
            </section>
        </div>

        <!-- Day: the full cards, as in the list. -->
        <div v-else>
            <p v-if="!eventsOn(anchor).length" class="text-sm text-ink/70">
                {{ t('events.calendar.noEvents') }}
            </p>
            <ul class="grid grid-cols-1 gap-3 md:gap-4 xl:grid-cols-2">
                <li
                    v-for="event in eventsOn(anchor)"
                    :key="event.id"
                    class="h-full min-w-0"
                >
                    <EventCard :event="event" />
                </li>
            </ul>
        </div>
    </template>
</template>
