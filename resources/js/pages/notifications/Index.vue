<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BellRing, CheckCheck, Inbox, ListFilter } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ActivityRow from '@/components/activity/ActivityRow.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import { useNavigation } from '@/components/shell/useNavigation';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import IconButton from '@/components/ui/IconButton.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import { addDays, isoWeekday, useDates } from '@/lib/dates';
import { onActivity } from '@/realtime';
import { requestJson } from '@/realtime/http';
import { read as readNotification } from '@/routes/notifications';
import { notifications as notificationSettings } from '@/routes/settings';
import type { ActivityItem, NotificationCategory } from '@/types';

/**
 * Activity: invitations, requests and replies, newest first, each answered
 * right where it is. Opening the page marks everything read; what was new
 * stays under "New" until you leave (or mark it read). New items slide in
 * live.
 */
const props = defineProps<{
    notifications: ActivityItem[];
    push: { categories: NotificationCategory[]; has_device: boolean };
}>();

const LIMIT = 100;

const { t, locale } = useI18n();
const dates = useDates();
const { previousTab } = useNavigation();

type Kind = 'all' | 'invitations' | 'requests' | 'comments' | 'follows';

const kind = ref<Kind>('all');

function kindOf(item: ActivityItem): Exclude<Kind, 'all'> {
    switch (item.type) {
        case 'InvitedToEvent':
        case 'FollowedUserPublishedEvent':
            return 'invitations';
        case 'FriendRequestReceived':
            return 'requests';
        case 'NewFollower':
            return item.pending ? 'requests' : 'follows';
        case 'NewEventComment':
        case 'NewLeafletResponse':
            return 'comments';
        default:
            return 'follows';
    }
}

const waitingInvitations = computed(
    () =>
        props.notifications.filter((item) => {
            const event = item.context?.event;

            return (
                item.type === 'InvitedToEvent' &&
                event &&
                !event.is_host &&
                !event.is_over &&
                (event.my_status === null || event.my_status === 'no_response')
            );
        }).length,
);

const openRequests = computed(
    () =>
        props.notifications.filter(
            (item) =>
                kindOf(item) === 'requests' &&
                item.context?.request?.status === 'pending',
        ).length,
);

const kinds = computed(() => [
    { key: 'all' as const, label: t('notifications.all'), badge: 0 },
    {
        key: 'invitations' as const,
        label: t('notifications.invitations'),
        badge: waitingInvitations.value,
    },
    {
        key: 'requests' as const,
        label: t('notifications.requests'),
        badge: openRequests.value,
    },
    { key: 'comments' as const, label: t('notifications.comments'), badge: 0 },
    { key: 'follows' as const, label: t('notifications.follows'), badge: 0 },
]);

// What shows as new: unread when the page loaded or since, until cleared.
const cleared = ref(new Set<string>());

function isNew(item: ActivityItem): boolean {
    return item.unread && !cleared.value.has(item.id);
}

const newItems = computed(() => props.notifications.filter(isNew));

async function markRead(id: string): Promise<void> {
    try {
        await requestJson(readNotification(id));
    } catch {
        // The badge catches up on the next load.
    }
}

function markAllRead(): void {
    const next = new Set(cleared.value);

    for (const item of newItems.value) {
        next.add(item.id);
        void markRead(item.id);
    }

    cleared.value = next;
}

// Something new arrived: fetch it with what's needed to answer it. The
// user is looking at it, so it counts as read (but shows as new).
onActivity((item) => {
    router.reload({
        only: ['notifications'],
        onSuccess: () => {
            if (document.visibilityState === 'visible') {
                void markRead(item.id);
            }
        },
    });
});

// Sections: new, earlier this week (Monday to Sunday), earlier.
const thisMonday = computed(() => {
    const today = dates.todayKey();

    return addDays(today, 1 - isoWeekday(today));
});

const sections = computed(() => {
    const shown = props.notifications.filter(
        (item) => kind.value === 'all' || kindOf(item) === kind.value,
    );
    const fresh = shown.filter(isNew);
    const rest = shown.filter((item) => !isNew(item));
    const thisWeek = rest.filter(
        (item) => dates.dayKey(item.created_at) >= thisMonday.value,
    );
    const earlier = rest.filter(
        (item) => dates.dayKey(item.created_at) < thisMonday.value,
    );

    return [
        {
            key: 'new',
            title: t('notifications.new'),
            count: fresh.length,
            items: fresh,
        },
        {
            key: 'week',
            title: t('notifications.thisWeek'),
            count: 0,
            items: thisWeek,
        },
        {
            key: 'earlier',
            title: t('notifications.earlier'),
            count: 0,
            items: earlier,
        },
    ].filter((section) => section.items.length);
});

