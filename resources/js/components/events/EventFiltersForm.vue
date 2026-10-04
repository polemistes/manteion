<script setup lang="ts">
import { LocateFixed, MapPin } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import SearchField from '@/components/ui/SearchField.vue';
import Switch from '@/components/ui/Switch.vue';
import TextField from '@/components/ui/TextField.vue';
import { useNominatimSearch } from '@/composables/useNominatimSearch';
import type { NominatimResult } from '@/composables/useNominatimSearch';
import type {
    CategoryOption,
    ContactOption,
    GroupOption,
    TagOption,
} from '@/events';
import { addDays, useDates, weekendOf } from '@/lib/dates';

export interface EventFilterState {
    tags: string[];
    owner_filter: string[];
    group_id: string;
    start_date: string;
    end_date: string;
    near_lat: number | null;
    near_lng: number | null;
    near_label: string | null;
}

/**
 * The full set of event filters: near me, dates, categories, organizers
 * and contact groups. Used in the Filters sheet (applied with "Show
 * events") and in the desktop aside (applied as you change them).
 */
const props = withDefaults(
    defineProps<{
        categories: CategoryOption[];
        friends: ContactOption[];
        following: ContactOption[];
        friendGroups: GroupOption[];
        followingGroups: GroupOption[];
        /** In a narrow aside: categories start as their main groups only. */
        compact?: boolean;
        /** The calendar picks its own dates and has no distance order. */
        withPlaceAndDates?: boolean;
    }>(),
    { compact: false, withPlaceAndDates: true },
);

const model = defineModel<EventFilterState>({ required: true });

const { t, te, locale } = useI18n();
const dates = useDates();

function patch(changes: Partial<EventFilterState>): void {
    model.value = { ...model.value, ...changes };
}

// Near me: the device's location, or a place searched for.
const locating = ref(false);
const locationError = ref<string | null>(null);
const placeQuery = ref('');
const {
    results: placeResults,
    search: searchPlaces,
    clearResults,
} = useNominatimSearch(() => locale.value);

const nearOn = computed({
    get: () => model.value.near_lat !== null,
    set: (on: boolean) => {
        if (!on) {
            patch({ near_lat: null, near_lng: null, near_label: null });

            return;
        }

        useLocation();
    },
});

function useLocation(): void {
    locationError.value = null;

    if (!navigator.geolocation) {
        locationError.value = t('events.index.locationFailed');

        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            locating.value = false;
            patch({
                near_lat: Number(position.coords.latitude.toFixed(5)),
                near_lng: Number(position.coords.longitude.toFixed(5)),
                near_label: null,
            });
        },
        () => {
            locating.value = false;
            locationError.value = t('events.index.locationFailed');
        },
        { enableHighAccuracy: false, timeout: 10_000, maximumAge: 300_000 },
    );
}

function onPlaceInput(value: string): void {
    placeQuery.value = value;
    searchPlaces(value);
}

function pickPlace(result: NominatimResult): void {
    patch({
        near_lat: Number(Number(result.lat).toFixed(5)),
        near_lng: Number(Number(result.lon).toFixed(5)),
        near_label: result.display_name.split(',')[0],
    });
    placeQuery.value = '';
    clearResults();
}

// Dates: presets, or a from/to range.
const today = computed(() => dates.todayKey());

const presets = computed(() => {
    const weekend = weekendOf(today.value);

    return [
        {
            key: 'any',
            label: t('events.filters.anyTime'),
            start: today.value,
            end: '',
        },
        {
            key: 'today',
            label: t('events.filters.today'),
            start: today.value,
            end: today.value,
        },
        {
            key: 'weekend',
            label: t('events.filters.thisWeekend'),
            start: weekend.start,
            end: weekend.end,
        },
        {
            key: 'week',
            label: t('events.filters.next7'),
            start: today.value,
            end: addDays(today.value, 6),
        },
    ];
});

const activePreset = computed(
    () =>
        presets.value.find(
            (preset) =>
                preset.start === (model.value.start_date || today.value) &&
                preset.end === model.value.end_date,
        )?.key ?? null,
);

function pickPreset(key: string): void {
    const preset = presets.value.find((item) => item.key === key);

    if (preset) {
        patch({ start_date: preset.start, end_date: preset.end });
    }
}

// Categories: a parent stands for all of its children. In compact mode
// only the main groups show until asked, unless a subcategory is chosen.
const showAllCategories = ref(false);
const categoriesExpanded = computed(
    () =>
        !props.compact ||
        showAllCategories.value ||
        props.categories.some((category) =>
            category.children.some((child) => hasTag(child.slug)),
        ),
);

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function hasTag(slug: string): boolean {
    return model.value.tags.includes(slug);
}

