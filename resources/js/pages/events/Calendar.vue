<script setup lang="ts">
import { Link, router, usePoll } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    List,
    Plus,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    daysOfView,
    eventsByDay,
    noonOf,
    shiftDate,
} from '@/components/events/calendar';
import type { CalendarView } from '@/components/events/calendar';
import CalendarDayAgenda from '@/components/events/CalendarDayAgenda.vue';
import CalendarEventRow from '@/components/events/CalendarEventRow.vue';
import CalendarMonth from '@/components/events/CalendarMonth.vue';
import EventCard from '@/components/events/EventCard.vue';
import EventFiltersForm from '@/components/events/EventFiltersForm.vue';
import type { EventFilterState } from '@/components/events/EventFiltersForm.vue';
import EventFilterSheet from '@/components/events/EventFilterSheet.vue';
import {
    activeFilterCount,
    emptyState,
    queryFromState,
    stateFromFilters,
} from '@/components/events/filters';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import DateBadge from '@/components/ui/DateBadge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import IconButton from '@/components/ui/IconButton.vue';
import SegmentedLinks from '@/components/ui/SegmentedLinks.vue';
import type {
    CategoryOption,
    ContactOption,
    EventFilterValues,
    EventSummary,
    GroupOption,
} from '@/events';
import { isoWeekOfDay, useDates } from '@/lib/dates';
import {
    calendar as eventsCalendar,
    create as eventsCreate,
    index as eventsIndex,
} from '@/routes/events';

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

const { t } = useI18n();
const dates = useDates();

usePoll(60_000, { only: ['events'] });

const view = computed(() => props.calendar.view);
const today = computed(() => dates.todayKey());
const state = computed(() => stateFromFilters(props.filters, today.value));
const mine = computed(() => props.filters.mine ?? '');

/** The filters that travel with every link: the list's and the calendar's. */
function filterQuery(
    next: EventFilterState = state.value,
    nextMine = mine.value,
): Record<string, string | string[]> {
    return queryFromState(next, today.value, { mine: nextMine || undefined });
}

function calendarUrl(nextView: CalendarView, date: string): string {
    return eventsCalendar({
        query: {
            ...filterQuery(),
            view: nextView === 'month' ? undefined : nextView,
            date: date === today.value ? undefined : date,
        },
    }).url;
}

