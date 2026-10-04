<script setup lang="ts">
import { Link, router, usePoll } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarPlus,
    List,
    Navigation,
    Plus,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { noonOf } from '@/components/events/calendar';
import ComingUp from '@/components/events/ComingUp.vue';
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
import MiniMonth from '@/components/events/MiniMonth.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import IconButton from '@/components/ui/IconButton.vue';
import SearchField from '@/components/ui/SearchField.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import SegmentedLinks from '@/components/ui/SegmentedLinks.vue';
import { usePressedChipInView } from '@/composables/usePressedChipInView';
import type {
    CategoryOption,
    ContactOption,
    EventFilterValues,
    EventSummary,
    GroupOption,
} from '@/events';
import { useDates, weekendOf } from '@/lib/dates';
import {
    calendar as eventsCalendar,
    create as eventsCreate,
    index as eventsIndex,
    search as eventsSearch,
} from '@/routes/events';

const props = defineProps<{
    events: EventSummary[];
    pagination: PaginationSummary;
    invitations: EventSummary[];
    comingUp: EventSummary[];
    calendarDays: { month: string; days: string[] };
    categories: CategoryOption[];
    friends: ContactOption[];
    following: ContactOption[];
    friendGroups: GroupOption[];
    followingGroups: GroupOption[];
    filters: EventFilterValues & { near_label?: string; q?: string };
}>();

const { t } = useI18n();
const dates = useDates();

const today = computed(() => dates.todayKey());
const state = computed(() => stateFromFilters(props.filters, today.value));
const mine = computed(() => props.filters.mine ?? '');
const query = ref(props.filters.q ?? '');

// Quiet freshness: new events and answers arrive within a minute.
usePoll(60_000, { only: ['events', 'pagination', 'invitations', 'comingUp'] });

function visit(
    next: EventFilterState,
    nextMine = mine.value,
    q = query.value,
): void {
    router.get(
        eventsIndex().url,
        queryFromState(next, today.value, {
            mine: nextMine || undefined,
            q: q.trim() || undefined,
        }),
        { preserveState: true, replace: true },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit(state.value, mine.value, value), 400);
});

// Quick filters.
const weekend = computed(() => weekendOf(today.value));
const isWeekend = computed(
    () =>
        state.value.start_date === weekend.value.start &&
        state.value.end_date === weekend.value.end,
);
const isNear = computed(() => state.value.near_lat !== null);
const isAll = computed(() => !mine.value && !isWeekend.value && !isNear.value);

function setMine(value: string): void {
    visit(state.value, mine.value === value ? '' : value);
}

function toggleWeekend(on: boolean): void {
    visit(
        on
            ? {
                  ...state.value,
                  start_date: weekend.value.start,
                  end_date: weekend.value.end,
              }
            : { ...state.value, start_date: today.value, end_date: '' },
    );
}

const locating = ref(false);

function toggleNear(on: boolean): void {
    if (!on) {
        visit({
            ...state.value,
            near_lat: null,
            near_lng: null,
            near_label: null,
        });

        return;
    }

    if (!navigator.geolocation) {
        filtersOpen.value = true;

        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            locating.value = false;
            visit({
                ...state.value,
                near_lat: Number(position.coords.latitude.toFixed(5)),
                near_lng: Number(position.coords.longitude.toFixed(5)),
                near_label: null,
            });
        },
        () => {
            locating.value = false;
            filtersOpen.value = true;
        },
        { timeout: 10_000, maximumAge: 300_000 },
    );
}

function clearAll(): void {
    query.value = '';
    visit(emptyState(today.value), '', '');
}

const filtersOpen = ref(false);
const filterCount = computed(() => activeFilterCount(state.value, today.value));
const asideState = computed({
    get: () => state.value,
    set: (next: EventFilterState) => visit(next),
});

// Sections: waiting for an answer, today, this week, then by month.
const invitationIds = computed(
    () => new Set(props.invitations.map((event) => event.id)),
);

const waiting = computed(() =>
    mine.value || query.value ? [] : props.invitations,
);

interface FeedSection {
    key: string;
    title: string | null;
    meta?: string;
    events: EventSummary[];
}