// The aside: what reaches the phone.
const pushSummary = computed(() => {
    if (!props.push.has_device) {
        return t('notifications.push.noDevice');
    }

    if (!props.push.categories.length) {
        return t('notifications.push.onlyHere');
    }

    const names = props.push.categories.map((category) =>
        t(`notifications.categories.${category}`),
    );
    const list = new Intl.ListFormat(locale.value === 'nb' ? 'nb' : 'en', {
        style: 'long',
        type: 'conjunction',
    }).format(names);

    return t('notifications.push.on', { list });
});
</script>

<template>
    <PageHeader
        :title="t('notifications.title')"
        :lede="t('notifications.lede')"
        :back="previousTab.href"
        :back-label="previousTab.label"
        width="wide"
    >
        <template #phone-actions>
            <IconButton
                v-if="newItems.length"
                :icon="CheckCheck"
                :label="t('notifications.markAllRead')"
                @click="markAllRead"
            />
        </template>
        <template #actions>
            <Button
                v-if="newItems.length"
                :icon="CheckCheck"
                @click="markAllRead"
            >
                {{ t('notifications.markAllRead') }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer width="wide" class="pt-3 sm:pt-0">
        <div
            class="xl:grid xl:grid-cols-[minmax(0,1fr)_300px] xl:items-start xl:gap-12"
        >
            <div class="min-w-0">
                <div
                    class="-mx-4 scrollbar-none flex gap-2 overflow-x-auto fade-end px-4 pb-1 sm:mx-0 sm:flex-wrap sm:[mask-image:none] sm:px-0"
                    role="group"
                    :aria-label="t('notifications.kinds')"
                >
                    <FilterChip
                        v-for="option in kinds"
                        :key="option.key"
                        :pressed="kind === option.key"
                        :badge="option.badge"
                        @update:pressed="kind = option.key"
                    >
                        {{ option.label }}
                    </FilterChip>
                </div>

                <template v-for="section in sections" :key="section.key">
                    <SectionHeader
                        :title="section.title"
                        :count="section.count || undefined"
                    />
                    <ul
                        class="-mx-4 sm:mx-0 sm:overflow-hidden sm:rounded-lg sm:border sm:border-line sm:bg-surface"
                    >
                        <li
                            v-for="(item, index) in section.items"
                            :key="item.id"
                            class="relative"
                        >
                            <span
                                v-if="index > 0"
                                class="absolute top-0 right-0 left-[72px] h-px bg-line sm:left-[78px]"
                                aria-hidden="true"
                            />
                            <ActivityRow :item="item" :unread="isNew(item)" />
                        </li>
                    </ul>
                </template>

                <EmptyState
                    v-if="!sections.length && kind !== 'all'"
                    :icon="ListFilter"
                    :title="t('notifications.emptyFilteredTitle')"
                    :body="t('notifications.emptyFilteredBody')"
                >
                    <Button @click="kind = 'all'">
                        {{ t('notifications.all') }}
                    </Button>
                </EmptyState>
                <EmptyState
                    v-else-if="!sections.length"
                    :icon="Inbox"
                    :title="t('notifications.emptyTitle')"
                    :body="t('notifications.emptyBody')"
                />
                <p
                    v-else
                    class="mt-10 text-center font-display text-input text-ink-muted italic"
                >
                    {{
                        props.notifications.length >= LIMIT
                            ? t('notifications.endNoteLimited', { n: LIMIT })
                            : t('notifications.endNote')
                    }}
                </p>
            </div>

            <aside class="hidden xl:sticky xl:top-6 xl:mt-7 xl:block">
                <section class="rounded-lg border border-line bg-surface p-5">
                    <span
                        class="grid size-10 place-items-center rounded-full bg-surface-2 text-ink-muted"
                        aria-hidden="true"
                    >
                        <BellRing :size="20" :stroke-width="1.75" />
                    </span>
                    <h2 class="mt-3 font-display text-title-3">
                        {{ t('notifications.push.title') }}
                    </h2>
                    <p class="mt-2 text-meta text-ink-muted">
                        {{ pushSummary }}
                    </p>
                    <Button
                        :as="Link"
                        :href="notificationSettings().url"
                        size="sm"
                        class="mt-4"
                    >
                        {{ t('notifications.push.settings') }}
                    </Button>
                </section>
            </aside>
        </div>
    </PageContainer>
</template>