function visit(next: EventFilterState, nextMine = mine.value): void {
    router.get(
        eventsCalendar().url,
        {
            ...filterQuery(next, nextMine),
            ...(view.value === 'month' ? {} : { view: view.value }),
            ...(props.calendar.date === today.value
                ? {}
                : { date: props.calendar.date }),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function setMine(value: string): void {
    visit(state.value, mine.value === value ? '' : value);
}

const filtersOpen = ref(false);
const filterCount = computed(() => activeFilterCount(state.value, today.value));
const hasNarrowing = computed(() => filterCount.value > 0 || mine.value !== '');
const asideState = computed({
    get: () => state.value,
    set: (next: EventFilterState) => visit(next),
});

function clearAll(): void {
    visit(emptyState(today.value), '');
}

// The days on show and what runs on each.
const days = computed(() => daysOfView(view.value, props.calendar.date));
const byDay = computed(() =>
    eventsByDay(props.events, days.value, dates.dayKey),
);
const month = computed(() => props.calendar.date.slice(0, 7));

function eventsOf(day: string): EventSummary[] {
    return byDay.value.get(day) ?? [];
}

// The month's chosen day: today when it's in the month, else the first
// day with something on.
const selected = ref(
    today.value.startsWith(month.value)
        ? today.value
        : (days.value.find(
              (day) => day.startsWith(month.value) && eventsOf(day).length,
          ) ?? props.calendar.date),
);

const periodTitle = computed(() => {
    if (view.value === 'month') {
        return dates.monthYear(noonOf(props.calendar.date));
    }

    if (view.value === 'week') {
        const first = days.value[0];
        const last = days.value[6];
        const range =
            first.slice(0, 7) === last.slice(0, 7)
                ? `${dates.dayNumber(noonOf(first))}–${dates.dayMonth(noonOf(last))}`
                : `${dates.dayMonth(noonOf(first))} – ${dates.dayMonth(noonOf(last))}`;

        return `${t('events.calendar.weekNumber', { week: isoWeekOfDay(first) })} · ${range}`;
    }

    return dates.longDate(noonOf(props.calendar.date));
});

/** On a phone the day view's title is "Sat 10 Oct", so it fits beside the arrows. */
const periodTitleShort = computed(() =>
    view.value === 'day'
        ? dates.shortDate(noonOf(props.calendar.date))
        : periodTitle.value,
);

const viewLinks = computed(() =>
    (['month', 'week', 'day'] as const).map((target) => ({
        href: calendarUrl(
            target,
            view.value === 'month' && target !== 'month'
                ? selected.value
                : props.calendar.date,
        ),
        label: t(`events.calendar.${target}`),
        active: view.value === target,
    })),
);

const pageViews = computed(() => [
    {
        href: eventsIndex({ query: filterQuery() }).url,
        label: t('events.index.list'),
        icon: List,
        active: false,
    },
    {
        href: calendarUrl(view.value, props.calendar.date),
        label: t('events.index.calendar'),
        icon: CalendarDays,
        active: true,
    },
]);
</script>

<template>
    <PageHeader
        :title="t('events.calendar.title')"
        :document-title="`${periodTitle} · ${t('events.calendar.title')}`"
        width="wide"
        root
    >
        <template #phone-actions>
            <IconButton
                :as="Link"
                :href="eventsCreate().url"
                :icon="Plus"
                :label="t('events.index.createEvent')"
                variant="create"
            />
        </template>
        <template #actions>
            <SegmentedLinks
                :links="pageViews"
                :label="t('events.index.views')"
            />
            <Button
                :as="Link"
                :href="eventsCreate().url"
                :icon="Plus"
                variant="primary"
            >
                {{ t('events.index.createEvent') }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer width="wide" class="pt-3 sm:pt-0">
        <div
            class="xl:grid xl:grid-cols-[minmax(0,1fr)_var(--spacing-aside)] xl:gap-12"
        >
            <div class="min-w-0">
                <!-- Phone: the view switches get a row of their own. -->
                <div
                    class="mb-4 flex items-center justify-between gap-2 sm:hidden"
                >
                    <SegmentedLinks
                        :links="viewLinks"
                        :label="t('events.calendar.viewSwitch')"
                    />
                    <SegmentedLinks
                        :links="
                            pageViews.map((link) => ({
                                ...link,
                                iconOnly: true,
                            }))
                        "
                        :label="t('events.index.views')"
                    />
                </div>

                <div class="flex items-center gap-1">
                    <h2
                        class="min-w-0 flex-1 truncate font-display text-title-3 first-letter:uppercase sm:text-title-2"
                    >
                        <span class="sm:hidden">{{ periodTitleShort }}</span>
                        <span class="hidden sm:inline">{{ periodTitle }}</span>
                    </h2>
                    <IconButton
                        :as="Link"
                        :href="
                            calendarUrl(
                                view,
                                shiftDate(view, props.calendar.date, -1),
                            )
                        "
                        :icon="ChevronLeft"
                        :label="t('events.calendar.previous')"
                    />
                    <Button
                        :as="Link"
                        :href="calendarUrl(view, today)"
                        size="sm"
                    >
                        {{ t('events.calendar.today') }}
                    </Button>
                    <IconButton
                        :as="Link"
                        :href="
                            calendarUrl(
                                view,
                                shiftDate(view, props.calendar.date, 1),
                            )
                        "
                        :icon="ChevronRight"
                        :label="t('events.calendar.next')"
                    />
                    <div class="ml-3 hidden sm:block">
                        <SegmentedLinks
                            :links="viewLinks"
                            :label="t('events.calendar.viewSwitch')"
                        />
                    </div>
                </div>

                <div
                    class="-mx-4 mt-3 scrollbar-none flex gap-2 overflow-x-auto fade-end px-4 pb-1 sm:mx-0 sm:flex-wrap sm:[mask-image:none] sm:px-0"
                    role="group"
                    :aria-label="t('events.index.quick')"
                >
                    <FilterChip
                        class="xl:hidden"
                        :icon="SlidersHorizontal"
                        :count="filterCount"
                        :pressed="false"
                        @update:pressed="filtersOpen = true"
                    >
                        {{ t('events.index.filters') }}
                    </FilterChip>
                    <FilterChip :pressed="!mine" @update:pressed="setMine('')">
                        {{ t('events.index.all') }}
                    </FilterChip>
                    <FilterChip
                        :pressed="mine === 'invited'"
                        @update:pressed="setMine('invited')"
                    >
                        {{ t('events.index.invitations') }}
                    </FilterChip>
                    <FilterChip
                        :pressed="mine === 'going'"
                        @update:pressed="setMine('going')"
                    >
                        {{ t('events.index.going') }}
                    </FilterChip>
                    <FilterChip
                        :pressed="mine === 'hosting'"
                        @update:pressed="setMine('hosting')"
                    >
                        {{ t('events.index.hosting') }}
                    </FilterChip>
                </div>

                <div
                    v-if="!props.events.length && hasNarrowing"
                    class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md bg-surface-2 px-4 py-3 text-meta text-ink-muted"
                >
                    <span class="min-w-0 flex-1">{{
                        t('events.calendar.noMatches')
                    }}</span>
                    <Button size="sm" @click="clearAll">
                        {{ t('events.index.clearFilters') }}
                    </Button>
                </div>

                <template v-if="view === 'month'">
                    <CalendarMonth
                        v-model:selected="selected"
                        class="mt-4"
                        :days="days"
                        :month="month"
                        :events-on="byDay"
                    />
                    <CalendarDayAgenda
                        class="mt-6 xl:hidden"
                        :day="selected"
                        :events="eventsOf(selected)"
                        :day-href="calendarUrl('day', selected)"
                    />
                </template>

                <ol v-else-if="view === 'week'" class="mt-4">
                    <li
                        v-for="day in days"
                        :key="day"
                        class="flex gap-4 border-t border-line py-3 first:border-t-0"
                    >
                        <Link
                            :href="calendarUrl('day', day)"
                            class="shrink-0 self-start rounded-sm"
                            :aria-label="dates.longDate(noonOf(day))"
                        >
                            <DateBadge :date="noonOf(day)" size="sm" />
                        </Link>
                        <ul
                            v-if="eventsOf(day).length"
                            class="-mx-3 min-w-0 flex-1"
                        >
                            <li v-for="event in eventsOf(day)" :key="event.id">
                                <CalendarEventRow :event="event" :day="day" />
                            </li>
                        </ul>
                        <p
                            v-else
                            class="min-w-0 flex-1 self-center text-meta text-ink-subtle"
                        >
                            {{ t('events.calendar.nothingPlanned') }}
                        </p>
                    </li>
                </ol>

                <div v-else class="@container mt-4">
                    <ul
                        v-if="eventsOf(props.calendar.date).length"
                        class="flex flex-col gap-3"
                    >
                        <li
                            v-for="event in eventsOf(props.calendar.date)"
                            :key="event.id"
                        >
                            <EventCard :event="event" />
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        :icon="CalendarPlus"
                        :title="t('events.calendar.nothingPlanned')"
                        :body="t('events.calendar.nothingPlannedBody')"
                    >
                        <Button
                            :as="Link"
                            :href="eventsCreate().url"
                            :icon="Plus"
                            variant="primary"
                        >
                            {{ t('events.index.createEvent') }}
                        </Button>
                    </EmptyState>
                </div>
            </div>

            <aside class="hidden xl:block">
                <div class="flex flex-col gap-4">
                    <div
                        v-if="view === 'month'"
                        class="rounded-lg border border-line bg-surface p-4"
                    >
                        <CalendarDayAgenda
                            :day="selected"
                            :events="eventsOf(selected)"
                            :day-href="calendarUrl('day', selected)"
                        />
                    </div>
                    <section
                        class="rounded-lg border border-line bg-surface p-4"
                    >
                        <h2 class="mb-4 text-kicker text-ink-muted uppercase">
                            {{ t('events.filters.title') }}
                        </h2>
                        <EventFiltersForm
                            v-model="asideState"
                            compact
                            :with-place-and-dates="false"
                            :categories="props.categories"
                            :friends="props.friends"
                            :following="props.following"
                            :friend-groups="props.friendGroups"
                            :following-groups="props.followingGroups"
                        />
                    </section>
                </div>
            </aside>
        </div>
    </PageContainer>

    <EventFilterSheet
        v-model:open="filtersOpen"
        :state="state"
        :with-place-and-dates="false"
        :categories="props.categories"
        :friends="props.friends"
        :following="props.following"
        :friend-groups="props.friendGroups"
        :following-groups="props.followingGroups"
        @apply="visit"
    />
</template>
