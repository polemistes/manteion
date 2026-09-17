<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryFilterList from '@/components/CategoryFilterList.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import PersonSummary from '@/components/PersonSummary.vue';
import { useNominatimSearch } from '@/composables/useNominatimSearch';
import type { NominatimResult } from '@/composables/useNominatimSearch';
import { search } from '@/routes/events';

const { t, te, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

interface TagOption {
    id: number;
    name: string;
    slug: string;
}

interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    children: TagOption[];
}

interface EventSummary {
    id: number;
    name: string;
    place: string | null;
    city: string | null;
    country: string | null;
    starts_at: string;
    owner: {
        id: number;
        name: string;
        username: string;
        avatar_url: string | null;
    };
    tags: TagOption[];
    distance_km: number | null;
}

const props = defineProps<{
    q: string;
    categories: CategoryOption[];
    events: EventSummary[];
    pagination: PaginationSummary;
    filters: {
        tags?: string[];
        near_lat?: string;
        near_lng?: string;
    };
}>();

const query = ref(props.q);
const selectedTags = ref<string[]>(props.filters.tags ?? []);
const nearLat = ref<number | null>(
    props.filters.near_lat ? parseFloat(props.filters.near_lat) : null,
);
const nearLng = ref<number | null>(
    props.filters.near_lng ? parseFloat(props.filters.near_lng) : null,
);
const nearQuery = ref('');
const geolocationErrorMessage = ref<string | null>(null);

const {
    results: nearResults,
    error: nearSearchError,
    search: searchNear,
    clearResults: clearNearResults,
} = useNominatimSearch();

function submit(): void {
    router.get(
        search().url,
        {
            q: query.value,
            tags: selectedTags.value,
            near_lat: nearLat.value ?? undefined,
            near_lng: nearLng.value ?? undefined,
        },
        { preserveState: true, replace: true },
    );
}

function onCategoriesChange(value: string[]): void {
    selectedTags.value = value;
    submit();
}

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function locationLabel(event: EventSummary): string | null {
    return (
        event.place ||
        [event.city, event.country].filter(Boolean).join(', ') ||
        null
    );
}

function onNearSearchInput(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    nearQuery.value = value;
    searchNear(value);
}

function selectNearResult(result: NominatimResult): void {
    nearLat.value = parseFloat(result.lat);
    nearLng.value = parseFloat(result.lon);
    nearQuery.value = '';
    clearNearResults();
    submit();
}

function useCurrentLocation(): void {
    geolocationErrorMessage.value = null;

    if (!navigator.geolocation) {
        geolocationErrorMessage.value = t('location.geolocationError');

        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            nearLat.value = position.coords.latitude;
            nearLng.value = position.coords.longitude;
            submit();
        },
        () => {
            geolocationErrorMessage.value = t('location.geolocationError');
        },
    );
}

function clearNear(): void {
    nearLat.value = null;
    nearLng.value = null;
    submit();
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}
</script>

<template>
    <Head :title="t('events.search.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('events.search.title') }}</h1>

    <form class="mb-4 flex gap-2" @submit.prevent="submit">
        <input
            v-model="query"
            type="text"
            :placeholder="t('events.search.placeholder')"
            autofocus
            class="flex-1 border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
        />
        <button
            type="submit"
            class="border border-edge px-4 py-2 text-sm hover:bg-accent hover:text-on-accent"
        >
            {{ t('events.search.search') }}
        </button>
    </form>

    <div v-if="categories.length" class="mb-6">
        <CategoryFilterList
            :categories="categories"
            :model-value="selectedTags"
            @update:model-value="onCategoriesChange"
        />
    </div>

    <div class="mb-6">
        <p class="mb-1 text-xs font-bold">{{ t('location.near') }}</p>
        <div
            v-if="nearLat !== null && nearLng !== null"
            class="flex items-center gap-2 text-sm"
        >
            <span>{{ t('location.sortingByDistance') }}</span>
            <button type="button" class="underline" @click="clearNear">
                {{ t('location.clear') }}
            </button>
        </div>
        <div v-else class="relative">
            <div class="flex gap-2">
                <input
                    type="text"
                    :value="nearQuery"
                    :placeholder="t('location.searchPlaceholder')"
                    class="border border-edge bg-surface-raised px-2 py-1 text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                    @input="onNearSearchInput"
                />
                <button
                    type="button"
                    class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                    @click="useCurrentLocation"
                >
                    {{ t('location.useCurrentLocation') }}
                </button>
            </div>
            <ul
                v-if="nearResults.length"
                class="absolute z-10 mt-1 w-full border border-edge bg-surface-raised"
            >
                <li v-for="result in nearResults" :key="result.display_name">
                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-left text-xs hover:bg-accent hover:text-on-accent"
                        @click="selectNearResult(result)"
                    >
                        {{ result.display_name }}
                    </button>
                </li>
            </ul>
            <p v-else-if="nearSearchError" class="mt-1 text-xs text-ink/70">
                {{ t('location.searchError') }}
            </p>
        </div>
        <p v-if="geolocationErrorMessage" class="mt-1 text-xs text-ink/70">
            {{ geolocationErrorMessage }}
        </p>
    </div>

    <p v-if="q && !events.length" class="text-sm text-ink/70">
        {{ t('events.search.noResults', { query: q }) }}
    </p>

    <ul class="flex flex-col gap-3">
        <li
            v-for="event in events"
            :key="event.id"
            class="border-frame border-edge p-4"
        >
            <Link
                :href="`/events/${event.id}`"
                class="text-base font-bold underline"
            >
                {{ event.name }}
            </Link>
            <p class="text-sm text-ink/70">
                {{ formatDate(event.starts_at) }}
                <template v-if="locationLabel(event)">
                    · {{ locationLabel(event) }}
                </template>
                <template v-if="event.distance_km !== null">
                    ·
                    {{
                        t('location.distanceAway', {
                            distance: event.distance_km,
                        })
                    }}
                </template>
            </p>
            <p class="mt-1 text-sm">
                {{ t('events.search.by') }}
                <PersonSummary v-bind="event.owner" />
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
                <span
                    v-for="tag in event.tags"
                    :key="tag.id"
                    class="border border-edge px-2 py-0.5 text-xs"
                >
                    {{ categoryLabel(tag) }}
                </span>
            </div>
        </li>
    </ul>

    <Pagination :pagination="pagination" :only="['events', 'pagination']" />
</template>
