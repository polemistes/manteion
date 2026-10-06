<script setup lang="ts">
import { Link, router, usePage, usePoll } from '@inertiajs/vue3';
import {
    BellOff,
    ChevronRight,
    MessageCircle,
    Plus,
    UserCheck,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FindPeople from '@/components/people/FindPeople.vue';
import GroupDialog from '@/components/people/GroupDialog.vue';
import GroupPickerDialog from '@/components/people/GroupPickerDialog.vue';
import MuteDialog from '@/components/people/MuteDialog.vue';
import NewGroupDialog from '@/components/people/NewGroupDialog.vue';
import { messagePerson, PEOPLE_PROPS } from '@/components/people/people';
import type {
    FindResult,
    FollowEntry,
    OrganizerResult,
    FriendEntry,
    FriendRequestEntry,
    GroupEntry,
    GroupType,
    Person,
} from '@/components/people/people';
import PersonMenu from '@/components/people/PersonMenu.vue';
import PersonRow from '@/components/people/PersonRow.vue';
import RequestRow from '@/components/people/RequestRow.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import AvatarStack from '@/components/ui/AvatarStack.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import IconButton from '@/components/ui/IconButton.vue';
import ListRow from '@/components/ui/ListRow.vue';
import SearchField from '@/components/ui/SearchField.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import { confirmAction } from '@/composables/useConfirm';
import { usePressedChipInView } from '@/composables/usePressedChipInView';
import type { CategoryOption, OrganizerSummary, TagOption } from '@/events';
import { useDates } from '@/lib/dates';
import { toast, toastError } from '@/lib/toast';
import { useBadges } from '@/realtime';
import {
    destroy as destroyFollow,
    store as storeFollow,
    update as approveFollowRequest,
} from '@/routes/follows';
import {
    destroy as destroyFriendship,
    update as answerFriendRequest,
} from '@/routes/friendships';
import { show as organizersShow } from '@/routes/organizers';
import { destroy as unfollowOrganizer } from '@/routes/organizers/follow';

/**
 * People: friends, the people you follow and who follow you, the requests
 * waiting both ways, and your groups (which filter the events feed). From
 * xl the groups and finding someone new sit beside the list.
 */
const props = defineProps<{
    friends: FriendEntry[];
    pendingFriendRequests: FriendRequestEntry[];
    sentFriendRequests: FriendRequestEntry[];
    following: FollowEntry[];
    pendingFollowRequestsSent: FollowEntry[];
    followers: FollowEntry[];
    pendingFollowerRequests: FollowEntry[];
    friendGroups: GroupEntry[];
    followingGroups: GroupEntry[];
    categories: CategoryOption[];
    followedOrganizers: OrganizerSummary[];
    findQuery: string;
    findResults: FindResult[];
    findOrganizers: OrganizerResult[];
}>();

const { t, te } = useI18n();
const dates = useDates();
const page = usePage();

// Fresh within a minute, and at once when a request arrives or is answered.
usePoll(60_000, { only: PEOPLE_PROPS });

const { badges } = useBadges();

watch(
    () => badges.value.requests,
    () => router.reload({ only: PEOPLE_PROPS }),
);

type Tab = 'friends' | 'following' | 'followers' | 'requests' | 'groups';

const TABS: Tab[] = ['friends', 'following', 'followers', 'requests', 'groups'];

function initialTab(): Tab {
    const wanted = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');

    return TABS.includes(wanted as Tab) ? (wanted as Tab) : 'friends';
}

const tab = ref<Tab>(initialTab());

function showTab(next: Tab): void {
    tab.value = next;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);

        if (next === 'friends') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', next);
        }

        window.history.replaceState(window.history.state, '', url);
    }
}

const incomingCount = computed(
    () =>
        props.pendingFriendRequests.length +
        props.pendingFollowerRequests.length,
);

const groups = computed(() => [
    ...props.friendGroups.map((group) => ({
        ...group,
        type: 'friend' as GroupType,
    })),
    ...props.followingGroups.map((group) => ({
        ...group,
        type: 'following' as GroupType,
    })),
]);