const sections = computed<FeedSection[]>(() => {
    const events = props.events.filter(
        (event) => !waiting.value.length || !invitationIds.value.has(event.id),
    );

    // Sorted by distance, or narrowed by a search: one plain list.
    if (isNear.value || query.value.trim()) {
        return events.length ? [{ key: 'all', title: null, events }] : [];
    }

    const result: FeedSection[] = [];
    const thisMonth = today.value.slice(0, 7);
    const heading = (text: string) =>
        text.charAt(0).toUpperCase() + text.slice(1);

    for (const event of events) {
        const section = dates.section(event.starts_at);
        const dayKey = dates.dayKey(event.starts_at);
        let key: string;
        let title: string;
        let meta: string | undefined;

        if (section === 'today') {
            key = 'today';
            title = t('events.index.today');
            meta = dates.longDate(event.starts_at);
        } else if (section === 'thisWeek') {
            key = 'week';
            title = t('events.index.thisWeek');
        } else if (section === 'nextWeek') {
            key = 'nextWeek';
            title = t('events.index.nextWeek');
        } else {
            key = dayKey.slice(0, 7);

            if (key === thisMonth) {
                title = t('events.index.laterIn', {
                    month: dates.monthName(event.starts_at),
                });
            } else {
                title = heading(
                    key.slice(0, 4) === thisMonth.slice(0, 4)
                        ? dates.monthName(event.starts_at)
                        : dates.monthYear(event.starts_at),
                );
            }
        }

        const last = result[result.length - 1];

        if (last && last.key === key) {
            last.events.push(event);
        } else {
            result.push({ key, title, meta, events: [event] });
        }
    }

    return result;
});

const hasNarrowing = computed(
    () => !isAll.value || filterCount.value > 0 || query.value.trim() !== '',
);

// Today by the shared clock, so the server's render and the browser's agree.
const kicker = computed(() => {
    const today = noonOf(dates.todayKey());

    return `${dates.weekLabel(today)} · ${dates.monthYear(today)}`;
});

const views = computed(() => [
    {
        href: eventsIndex().url,
        label: t('events.index.list'),
        icon: List,
        active: true,
    },
    {
        // The calendar keeps the filters it shares with the list.
        href: eventsCalendar({
            query: {
                tags: state.value.tags,
                owner_filter: state.value.owner_filter,
                group_id: state.value.group_id || undefined,
                mine: mine.value || undefined,
            },
        }).url,
        label: t('events.index.calendar'),
        icon: CalendarDays,
        active: false,
    },
]);

// On a phone the chips scroll sideways: keep the pressed one in view.
const chipRow = ref<HTMLElement | null>(null);

usePressedChipInView(chipRow, () => props.filters);
</script>

