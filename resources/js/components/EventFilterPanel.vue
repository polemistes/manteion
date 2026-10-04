<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CategoryFilterList from '@/components/CategoryFilterList.vue';
import DateFilterButton from '@/components/DateFilterButton.vue';
import OrganizerFilter from '@/components/OrganizerFilter.vue';
import { useNominatimSearch } from '@/composables/useNominatimSearch';
import type { NominatimResult } from '@/composables/useNominatimSearch';
import type {
    CategoryOption,
    ContactOption,
    EventFilterQuery,
    EventFilterValues,
    GroupOption,
} from '@/events';

const props = withDefaults(
    defineProps<{
        categories: CategoryOption[];
        friends: ContactOption[];
        following: ContactOption[];
        friendGroups: GroupOption[];
        followingGroups: GroupOption[];
        filters: EventFilterValues;
        showDates?: boolean;
        showNear?: boolean;
    }>(),
    { showDates: false, showNear: false },
);

const emit = defineEmits<{
    change: [query: EventFilterQuery];
}>();

const { t } = useI18n();

function todayIso(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const selectedTags = ref<string[]>(props.filters.tags ?? []);
const ownerFilter = ref<string[]>(props.filters.owner_filter ?? []);
const groupId = ref(props.filters.group_id ?? '');
const startDate = ref<string | null>(props.filters.start_date || null);
const endDate = ref<string | null>(props.filters.end_date || null);
const nearLat = ref<number | null>(
    props.filters.near_lat ? parseFloat(props.filters.near_lat) : null,
);
const nearLng = ref<number | null>(
    props.filters.near_lng ? parseFloat(props.filters.near_lng) : null,
);
const nearQuery = ref('');
const geolocationErrorMessage = ref<string | null>(null);

// Start expanded if a filter is already applied (e.g. from a shared link or
// a previous visit), so it isn't hidden behind a closed panel.
const open = ref(
    selectedTags.value.length > 0 ||
        ownerFilter.value.length > 0 ||
        groupId.value !== '' ||
        nearLat.value !== null ||
        endDate.value !== null ||
        (startDate.value !== null && startDate.value !== todayIso()),
);

const {
    results: nearResults,
    error: nearSearchError,
    search: searchNear,
    clearResults: clearNearResults,
} = useNominatimSearch();

function apply(): void {
    emit('change', {
        tags: selectedTags.value,
        owner_filter: ownerFilter.value,
        group_id: groupId.value || undefined,
        near_lat: props.showNear ? (nearLat.value ?? undefined) : undefined,
        near_lng: props.showNear ? (nearLng.value ?? undefined) : undefined,
        start_date: props.showDates ? (startDate.value ?? '') : undefined,
        end_date: props.showDates ? (endDate.value ?? '') : undefined,
    });
}

function onCategoriesChange(value: string[]): void {
    selectedTags.value = value;
    apply();
}

function onOwnerFilterChange(value: string[]): void {
    ownerFilter.value = value;
    apply();
}

function onStartDateChange(value: string | null): void {
    startDate.value = value;
    apply();
}

function onEndDateChange(value: string | null): void {
    endDate.value = value;
    apply();
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
    apply();
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
            apply();
        },
        () => {
            geolocationErrorMessage.value = t('location.geolocationError');
        },
    );
}

function clearNear(): void {
    nearLat.value = null;
    nearLng.value = null;
    apply();
}
</script>

<template>
    <section class="border-frame border-edge p-4">
        <button
            type="button"
            class="text-sm flex w-full items-center justify-between font-bold uppercase"
            :class="{ 'mb-3': open }"
            @click="open = !open"
        >
            {{ t('events.index.filters') }}
            <span>{{ open ? '▲' : '▼' }}</span>
        </button>

        <div v-if="open" class="flex flex-wrap gap-6">
            <CategoryFilterList
                v-if="props.categories.length"
                :categories="props.categories"
                :model-value="selectedTags"
                @update:model-value="onCategoriesChange"
            />

            <OrganizerFilter
                :friends="props.friends"
                :following="props.following"
                :model-value="ownerFilter"
                @update:model-value="onOwnerFilterChange"
            />

            <template v-if="props.showDates">
                <DateFilterButton
                    :label="t('events.index.startDate')"
                    :model-value="startDate"
                    @update:model-value="onStartDateChange"
                />

                <DateFilterButton
                    :label="t('events.index.endDate')"
                    :model-value="endDate"
                    @update:model-value="onEndDateChange"
                />
            </template>

            <div class="max-w-full min-w-0">
                <p class="text-xs mb-1 font-bold">
                    {{ t('events.index.specificGroup') }}
                </p>
                <select
                    v-model="groupId"
                    class="border-edge bg-surface-raised text-sm max-w-full border px-2 py-1"
                    @change="apply"
                >
                    <option value="">{{ t('events.index.anyGroup') }}</option>
                    <optgroup :label="t('events.index.friendGroupsLabel')">
                        <option
                            v-for="group in props.friendGroups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </option>
                    </optgroup>
                    <optgroup :label="t('events.index.followingGroupsLabel')">
                        <option
                            v-for="group in props.followingGroups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </option>
                    </optgroup>
                </select>
            </div>

            <div v-if="props.showNear">
                <p class="text-xs mb-1 font-bold">{{ t('location.near') }}</p>
                <div
                    v-if="nearLat !== null && nearLng !== null"
                    class="text-sm flex items-center gap-2"
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
                            class="border-edge bg-surface-raised text-sm border px-2 py-1 focus:ring-1 focus:ring-accent focus:outline-none"
                            @input="onNearSearchInput"
                        />
                        <button
                            type="button"
                            class="border-edge text-xs border px-2 py-1 hover:bg-accent hover:text-on-accent"
                            @click="useCurrentLocation"
                        >
                            {{ t('location.useCurrentLocation') }}
                        </button>
                    </div>
                    <ul
                        v-if="nearResults.length"
                        class="border-edge bg-surface-raised absolute z-10 mt-1 w-full border"
                    >
                        <li
                            v-for="result in nearResults"
                            :key="result.display_name"
                        >
                            <button
                                type="button"
                                class="text-xs block w-full px-3 py-2 text-left hover:bg-accent hover:text-on-accent"
                                @click="selectNearResult(result)"
                            >
                                {{ result.display_name }}
                            </button>
                        </li>
                    </ul>
                    <p
                        v-else-if="nearSearchError"
                        class="text-xs mt-1 text-ink/70"
                    >
                        {{ t('location.searchError') }}
                    </p>
                </div>
                <p
                    v-if="geolocationErrorMessage"
                    class="text-xs mt-1 text-ink/70"
                >
                    {{ geolocationErrorMessage }}
                </p>
            </div>
        </div>
    </section>
</template>