const tabs = computed(() => [
    {
        key: 'friends' as const,
        label: t('people.tabs.friends'),
        count: props.friends.length,
        badge: 0,
    },
    {
        key: 'following' as const,
        label: t('people.tabs.following'),
        count: props.following.length,
        badge: 0,
    },
    {
        key: 'followers' as const,
        label: t('people.tabs.followers'),
        count: props.followers.length,
        badge: 0,
    },
    {
        key: 'requests' as const,
        label: t('people.tabs.requests'),
        count: 0,
        badge: incomingCount.value,
    },
    {
        key: 'groups' as const,
        label: t('people.tabs.groups'),
        count: groups.value.length,
        badge: 0,
    },
]);

// Narrowing the lists by name.
const query = ref('');

function matches(person: Person): boolean {
    const term = query.value.trim().toLocaleLowerCase();

    return (
        term === '' ||
        person.name.toLocaleLowerCase().includes(term) ||
        person.username.toLocaleLowerCase().includes(term)
    );
}

const SHOWN = 50;
const showAll = ref(false);

watch([tab, query], () => {
    showAll.value = false;
});

function limited<T>(items: T[]): T[] {
    return showAll.value || query.value ? items : items.slice(0, SHOWN);
}

const friends = computed(() => props.friends.filter(matches));
const following = computed(() =>
    props.following.filter((entry) => matches(entry.user)),
);
const followers = computed(() =>
    props.followers.filter((entry) => matches(entry.user)),
);
const followedOrganizers = computed(() => {
    const term = query.value.trim().toLocaleLowerCase();

    return props.followedOrganizers.filter(
        (organizer) =>
            term === '' ||
            organizer.name.toLocaleLowerCase().includes(term) ||
            organizer.handle.toLocaleLowerCase().includes(term),
    );
});

async function endOrganizerFollow(organizer: OrganizerSummary): Promise<void> {
    const confirmed = await confirmAction({
        title: t('people.confirm.unfollowTitle', { name: organizer.name }),
        message: t('people.confirm.unfollowBody'),
        confirmLabel: t('people.menu.unfollow'),
    });

    if (!confirmed) {
        return;
    }

    router.delete(unfollowOrganizer(organizer.id).url, {
        preserveScroll: true,
        only: PEOPLE_PROPS,
        onSuccess: () =>
            toast({
                title: t('people.find.unfollowed', { name: organizer.name }),
            }),
        onError: () => toastError(t('settings.saveFailed')),
    });
}

// The requests waiting for you, newest first.
type Incoming =
    | { kind: 'friend'; key: string; request: FriendRequestEntry }
    | { kind: 'follow'; key: string; request: FollowEntry };

const incoming = computed<Incoming[]>(() =>
    [
        ...props.pendingFriendRequests.map((request): Incoming => ({
            kind: 'friend',
            key: `friend-${request.friendship_id}`,
            request,
        })),
        ...props.pendingFollowerRequests.map((request): Incoming => ({
            kind: 'follow',
            key: `follow-${request.follow_id}`,
            request,
        })),
    ]
        .filter((item) => matches(item.request.user))
        .sort((a, b) => createdOf(b).localeCompare(createdOf(a))),
);

function createdOf(item: Incoming): string {
    return item.kind === 'friend'
        ? (item.request.created_at ?? '')
        : (item.request.since ?? '');
}

// Sub lines.
const tagsById = computed(() => {
    const map = new Map<number, TagOption>();

    for (const category of props.categories) {
        map.set(category.id, category);
        category.children.forEach((child) => map.set(child.id, child));
    }

    return map;
});

function tagLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function mutedText(entry: {
    muted?: boolean;
    tag_preference_ids?: number[];
}): string | null {
    if (entry.muted) {
        return t('people.mutedAll');
    }

    const names = (entry.tag_preference_ids ?? [])
        .map((id) => tagsById.value.get(id))
        .filter((tag): tag is TagOption => tag !== undefined)
        .map(tagLabel);

    return names.length ? t('people.muted', { names: names.join(', ') }) : null;
}

function groupNames(person: Person, type: GroupType): string[] {
    return (type === 'friend' ? props.friendGroups : props.followingGroups)
        .filter((group) =>
            group.members.some((member) => member.id === person.id),
        )
        .map((group) => group.name);
}

function since(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const year = dates.dayKey(value).slice(0, 4);

    return year === dates.todayKey().slice(0, 4)
        ? t('people.friendsSinceMonth', { month: dates.monthName(value) })
        : t('people.friendsSince', { year });
}