<template>
    <PageHeader
        :title="t('events.index.title')"
        :kicker="kicker"
        :lede="t('events.index.lede')"
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
            <SegmentedLinks :links="views" :label="t('events.index.views')" />
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
                <div class="flex items-center gap-2">
                    <SearchField
                        v-model="query"
                        :label="t('events.index.search')"
                        class="min-w-0 flex-1 sm:max-w-[26rem]"
                    />
                    <SegmentedLinks
                        class="sm:hidden"
                        :links="
                            views.map((view) => ({ ...view, iconOnly: true }))
                        "
                        :label="t('events.index.views')"
                    />
                </div>

                <div
                    class="-mx-4 mt-3 scrollbar-none flex gap-2 overflow-x-auto fade-end px-4 pb-1 sm:mx-0 sm:flex-wrap sm:[mask-image:none] sm:px-0"
                    ref="chipRow"
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
                    <FilterChip :pressed="isAll" @update:pressed="clearAll">
                        {{ t('events.index.all') }}
                    </FilterChip>
                    <FilterChip
                        :pressed="mine === 'invited'"
                        :badge="props.invitations.length"
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
                    <FilterChip
                        :pressed="isWeekend"
                        @update:pressed="toggleWeekend"
                    >
                        {{ t('events.index.thisWeekend') }}
                    </FilterChip>
                    <FilterChip
                        :icon="Navigation"
                        :pressed="isNear"
                        :disabled="locating"
                        @update:pressed="toggleNear"
                    >
                        {{
                            locating
                                ? t('events.index.locating')
                                : t('events.index.nearMe')
                        }}
                    </FilterChip>
                </div>

                <div class="@container">
                    <template v-if="waiting.length">
                        <SectionHeader
                            :title="t('events.index.waiting')"
                            :count="waiting.length"
                        />
                        <ul class="flex flex-col gap-3">
                            <li
                                v-for="event in waiting"
                                :key="`invite-${event.id}`"
                            >
                                <EventCard :event="event" />
                            </li>
                        </ul>
                    </template>

                    <template v-for="section in sections" :key="section.key">
                        <SectionHeader
                            v-if="section.title"
                            :title="section.title"
                            :meta="section.meta"
                        />
                        <ul
                            class="flex flex-col gap-3"
                            :class="section.title ? '' : 'mt-6'"
                        >
                            <li v-for="event in section.events" :key="event.id">
                                <EventCard :event="event" />
                            </li>
                        </ul>
                    </template>
                </div>

                <template v-if="!waiting.length && !sections.length">
                    <EmptyState
                        v-if="hasNarrowing"
                        :icon="SlidersHorizontal"
                        :title="t('events.index.noMatchesTitle')"
                        :body="t('events.index.noMatchesBody')"
                    >
                        <Button @click="clearAll">
                            {{ t('events.index.clearFilters') }}
                        </Button>
                    </EmptyState>
                    <EmptyState
                        v-else
                        :icon="CalendarPlus"
                        :title="t('events.index.emptyTitle')"
                        :body="t('events.index.emptyBody')"
                    >
                        <div class="flex flex-wrap justify-center gap-2">
                            <Button
                                :as="Link"
                                :href="eventsCreate().url"
                                :icon="Plus"
                                variant="primary"
                            >
                                {{ t('events.index.createEvent') }}
                            </Button>
                            <Button :as="Link" :href="eventsSearch().url">
                                {{ t('events.search.browse') }}
                            </Button>
                        </div>
                    </EmptyState>
                </template>

                <Pagination
                    :pagination="props.pagination"
                    :only="['events', 'pagination']"
                />

                <p v-if="query.trim()" class="mt-6 text-center">
                    <Link
                        :href="eventsSearch({ query: { q: query.trim() } }).url"
                        class="relative text-label font-semibold text-ink underline underline-offset-2 after:absolute after:-inset-x-1 after:-inset-y-3"
                    >
                        {{
                            t('events.search.fromFeed', { query: query.trim() })
                        }}
                    </Link>
                </p>

                <div
                    v-if="
                        sections.length &&
                        !query.trim() &&
                        props.pagination.current_page ===
                            props.pagination.last_page
                    "
                    class="mt-10 flex flex-col items-center gap-2 text-center"
                >
                    <p class="font-display text-input text-ink-muted italic">
                        {{ t('events.index.endNote') }}
                    </p>
                    <p class="flex flex-wrap justify-center gap-x-5 gap-y-6">
                        <Link
                            :href="eventsCalendar().url"
                            class="relative text-label font-semibold text-ink underline underline-offset-2 after:absolute after:-inset-x-1 after:-inset-y-3"
                        >
                            {{ t('events.index.seeCalendar') }}
                        </Link>
                        <Link
                            :href="eventsSearch().url"
                            class="relative text-label font-semibold text-ink underline underline-offset-2 after:absolute after:-inset-x-1 after:-inset-y-3"
                        >
                            {{ t('events.search.browse') }}
                        </Link>
                    </p>
                </div>
            </div>

            <aside class="hidden xl:block">
                <div class="flex flex-col gap-4">
                    <MiniMonth
                        :month="props.calendarDays.month"
                        :days="props.calendarDays.days"
                    />
                    <ComingUp :events="props.comingUp" />
                    <section
                        class="rounded-lg border border-line bg-surface p-4"
                    >
                        <h2 class="mb-4 text-kicker text-ink-muted uppercase">
                            {{ t('events.filters.title') }}
                        </h2>
                        <EventFiltersForm
                            v-model="asideState"
                            compact
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
        :categories="props.categories"
        :friends="props.friends"
        :following="props.following"
        :friend-groups="props.friendGroups"
        :following-groups="props.followingGroups"
        @apply="visit"
    />
</template>
