<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Earth, Navigation, SearchX } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EventCard from '@/components/events/EventCard.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import SearchField from '@/components/ui/SearchField.vue';
import type { CategoryOption, EventSummary, TagOption } from '@/events';
import { toastError } from '@/lib/toast';
import { index as eventsIndex, search } from '@/routes/events';

/**
 * Events anyone may find: those their hosts made searchable. Search by
 * name or place, narrow by category, or sort by distance.
 */
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

const { t, te } = useI18n();

const query = ref(props.q);
const tags = computed(() => props.filters.tags ?? []);
const near = computed(() =>
    props.filters.near_lat && props.filters.near_lng
        ? { lat: props.filters.near_lat, lng: props.filters.near_lng }
        : null,
);

function visit(
    changes: {
        q?: string;
        tags?: string[];
        near?: { lat: string; lng: string } | null;
    } = {},
): void {
    const nextNear = changes.near === undefined ? near.value : changes.near;

    router.get(
        search().url,
        {
            q: (changes.q ?? query.value).trim() || undefined,
            tags: changes.tags ?? tags.value,
            near_lat: nextNear?.lat,
            near_lng: nextNear?.lng,
        },
        { preserveState: true, replace: true },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit({ q: value }), 400);
});

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function toggleTag(slug: string, on: boolean): void {
    visit({
        tags: on
            ? [...new Set([...tags.value, slug])]
            : tags.value.filter((tag) => tag !== slug),
    });
}

const locating = ref(false);

function toggleNear(on: boolean): void {
    if (!on) {
        visit({ near: null });

        return;
    }

    if (!navigator.geolocation) {
        toastError(t('events.index.locationFailed'));

        return;
    }

    locating.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            locating.value = false;
            visit({
                near: {
                    lat: position.coords.latitude.toFixed(5),
                    lng: position.coords.longitude.toFixed(5),
                },
            });
        },
        () => {
            locating.value = false;
            toastError(t('events.index.locationFailed'));
        },
        { timeout: 10_000, maximumAge: 300_000 },
    );
}
</script>

<template>
    <PageHeader
        :title="t('events.search.title')"
        :lede="t('events.search.lede')"
        :back="eventsIndex().url"
        :back-label="t('events.show.back')"
    />

    <PageContainer class="pt-3 sm:pt-0">
        <SearchField
            v-model="query"
            :label="t('events.search.label')"
            :placeholder="t('events.search.placeholder')"
        />

        <div
            class="-mx-4 mt-3 scrollbar-none flex gap-2 overflow-x-auto fade-end px-4 pb-1 sm:mx-0 sm:flex-wrap sm:[mask-image:none] sm:px-0"
            role="group"
            :aria-label="t('events.search.narrow')"
        >
            <FilterChip
                :icon="Navigation"
                :pressed="near !== null"
                :disabled="locating"
                @update:pressed="toggleNear"
            >
                {{
                    locating
                        ? t('events.index.locating')
                        : t('events.index.nearMe')
                }}
            </FilterChip>
            <FilterChip
                v-for="category in props.categories"
                :key="category.id"
                :pressed="tags.includes(category.slug)"
                @update:pressed="toggleTag(category.slug, $event)"
            >
                {{ categoryLabel(category) }}
            </FilterChip>
        </div>

        <div class="@container mt-6">
            <ul v-if="props.events.length" class="flex flex-col gap-3">
                <li v-for="event in props.events" :key="event.id">
                    <EventCard :event="event" />
                </li>
            </ul>
            <EmptyState
                v-else-if="props.q || tags.length"
                :icon="SearchX"
                :title="t('events.search.noResultsTitle')"
                :body="
                    props.q
                        ? t('events.search.noResults', { query: props.q })
                        : t('events.search.noResultsFiltered')
                "
            />
            <EmptyState
                v-else
                :icon="Earth"
                :title="t('events.search.emptyTitle')"
                :body="t('events.search.emptyBody')"
            />
        </div>

        <Pagination
            :pagination="props.pagination"
            :only="['events', 'pagination']"
        />
    </PageContainer>
</template>