function toggleTag(slug: string, on: boolean): void {
    patch({
        tags: on
            ? [...new Set([...model.value.tags, slug])]
            : model.value.tags.filter((tag) => tag !== slug),
    });
}

// Organizers: friends, people I follow, or specific people.
function hasOwner(token: string): boolean {
    return model.value.owner_filter.includes(token);
}

function toggleOwner(token: string, on: boolean): void {
    patch({
        owner_filter: on
            ? [...new Set([...model.value.owner_filter, token])]
            : model.value.owner_filter.filter((item) => item !== token),
    });
}

const peopleQuery = ref('');
const showAllPeople = ref(false);
const PEOPLE_SHOWN = 6;

const people = computed(() => {
    const byId = new Map<number, ContactOption>();

    for (const person of [...props.friends, ...props.following]) {
        byId.set(person.id, person);
    }

    const term = peopleQuery.value.trim().toLocaleLowerCase();

    return [...byId.values()]
        .filter(
            (person) =>
                term === '' ||
                person.name.toLocaleLowerCase().includes(term) ||
                person.username.toLocaleLowerCase().includes(term),
        )
        .sort((a, b) => a.name.localeCompare(b.name));
});

const shownPeople = computed(() =>
    showAllPeople.value || peopleQuery.value
        ? people.value
        : people.value.slice(0, PEOPLE_SHOWN),
);

const groups = computed(() => [
    ...props.friendGroups,
    ...props.followingGroups,
]);
</script>

