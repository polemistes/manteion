<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EventCard from '@/components/EventCard.vue';
import { useNominatimSearch } from '@/composables/useNominatimSearch';
import type { NominatimResult } from '@/composables/useNominatimSearch';
import { index as eventsIndex, create as eventsCreate } from '@/routes/events';
import { index as notificationsIndex } from '@/routes/notifications';

const { t } = useI18n();

const page = usePage();
const unreadNotificationsCount = computed(
    () => page.props.unreadNotificationsCount,
);

usePoll(20000, { only: ['events'] });

interface TagOption {
    id: number;
    name: string;
    slug: string;
}

interface ContactOption {
    id: number;
    name: string;
    username: string;
    avatar_url?: string | null;
}

interface GroupOption {
    id: number;
    name: string;
}

interface EventSummary {
    id: number;
    name: string;
    place: string | null;
    city: string | null;
    country: string | null;
    starts_at: string;
    visibility: string;
    owner: ContactOption;
    tags: TagOption[];
    my_status: string | null;
    cover_image_url: string | null;
    attending_count: number;
    interested_count: number;
    distance_km: number | null;
}

const props = defineProps<{
    events: EventSummary[];
    tags: TagOption[];
    friends: ContactOption[];
    following: ContactOption[];
    friendGroups: GroupOption[];
    followingGroups: GroupOption[];
    filters: {
        tags?: string[];
        relation?: string;
        contact_id?: string;
        group_id?: string;
        near_lat?: string;
        near_lng?: string;
    };
}>();

const selectedTags = ref<string[]>(props.filters.tags ?? []);
const relation = ref(props.filters.relation ?? '');
const contactId = ref(props.filters.contact_id ?? '');
const groupId = ref(props.filters.group_id ?? '');
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

function applyFilters(): void {
    router.get(
        eventsIndex().url,
        {
            tags: selectedTags.value,
            relation: relation.value || undefined,
            contact_id: contactId.value || undefined,
            group_id: groupId.value || undefined,
            near_lat: nearLat.value ?? undefined,
            near_lng: nearLng.value ?? undefined,
        },
        { preserveState: true, replace: true },
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
    applyFilters();
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
            applyFilters();
        },
        () => {
            geolocationErrorMessage.value = t('location.geolocationError');
        },
    );
}

function clearNear(): void {
    nearLat.value = null;
    nearLng.value = null;
    applyFilters();
}
</script>

<template>
    <Head :title="t('events.index.title')" />

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold">{{ t('events.index.title') }}</h1>
        <Link
            :href="eventsCreate().url"
            class="border border-black bg-black px-3 py-2 text-sm text-white hover:bg-white hover:text-black"
        >
            {{ t('events.index.createEvent') }}
        </Link>
    </div>

    <Link
        v-if="unreadNotificationsCount"
        :href="notificationsIndex().url"
        class="mb-6 flex items-center justify-between border-[3px] border-black px-4 py-3 text-sm font-bold hover:bg-black hover:text-white"
    >
        {{
            t(
                'events.index.newNotifications',
                { count: unreadNotificationsCount },
                unreadNotificationsCount,
            )
        }}
        <span>&rsaquo;</span>
    </Link>

    <section class="mb-8 border-[3px] border-black p-4">
        <h2 class="mb-3 text-sm font-bold uppercase">
            {{ t('events.index.filters') }}
        </h2>

        <div class="mb-3">
            <p class="mb-1 text-xs font-bold">{{ t('events.index.tags') }}</p>
            <div class="flex flex-wrap gap-3">
                <label
                    v-for="tag in tags"
                    :key="tag.id"
                    class="flex items-center gap-1 text-sm"
                >
                    <input
                        v-model="selectedTags"
                        type="checkbox"
                        :value="tag.slug"
                        class="border border-black"
                        @change="applyFilters"
                    />
                    {{ tag.name }}
                </label>
                <span v-if="!tags.length" class="text-xs text-black/70">{{
                    t('events.index.noTags')
                }}</span>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-6">
            <div>
                <p class="mb-1 text-xs font-bold">
                    {{ t('events.index.ownedBy') }}
                </p>
                <select
                    v-model="relation"
                    class="border border-black bg-white px-2 py-1 text-sm"
                    @change="applyFilters"
                >
                    <option value="">
                        {{ t('events.index.ownedByAnyone') }}
                    </option>
                    <option value="friends">
                        {{ t('events.index.ownedByFriends') }}
                    </option>
                    <option value="following">
                        {{ t('events.index.ownedByFollowing') }}
                    </option>
                    <option value="both">
                        {{ t('events.index.ownedByBoth') }}
                    </option>
                </select>
            </div>

            <div>
                <p class="mb-1 text-xs font-bold">
                    {{ t('events.index.specificPerson') }}
                </p>
                <select
                    v-model="contactId"
                    class="border border-black bg-white px-2 py-1 text-sm"
                    @change="applyFilters"
                >
                    <option value="">{{ t('events.index.anyone') }}</option>
                    <optgroup :label="t('events.index.friendsGroupLabel')">
                        <option
                            v-for="friend in friends"
                            :key="friend.id"
                            :value="String(friend.id)"
                        >
                            {{ friend.name }}
                        </option>
                    </optgroup>
                    <optgroup :label="t('events.index.followingGroupLabel')">
                        <option
                            v-for="contact in following"
                            :key="contact.id"
                            :value="String(contact.id)"
                        >
                            {{ contact.name }}
                        </option>
                    </optgroup>
                </select>
            </div>

            <div>
                <p class="mb-1 text-xs font-bold">
                    {{ t('events.index.specificGroup') }}
                </p>
                <select
                    v-model="groupId"
                    class="border border-black bg-white px-2 py-1 text-sm"
                    @change="applyFilters"
                >
                    <option value="">{{ t('events.index.anyGroup') }}</option>
                    <optgroup :label="t('events.index.friendGroupsLabel')">
                        <option
                            v-for="group in friendGroups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </option>
                    </optgroup>
                    <optgroup :label="t('events.index.followingGroupsLabel')">
                        <option
                            v-for="group in followingGroups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.name }}
                        </option>
                    </optgroup>
                </select>
            </div>

            <div>
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
                            class="border border-black bg-white px-2 py-1 text-sm focus:ring-1 focus:ring-black focus:outline-none"
                            @input="onNearSearchInput"
                        />
                        <button
                            type="button"
                            class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                            @click="useCurrentLocation"
                        >
                            {{ t('location.useCurrentLocation') }}
                        </button>
                    </div>
                    <ul
                        v-if="nearResults.length"
                        class="absolute z-10 mt-1 w-full border border-black bg-white"
                    >
                        <li
                            v-for="result in nearResults"
                            :key="result.display_name"
                        >
                            <button
                                type="button"
                                class="block w-full px-3 py-2 text-left text-xs hover:bg-black hover:text-white"
                                @click="selectNearResult(result)"
                            >
                                {{ result.display_name }}
                            </button>
                        </li>
                    </ul>
                    <p
                        v-else-if="nearSearchError"
                        class="mt-1 text-xs text-black/70"
                    >
                        {{ t('location.searchError') }}
                    </p>
                </div>
                <p
                    v-if="geolocationErrorMessage"
                    class="mt-1 text-xs text-black/70"
                >
                    {{ geolocationErrorMessage }}
                </p>
            </div>
        </div>
    </section>

    <p v-if="!events.length" class="text-sm text-black/70">
        {{ t('events.index.noMatches') }}
    </p>

    <ul class="flex flex-col gap-3">
        <li v-for="event in events" :key="event.id">
            <EventCard :event="event" />
        </li>
    </ul>
</template>