function friendSub(friend: FriendEntry): string {
    return [
        ...groupNames(friend, 'friend'),
        friend.mutual_count ? t('people.mutual', friend.mutual_count) : null,
        since(friend.friends_since),
    ]
        .filter(Boolean)
        .join(' · ');
}

function followingSub(entry: FollowEntry): string {
    return groupNames(entry.user, 'following').join(' · ');
}

function followerSub(entry: FollowEntry): string {
    return [
        entry.follows_back === 'following' ? t('people.eachOther') : null,
        entry.mutual_count ? t('people.mutual', entry.mutual_count) : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

// Messages are between friends only.
const friendIds = computed(
    () => new Set(props.friends.map((friend) => friend.id)),
);

// Answering and ending.
const busy = ref<string | null>(null);

function visitOptions(key: string, done?: () => void) {
    busy.value = key;

    return {
        preserveScroll: true,
        preserveState: true,
        only: PEOPLE_PROPS,
        onSuccess: () => done?.(),
        onError: () => toastError(t('settings.saveFailed')),
        onFinish: () => {
            busy.value = null;
        },
    };
}

function answerFriend(request: FriendRequestEntry, accept: boolean): void {
    router.patch(
        answerFriendRequest(request.friendship_id).url,
        { action: accept ? 'accept' : 'decline' },
        visitOptions(`friend-${request.friendship_id}`, () =>
            toast(
                accept
                    ? {
                          title: t('notifications.savedAccepted', {
                              name: request.user.name,
                          }),
                          icon: UserCheck,
                      }
                    : { title: t('notifications.savedDeclined') },
            ),
        ),
    );
}

function answerFollow(request: FollowEntry, approve: boolean): void {
    const key = `follow-${request.follow_id}`;
    const done = () =>
        toast(
            approve
                ? {
                      title: t('notifications.savedApproved', {
                          name: request.user.name,
                      }),
                      icon: UserCheck,
                  }
                : { title: t('notifications.savedDeclined') },
        );

    if (approve) {
        router.patch(
            approveFollowRequest(request.follow_id).url,
            {},
            visitOptions(key, done),
        );
    } else {
        router.delete(
            destroyFollow(request.follow_id).url,
            visitOptions(key, done),
        );
    }
}

async function unfriend(friend: FriendEntry): Promise<void> {
    const confirmed = await confirmAction({
        title: t('people.confirm.unfriendTitle', { name: friend.name }),
        message: t('people.confirm.unfriendBody', { name: friend.name }),
        confirmLabel: t('people.menu.unfriend'),
    });

    if (confirmed) {
        router.delete(
            destroyFriendship(friend.friendship_id).url,
            visitOptions(`friendship-${friend.friendship_id}`),
        );
    }
}

async function endFollow(
    entry: FollowEntry,
    kind: 'unfollow' | 'removeFollower',
): Promise<void> {
    const confirmed = await confirmAction({
        title: t(`people.confirm.${kind}Title`, { name: entry.user.name }),
        message: t(`people.confirm.${kind}Body`, { name: entry.user.name }),
        confirmLabel:
            kind === 'unfollow'
                ? t('people.menu.unfollow')
                : t('people.menu.removeFollower'),
    });

    if (confirmed) {
        router.delete(
            destroyFollow(entry.follow_id).url,
            visitOptions(`follow-${entry.follow_id}`),
        );
    }
}

function followBack(entry: FollowEntry): void {
    router.post(
        storeFollow().url,
        { user_id: entry.user.id },
        visitOptions(`back-${entry.user.id}`, () =>
            toast({
                title: t('notifications.savedFollowing', {
                    name: entry.user.name,
                }),
                icon: UserCheck,
            }),
        ),
    );
}

function cancelFriendRequest(request: FriendRequestEntry): void {
    router.delete(
        destroyFriendship(request.friendship_id).url,
        visitOptions(`sent-${request.friendship_id}`, () =>
            toast({ title: t('people.find.requestCancelled') }),
        ),
    );
}

function cancelFollowRequest(entry: FollowEntry): void {
    router.delete(
        destroyFollow(entry.follow_id).url,
        visitOptions(`sent-follow-${entry.follow_id}`, () =>
            toast({ title: t('people.find.requestCancelled') }),
        ),
    );
}

// Dialogs.
const muteOpen = ref(false);
const muteFor = ref<{
    person: Person;
    tagIds: number[];
    muteAll: boolean;
} | null>(null);

function openMute(
    person: Person,
    entry: { tag_preference_ids?: number[]; muted?: boolean },
): void {
    muteFor.value = {
        person,
        tagIds: entry.tag_preference_ids ?? [],
        muteAll: entry.muted ?? false,
    };
    muteOpen.value = true;
}

const pickerOpen = ref(false);
const pickerFor = ref<{ person: Person; type: GroupType } | null>(null);

function openGroupPicker(person: Person, type: GroupType): void {
    pickerFor.value = { person, type };
    pickerOpen.value = true;
}

const groupOpen = ref(false);
const openGroupId = ref<{ id: number; type: GroupType } | null>(null);
const openGroup = computed(
    () =>
        groups.value.find(
            (group) =>
                group.id === openGroupId.value?.id &&
                group.type === openGroupId.value?.type,
        ) ?? null,
);
const groupCandidates = computed<Person[]>(() =>
    openGroup.value?.type === 'following'
        ? props.following.map((entry) => entry.user)
        : props.friends,
);

function showGroup(group: { id: number; type: GroupType }): void {
    openGroupId.value = { id: group.id, type: group.type };
    groupOpen.value = true;
}

const newGroupOpen = ref(false);
const findOpen = ref(Boolean(props.findQuery));

// On a phone the chips scroll sideways: keep the pressed one in view.
const chipRow = ref<HTMLElement | null>(null);

usePressedChipInView(chipRow, tab);
</script>

<template>
    <PageHeader
        :title="t('people.title')"
        :lede="t('people.lede')"
        width="wide"
        root
    >
        <template #phone-actions>
            <IconButton
                :icon="UserPlus"
                :label="t('people.find.title')"
                @click="findOpen = true"
            />
        </template>
        <template #actions>
            <Button :icon="UserPlus" class="xl:hidden" @click="findOpen = true">
                {{ t('people.find.title') }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer width="wide" class="pt-3 pb-16 sm:pt-0">
        <div
            class="xl:grid xl:grid-cols-[minmax(0,1fr)_var(--spacing-aside)] xl:items-start xl:gap-12"
        >
            <div class="min-w-0">
                <SearchField
                    v-model="query"
                    :label="t('people.search')"
                    :placeholder="t('people.searchPlaceholder')"
                    class="sm:max-w-[26rem]"
                />

                <div
                    class="-mx-4 mt-3 scrollbar-none flex gap-2 overflow-x-auto fade-end px-4 pb-1 sm:mx-0 sm:flex-wrap sm:[mask-image:none] sm:px-0"
                    ref="chipRow"
                    role="group"
                    :aria-label="t('people.title')"
                >
                    <FilterChip
                        v-for="item in tabs"
                        :key="item.key"
                        :pressed="tab === item.key"
                        :count="item.count || undefined"
                        :badge="item.badge"
                        @update:pressed="showTab(item.key)"
                    >
                        {{ item.label }}
                    </FilterChip>
                </div>

                <!-- Waiting for you: on Friends and Requests. -->
                <template
                    v-if="
                        (tab === 'friends' || tab === 'requests') &&
                        incoming.length
                    "
                >
                    <SectionHeader
                        :title="t('people.waiting')"
                        :count="incoming.length"
                    />
                    <div
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <template v-for="item in incoming" :key="item.key">
                            <RequestRow
                                v-if="item.kind === 'friend'"
                                :person="item.request.user"
                                kind="friend"
                                :created-at="item.request.created_at"
                                :mutual-count="item.request.mutual_count"
                                :busy="busy === item.key"
                                @accept="answerFriend(item.request, true)"
                                @decline="answerFriend(item.request, false)"
                            />
                            <RequestRow
                                v-else
                                :person="item.request.user"
                                kind="follow"
                                :created-at="item.request.since"
                                :mutual-count="item.request.mutual_count"
                                :busy="busy === item.key"
                                @accept="answerFollow(item.request, true)"
                                @decline="answerFollow(item.request, false)"
                            />
                        </template>
                    </div>
                </template>

                <!-- Friends -->
                <template v-if="tab === 'friends'">
                    <SectionHeader
                        :title="t('people.tabs.friends')"
                        :meta="String(props.friends.length)"
                    />
                    <div
                        v-if="friends.length"
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <PersonRow
                            v-for="friend in limited(friends)"
                            :key="friend.id"
                            :person="friend"
                        >
                            <template #sub>
                                <span
                                    v-if="mutedText(friend)"
                                    class="inline-flex items-center gap-1"
                                >
                                    <BellOff
                                        :size="14"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ mutedText(friend) }}
                                    <template v-if="friendSub(friend)">
                                        ·
                                    </template>
                                </span>
                                {{ friendSub(friend) }}
                            </template>
                            <template #actions>
                                <IconButton
                                    class="hidden sm:inline-flex"
                                    :icon="MessageCircle"
                                    :label="
                                        t('people.menu.messageName', {
                                            name: friend.name,
                                        })
                                    "
                                    size="sm"
                                    @click="messagePerson(friend)"
                                />
                                <PersonMenu
                                    :person="friend"
                                    kind="friend"
                                    can-message
                                    :muted-hint="mutedText(friend) ?? undefined"
                                    @add-to-group="
                                        openGroupPicker(friend, 'friend')
                                    "
                                    @mute="openMute(friend, friend)"
                                    @remove="unfriend(friend)"
                                />
                            </template>
                        </PersonRow>
                    </div>
                    <EmptyState
                        v-else-if="query"
                        :title="t('people.noMatches')"
                    />
                    <EmptyState
                        v-else
                        :icon="Users"
                        :title="t('people.empty.friendsTitle')"
                        :body="t('people.empty.friendsBody')"
                    >
                        <Button :icon="UserPlus" @click="findOpen = true">
                            {{ t('people.find.title') }}
                        </Button>
                    </EmptyState>
                    <p
                        v-if="!showAll && !query && friends.length > SHOWN"
                        class="mt-5 text-center"
                    >
                        <button
                            type="button"
                            class="min-h-11 text-label font-semibold text-ink underline underline-offset-2"
                            @click="showAll = true"
                        >
                            {{ t('people.showAll', { n: friends.length }) }}
                        </button>
                    </p>
                </template>

                <!-- Following -->
                <template v-if="tab === 'following'">
                    <SectionHeader
                        :title="t('people.tabs.following')"
                        :meta="String(props.following.length)"
                    />
                    <div
                        v-if="following.length"
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <PersonRow
                            v-for="entry in limited(following)"
                            :key="entry.follow_id"
                            :person="entry.user"
                        >
                            <template #sub>
                                <span
                                    v-if="mutedText(entry)"
                                    class="inline-flex items-center gap-1"
                                >
                                    <BellOff
                                        :size="14"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                    {{ mutedText(entry) }}
                                    <template v-if="followingSub(entry)">
                                        ·
                                    </template>
                                </span>
                                {{ followingSub(entry) }}
                            </template>
                            <template #actions>
                                <PersonMenu
                                    :person="entry.user"
                                    kind="following"
                                    :can-message="friendIds.has(entry.user.id)"
                                    :muted-hint="mutedText(entry) ?? undefined"
                                    @add-to-group="
                                        openGroupPicker(entry.user, 'following')
                                    "
                                    @mute="openMute(entry.user, entry)"
                                    @remove="endFollow(entry, 'unfollow')"
                                />
                            </template>
                        </PersonRow>
                    </div>
                    <EmptyState
                        v-else-if="query"
                        :title="t('people.noMatches')"
                    />
                    <EmptyState
                        v-else
                        :icon="UserCheck"
                        :title="t('people.empty.followingTitle')"
                        :body="t('people.empty.followingBody')"
                    />

                    <!-- The organizers (venues, groups) you follow. -->
                    <template v-if="followedOrganizers.length">
                        <SectionHeader
                            :title="t('people.organizers')"
                            :meta="String(props.followedOrganizers.length)"
                        />
                        <div
                            class="overflow-hidden rounded-lg border border-line bg-surface"
                        >
                            <ListRow
                                v-for="organizer in followedOrganizers"
                                :key="organizer.id"
                                :inset="74"
                                :sub="'@' + organizer.handle"
                                interactive
                            >
                                <template #leading>
                                    <Avatar
                                        :name="organizer.name"
                                        :src="organizer.avatar_url"
                                        :size="44"
                                        decorative
                                    />
                                </template>
                                <template #title>
                                    <Link
                                        :href="organizersShow(organizer.id).url"
                                        class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-[-2px] focus-visible:after:outline-ink"
                                        >{{ organizer.name }}</Link
                                    >
                                </template>
                                <template #trailing>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="relative z-10"
                                        @click="endOrganizerFollow(organizer)"
                                    >
                                        {{ t('people.find.following') }}
                                    </Button>
                                </template>
                            </ListRow>
                        </div>
                    </template>
                </template>

                <!-- Followers -->
                <template v-if="tab === 'followers'">
                    <SectionHeader
                        :title="t('people.tabs.followers')"
                        :meta="String(props.followers.length)"
                    />
                    <div
                        v-if="followers.length"
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <PersonRow
                            v-for="entry in limited(followers)"
                            :key="entry.follow_id"
                            :person="entry.user"
                            :sub="followerSub(entry)"
                        >
                            <template #actions>
                                <Button
                                    v-if="entry.follows_back === null"
                                    size="sm"
                                    :icon="UserPlus"
                                    class="hidden sm:inline-flex"
                                    :disabled="busy === `back-${entry.user.id}`"
                                    @click="followBack(entry)"
                                >
                                    {{ t('people.menu.followBack') }}
                                </Button>
                                <PersonMenu
                                    :person="entry.user"
                                    kind="follower"
                                    :can-message="friendIds.has(entry.user.id)"
                                    :can-follow-back="
                                        entry.follows_back === null
                                    "
                                    @follow-back="followBack(entry)"
                                    @remove="endFollow(entry, 'removeFollower')"
                                />
                            </template>
                        </PersonRow>
                    </div>
                    <EmptyState
                        v-else-if="query"
                        :title="t('people.noMatches')"
                    />
                    <EmptyState
                        v-else
                        :icon="Users"
                        :title="t('people.empty.followersTitle')"
                        :body="t('people.empty.followersBody')"
                    />
                </template>

                <!-- Requests: the ones you sent (the incoming ones are above). -->
                <template v-if="tab === 'requests'">
                    <template
                        v-if="
                            props.sentFriendRequests.length ||
                            props.pendingFollowRequestsSent.length
                        "
                    >
                        <SectionHeader :title="t('people.sent')" />
                        <div
                            class="overflow-hidden rounded-lg border border-line bg-surface"
                        >
                            <PersonRow
                                v-for="request in props.sentFriendRequests"
                                :key="`sent-${request.friendship_id}`"
                                :person="request.user"
                                :sub="t('people.sentFriendRequest')"
                            >
                                <template #actions>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        :disabled="
                                            busy ===
                                            `sent-${request.friendship_id}`
                                        "
                                        @click="cancelFriendRequest(request)"
                                    >
                                        {{ t('people.cancel') }}
                                    </Button>
                                </template>
                            </PersonRow>
                            <PersonRow
                                v-for="entry in props.pendingFollowRequestsSent"
                                :key="`sent-follow-${entry.follow_id}`"
                                :person="entry.user"
                                :sub="t('people.sentFollowRequest')"
                            >
                                <template #actions>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        :disabled="
                                            busy ===
                                            `sent-follow-${entry.follow_id}`
                                        "
                                        @click="cancelFollowRequest(entry)"
                                    >
                                        {{ t('people.cancel') }}
                                    </Button>
                                </template>
                            </PersonRow>
                        </div>
                    </template>
                    <EmptyState
                        v-if="
                            !incoming.length &&
                            !props.sentFriendRequests.length &&
                            !props.pendingFollowRequestsSent.length
                        "
                        :icon="UserCheck"
                        :title="t('people.empty.requestsTitle')"
                        :body="t('people.empty.requestsBody')"
                    />
                </template>

                <!-- Groups (beside the list from xl as well). -->
                <template v-if="tab === 'groups'">
                    <div
                        class="mt-7 mb-3 flex items-center justify-between gap-3"
                    >
                        <p class="text-meta text-ink-muted">
                            {{ t('people.groups.hint') }}
                        </p>
                        <Button
                            size="sm"
                            :icon="Plus"
                            @click="newGroupOpen = true"
                        >
                            {{ t('people.groups.new') }}
                        </Button>
                    </div>
                    <div
                        v-if="groups.length"
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <button
                            v-for="group in groups"
                            :key="`${group.type}-${group.id}`"
                            type="button"
                            class="group/row relative flex min-h-16 w-full items-center gap-3.5 px-4 text-left hover:bg-surface-2/55"
                            @click="showGroup(group)"
                        >
                            <span
                                class="pointer-events-none absolute top-0 right-0 left-4 h-px bg-line group-first/row:hidden"
                                aria-hidden="true"
                            />
                            <AvatarStack
                                :people="group.members"
                                :size="28"
                                :max="3"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate font-medium text-ink"
                                    >{{ group.name }}</span
                                >
                                <span class="block text-meta text-ink-muted">{{
                                    t(
                                        group.type === 'friend'
                                            ? 'people.groups.ofFriendsCount'
                                            : 'people.groups.ofFollowingCount',
                                        group.members.length,
                                    )
                                }}</span>
                            </span>
                            <ChevronRight
                                :size="18"
                                :stroke-width="1.75"
                                class="shrink-0 text-ink-subtle"
                                aria-hidden="true"
                            />
                        </button>
                    </div>
                    <EmptyState
                        v-else
                        :icon="Users"
                        :title="t('people.empty.groupsTitle')"
                        :body="t('people.empty.groupsBody')"
                    />
                </template>
            </div>

            <aside class="hidden xl:sticky xl:top-6 xl:block">
                <div class="flex flex-col gap-4">
                    <section
                        class="overflow-hidden rounded-lg border border-line bg-surface"
                    >
                        <div class="flex items-center gap-2 px-4 pt-4 pb-2">
                            <h2
                                class="min-w-0 flex-1 text-kicker text-ink-muted uppercase"
                            >
                                {{ t('people.tabs.groups') }}
                            </h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                :icon="Plus"
                                @click="newGroupOpen = true"
                            >
                                {{ t('people.groups.new') }}
                            </Button>
                        </div>
                        <button
                            v-for="group in groups"
                            :key="`${group.type}-${group.id}`"
                            type="button"
                            class="group/row relative flex min-h-14 w-full items-center gap-3 px-4 text-left hover:bg-surface-2/55"
                            @click="showGroup(group)"
                        >
                            <span
                                class="pointer-events-none absolute top-0 right-0 left-4 h-px bg-line"
                                aria-hidden="true"
                            />
                            <AvatarStack
                                :people="group.members"
                                :size="24"
                                :max="3"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-label font-medium text-ink"
                                    >{{ group.name }}</span
                                >
                                <span class="block text-meta text-ink-muted">{{
                                    t(
                                        'people.groups.count',
                                        group.members.length,
                                    )
                                }}</span>
                            </span>
                            <ChevronRight
                                :size="18"
                                :stroke-width="1.75"
                                class="shrink-0 text-ink-subtle"
                                aria-hidden="true"
                            />
                        </button>
                        <p
                            class="border-t border-line px-4 py-3 text-meta text-ink-muted"
                        >
                            {{ t('people.groups.hint') }}
                        </p>
                    </section>

                    <section
                        class="rounded-lg border border-line bg-surface p-4"
                    >
                        <h2 class="font-display text-title-3">
                            {{ t('people.find.title') }}
                        </h2>
                        <p class="mt-1 mb-3 text-meta text-ink-muted">
                            {{ t('people.find.hint') }}
                        </p>
                        <FindPeople
                            :query="props.findQuery"
                            :results="props.findResults"
                            :organizers="props.findOrganizers"
                        />
                    </section>
                </div>
            </aside>
        </div>
    </PageContainer>

    <AppDialog
        v-model:open="findOpen"
        :title="t('people.find.title')"
        :description="t('people.find.hint')"
        size="lg"
    >
        <FindPeople
            :query="props.findQuery"
            :results="props.findResults"
            :organizers="props.findOrganizers"
        />
    </AppDialog>

    <MuteDialog
        v-model:open="muteOpen"
        :person="muteFor?.person ?? null"
        :categories="props.categories"
        :tag-ids="muteFor?.tagIds ?? []"
        :mute-all="muteFor?.muteAll ?? false"
    />

    <GroupPickerDialog
        v-model:open="pickerOpen"
        :person="pickerFor?.person ?? null"
        :type="pickerFor?.type ?? 'friend'"
        :groups="
            pickerFor?.type === 'following'
                ? props.followingGroups
                : props.friendGroups
        "
    />

    <GroupDialog
        v-model:open="groupOpen"
        :group="openGroup"
        :candidates="groupCandidates"
    />

    <NewGroupDialog v-model:open="newGroupOpen" />
</template>
