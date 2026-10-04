<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import EventCard from '@/components/EventCard.vue';
import EventFilterPanel from '@/components/EventFilterPanel.vue';
import EventViewSwitch from '@/components/EventViewSwitch.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import type {
    CategoryOption,
    ContactOption,
    EventFilterQuery,
    EventFilterValues,
    EventSummary,
    GroupOption,
} from '@/events';
import { useBadges } from '@/realtime';
import { index as eventsIndex, create as eventsCreate } from '@/routes/events';
import { index as notificationsIndex } from '@/routes/notifications';

const { t } = useI18n();

const { badges } = useBadges();
const unreadNotificationsCount = computed(() => badges.value.activity);

usePoll(20000, { only: ['events', 'pagination'] });

defineProps<{
    events: EventSummary[];
    pagination: PaginationSummary;
    categories: CategoryOption[];
    friends: ContactOption[];
    following: ContactOption[];
    friendGroups: GroupOption[];
    followingGroups: GroupOption[];
    filters: EventFilterValues;
}>();

function applyFilters(query: EventFilterQuery): void {
    router.get(eventsIndex().url, query, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head :title="t('events.index.title')" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">{{ t('events.index.title') }}</h1>
        <div class="flex flex-wrap items-center gap-3">
            <EventViewSwitch active="list" />
            <Link
                :href="eventsCreate().url"
                class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
            >
                {{ t('events.index.createEvent') }}
            </Link>
        </div>
    </div>

    <Link
        v-if="unreadNotificationsCount"
        :href="notificationsIndex().url"
        class="mb-6 flex items-center justify-between border-frame border-edge px-4 py-3 text-sm font-bold hover:bg-accent hover:text-on-accent"
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

    <EventFilterPanel
        class="mb-8"
        :categories="categories"
        :friends="friends"
        :following="following"
        :friend-groups="friendGroups"
        :following-groups="followingGroups"
        :filters="filters"
        show-dates
        show-near
        @change="applyFilters"
    />

    <p v-if="!events.length" class="text-sm text-ink/70">
        {{ t('events.index.noMatches') }}
    </p>

    <ul class="grid grid-cols-1 gap-3 md:gap-4 xl:grid-cols-2">
        <li v-for="event in events" :key="event.id" class="h-full min-w-0">
            <EventCard :event="event" />
        </li>
    </ul>

    <Pagination :pagination="pagination" :only="['events', 'pagination']" />
</template>