<template>
    <div class="flex flex-col gap-6">
        <section v-if="props.withPlaceAndDates">
            <div class="flex items-start gap-3.5">
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-ink">
                        {{ t('events.filters.nearMe') }}
                    </h3>
                    <p class="mt-0.5 text-meta text-ink-muted">
                        <template v-if="locating">{{
                            t('events.index.locating')
                        }}</template>
                        <template v-else-if="model.near_lat !== null">{{
                            t('events.filters.near', {
                                place:
                                    model.near_label ??
                                    t('events.filters.myLocation'),
                            })
                        }}</template>
                        <template v-else>{{
                            t('events.filters.nearMeHint')
                        }}</template>
                    </p>
                </div>
                <Switch v-model="nearOn" :label="t('events.filters.nearMe')" />
            </div>
            <p
                v-if="locationError"
                class="mt-2 text-meta font-medium text-danger"
            >
                {{ locationError }}
            </p>
            <div class="mt-3 flex flex-col gap-2">
                <Button
                    size="sm"
                    :icon="LocateFixed"
                    class="self-start"
                    :disabled="locating"
                    @click="useLocation"
                >
                    {{ t('events.filters.useLocation') }}
                </Button>
                <SearchField
                    :model-value="placeQuery"
                    :label="t('events.filters.placeSearch')"
                    :placeholder="t('events.filters.placeSearchPlaceholder')"
                    @update:model-value="onPlaceInput"
                />
                <ul
                    v-if="placeResults.length"
                    class="overflow-hidden rounded-md bg-surface ring-1 ring-line"
                >
                    <li
                        v-for="result in placeResults"
                        :key="result.display_name"
                    >
                        <button
                            type="button"
                            class="flex min-h-11 w-full items-center gap-2.5 px-3.5 py-2 text-left text-label hover:bg-surface-2"
                            @click="pickPlace(result)"
                        >
                            <MapPin
                                :size="16"
                                :stroke-width="2"
                                class="shrink-0 text-ink-subtle"
                                aria-hidden="true"
                            />
                            <span class="line-clamp-2">{{
                                result.display_name
                            }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="props.withPlaceAndDates">
            <h3 class="mb-2.5 font-semibold text-ink">
                {{ t('events.filters.dates') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                <FilterChip
                    v-for="preset in presets"
                    :key="preset.key"
                    small
                    :pressed="activePreset === preset.key"
                    @update:pressed="pickPreset(preset.key)"
                >
                    {{ preset.label }}
                </FilterChip>
            </div>
            <div
                class="mt-3 grid gap-3"
                :class="props.compact ? 'grid-cols-1' : 'grid-cols-2'"
            >
                <!-- From today is the default, which "Any time" already says:
                     the field stays empty for it. -->
                <TextField
                    type="date"
                    :label="t('events.filters.from')"
                    :model-value="
                        model.start_date === today ? '' : model.start_date
                    "
                    @update:model-value="
                        patch({ start_date: String($event ?? '') || today })
                    "
                />
                <TextField
                    type="date"
                    :label="t('events.filters.to')"
                    :model-value="model.end_date"
                    :min="model.start_date || undefined"
                    @update:model-value="
                        patch({ end_date: String($event ?? '') })
                    "
                />
            </div>
        </section>

        <section>
            <h3 class="mb-2.5 font-semibold text-ink">
                {{ t('events.filters.categories') }}
            </h3>
            <div v-if="categoriesExpanded" class="flex flex-col gap-3.5">
                <div v-for="category in props.categories" :key="category.id">
                    <p class="mb-1.5 text-meta font-semibold text-ink-muted">
                        {{ categoryLabel(category) }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <FilterChip
                            small
                            :pressed="hasTag(category.slug)"
                            @update:pressed="toggleTag(category.slug, $event)"
                        >
                            {{
                                t('events.filters.allIn', {
                                    category: categoryLabel(category),
                                })
                            }}
                        </FilterChip>
                        <FilterChip
                            v-for="child in category.children"
                            :key="child.id"
                            small
                            :pressed="hasTag(child.slug)"
                            @update:pressed="toggleTag(child.slug, $event)"
                        >
                            {{ categoryLabel(child) }}
                        </FilterChip>
                    </div>
                </div>
            </div>
            <div v-else class="flex flex-wrap gap-2">
                <FilterChip
                    v-for="category in props.categories"
                    :key="category.id"
                    small
                    :pressed="hasTag(category.slug)"
                    @update:pressed="toggleTag(category.slug, $event)"
                >
                    {{ categoryLabel(category) }}
                </FilterChip>
            </div>
            <button
                v-if="props.compact"
                type="button"
                class="mt-2 min-h-11 text-label font-semibold text-ink underline underline-offset-2"
                @click="showAllCategories = !showAllCategories"
            >
                {{
                    categoriesExpanded
                        ? t('events.filters.fewerCategories')
                        : t('events.filters.moreCategories')
                }}
            </button>
        </section>

        <section>
            <h3 class="mb-2.5 font-semibold text-ink">
                {{ t('events.filters.organizers') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                <FilterChip
                    small
                    :pressed="hasOwner('friends')"
                    @update:pressed="toggleOwner('friends', $event)"
                >
                    {{ t('events.filters.friends') }}
                </FilterChip>
                <FilterChip
                    small
                    :pressed="hasOwner('following')"
                    @update:pressed="toggleOwner('following', $event)"
                >
                    {{ t('events.filters.following') }}
                </FilterChip>
            </div>
            <div v-if="people.length || peopleQuery" class="mt-3">
                <SearchField
                    v-if="people.length > PEOPLE_SHOWN || peopleQuery"
                    v-model="peopleQuery"
                    :label="t('events.filters.searchPeople')"
                    class="mb-2"
                />
                <ul class="flex flex-col">
                    <li v-for="person in shownPeople" :key="person.id">
                        <label
                            class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-1 hover:bg-surface-2/55"
                        >
                            <Avatar
                                :name="person.name"
                                :src="person.avatar_url ?? null"
                                :size="28"
                                decorative
                            />
                            <span class="min-w-0 flex-1 truncate text-label">{{
                                person.name
                            }}</span>
                            <Checkbox
                                :model-value="hasOwner(String(person.id))"
                                :label="person.name"
                                @update:model-value="
                                    toggleOwner(String(person.id), $event)
                                "
                            />
                        </label>
                    </li>
                </ul>
                <button
                    v-if="people.length > PEOPLE_SHOWN && !peopleQuery"
                    type="button"
                    class="mt-1 min-h-11 text-label font-semibold text-ink underline underline-offset-2"
                    @click="showAllPeople = !showAllPeople"
                >
                    {{
                        showAllPeople
                            ? t('events.filters.showFewerPeople')
                            : t('events.filters.showAllPeople', {
                                  n: people.length,
                              })
                    }}
                </button>
            </div>
        </section>

        <section v-if="groups.length">
            <h3 class="mb-2.5 font-semibold text-ink">
                {{ t('events.filters.groups') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                <FilterChip
                    small
                    :pressed="model.group_id === ''"
                    @update:pressed="patch({ group_id: '' })"
                >
                    {{ t('events.filters.anyGroup') }}
                </FilterChip>
                <FilterChip
                    v-for="group in groups"
                    :key="group.id"
                    small
                    :pressed="model.group_id === String(group.id)"
                    @update:pressed="
                        patch({ group_id: $event ? String(group.id) : '' })
                    "
                >
                    {{ group.name }}
                </FilterChip>
            </div>
        </section>
    </div>
</template>
