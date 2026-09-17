<script setup lang="ts">
import { Form, Head, Link, router, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ContactSearchResult from '@/components/ContactSearchResult.vue';
import GroupMembershipDropdown from '@/components/GroupMembershipDropdown.vue';
import PersonSummary from '@/components/PersonSummary.vue';
import TagPreferencePicker from '@/components/TagPreferencePicker.vue';
import * as contactGroups from '@/routes/contact-groups';
import * as contactGroupMembers from '@/routes/contact-groups/members';
import { index as contactsIndex } from '@/routes/contacts';
import * as follows from '@/routes/follows';
import * as friendships from '@/routes/friendships';
import { store as startConversation } from '@/routes/messages';

const { t } = useI18n();

interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    children: { id: number; name: string; slug: string }[];
}

interface ContactSummary {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
    tag_preference_ids?: number[];
    muted?: boolean;
}

interface FriendshipEntry {
    friendship_id: number;
    user: ContactSummary;
}

interface FollowEntry {
    follow_id: number;
    user: ContactSummary;
    tag_preference_ids?: number[];
    muted?: boolean;
}

interface GroupEntry {
    id: number;
    name: string;
    members: ContactSummary[];
}

interface FindResult {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
    friend_status: 'none' | 'request_sent' | 'request_received' | 'friends';
    friendship_id: number | null;
    follow_status: 'none' | 'pending' | 'following';
    follow_id: number | null;
}

const props = defineProps<{
    friends: ContactSummary[];
    pendingFriendRequests: FriendshipEntry[];
    sentFriendRequests: FriendshipEntry[];
    following: FollowEntry[];
    pendingFollowRequestsSent: FollowEntry[];
    followers: FollowEntry[];
    pendingFollowerRequests: FollowEntry[];
    friendGroups: GroupEntry[];
    followingGroups: GroupEntry[];
    categories: CategoryOption[];
    findQuery: string;
    findResults: FindResult[];
}>();

usePoll(20000, {
    only: [
        'friends',
        'pendingFriendRequests',
        'sentFriendRequests',
        'following',
        'pendingFollowRequestsSent',
        'followers',
        'pendingFollowerRequests',
        'friendGroups',
        'followingGroups',
    ],
});

function groupsAvailableFor(
    personId: number,
    groups: GroupEntry[],
): GroupEntry[] {
    return groups.filter(
        (group) => !group.members.some((member) => member.id === personId),
    );
}

function matchesQuery(contact: ContactSummary, query: string): boolean {
    const term = query.trim().toLowerCase();

    if (term === '') {
        return true;
    }

    return (
        contact.name.toLowerCase().includes(term) ||
        contact.username.toLowerCase().includes(term)
    );
}

const contactsQuery = ref('');
const groupsOpen = ref(false);
const findOpen = ref(false);
const findQueryInput = ref(props.findQuery);

function submitFind(): void {
    router.get(
        contactsIndex().url,
        { find: findQueryInput.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['findQuery', 'findResults'],
        },
    );
}

const filteredFriends = computed(() =>
    props.friends.filter((friend) => matchesQuery(friend, contactsQuery.value)),
);
const filteredFollowing = computed(() =>
    props.following.filter((entry) =>
        matchesQuery(entry.user, contactsQuery.value),
    ),
);
const filteredFollowers = computed(() =>
    props.followers.filter((entry) =>
        matchesQuery(entry.user, contactsQuery.value),
    ),
);
</script>

<template>
    <Head :title="t('contacts.index.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('contacts.index.title') }}</h1>

    <div class="contacts-layout">
        <aside class="contacts-layout__find lg:sticky lg:top-6">
            <button
                type="button"
                class="mb-3 flex w-full items-center justify-between text-sm font-bold uppercase lg:hidden"
                @click="findOpen = !findOpen"
            >
                {{ t('contacts.index.findHeading') }}
                <span>{{ findOpen ? '▲' : '▼' }}</span>
            </button>

            <div :class="findOpen ? '' : 'hidden lg:block'">
                <h2 class="mb-3 hidden text-sm font-bold uppercase lg:block">
                    {{ t('contacts.index.findHeading') }}
                </h2>

                <form class="mb-4 flex gap-2" @submit.prevent="submitFind">
                    <input
                        v-model="findQueryInput"
                        type="text"
                        :placeholder="t('contacts.index.findPlaceholder')"
                        class="flex-1 border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                    />
                    <button
                        type="submit"
                        class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                    >
                        {{ t('contacts.index.find') }}
                    </button>
                </form>

                <div class="lg:hidden">
                    <p
                        v-if="findQuery && !findResults.length"
                        class="text-sm text-ink/70"
                    >
                        {{
                            t('contacts.index.findNoResults', {
                                query: findQuery,
                            })
                        }}
                    </p>

                    <ul v-else class="flex flex-col gap-2">
                        <ContactSearchResult
                            v-for="result in findResults"
                            :key="result.id"
                            :result="result"
                        />
                    </ul>
                </div>
            </div>
        </aside>

        <aside class="contacts-layout__groups lg:sticky lg:top-6">
            <button
                type="button"
                class="mb-3 flex w-full items-center justify-between text-sm font-bold uppercase lg:hidden"
                @click="groupsOpen = !groupsOpen"
            >
                {{ t('contacts.index.groupsHeading') }}
                <span>{{ groupsOpen ? '▲' : '▼' }}</span>
            </button>

            <div :class="groupsOpen ? '' : 'hidden lg:block'">
                <section class="mb-10">
                    <h2 class="mb-3 text-sm font-bold uppercase">
                        {{ t('contacts.index.friendGroups') }}
                    </h2>
                    <p class="mb-3 text-xs text-ink/70">
                        {{ t('contacts.index.friendGroupsDescription') }}
                    </p>

                    <Form
                        v-bind="contactGroups.store.form()"
                        #default="{ errors, processing }"
                        class="mb-4 flex items-end gap-2"
                    >
                        <input type="hidden" name="type" value="friend" />
                        <div class="flex-1">
                            <label
                                for="friend_group_name"
                                class="mb-1 block text-xs font-bold"
                                >{{ t('contacts.index.newGroupName') }}</label
                            >
                            <input
                                id="friend_group_name"
                                type="text"
                                name="name"
                                required
                                class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                            />
                            <p
                                v-if="errors.name"
                                class="mt-1 text-xs font-bold"
                            >
                                ! {{ errors.name }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="processing"
                            class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                        >
                            {{ t('contacts.index.create') }}
                        </button>
                    </Form>

                    <div
                        v-for="group in friendGroups"
                        :key="group.id"
                        class="mb-4 border-frame border-edge p-3"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-bold">{{
                                group.name
                            }}</span>
                            <Link
                                :href="contactGroups.destroy(group.id).url"
                                method="delete"
                                as="button"
                                preserve-scroll
                                class="text-xs underline"
                            >
                                {{ t('contacts.index.deleteGroup') }}
                            </Link>
                        </div>

                        <ul class="mb-2 flex flex-col gap-1">
                            <li
                                v-for="member in group.members"
                                :key="member.id"
                                class="flex items-center justify-between text-sm"
                            >
                                <PersonSummary v-bind="member" />
                                <Link
                                    :href="
                                        contactGroupMembers.destroy({
                                            contact_group: group.id,
                                            member: member.id,
                                        }).url
                                    "
                                    method="delete"
                                    as="button"
                                    preserve-scroll
                                    class="text-xs underline"
                                >
                                    {{ t('contacts.index.remove') }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </section>

                <section>
                    <h2 class="mb-3 text-sm font-bold uppercase">
                        {{ t('contacts.index.followingGroups') }}
                    </h2>
                    <p class="mb-3 text-xs text-ink/70">
                        {{ t('contacts.index.followingGroupsDescription') }}
                    </p>

                    <Form
                        v-bind="contactGroups.store.form()"
                        #default="{ errors, processing }"
                        class="mb-4 flex items-end gap-2"
                    >
                        <input type="hidden" name="type" value="following" />
                        <div class="flex-1">
                            <label
                                for="following_group_name"
                                class="mb-1 block text-xs font-bold"
                                >{{ t('contacts.index.newGroupName') }}</label
                            >
                            <input
                                id="following_group_name"
                                type="text"
                                name="name"
                                required
                                class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                            />
                            <p
                                v-if="errors.name"
                                class="mt-1 text-xs font-bold"
                            >
                                ! {{ errors.name }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="processing"
                            class="border border-edge px-3 py-2 text-sm hover:bg-accent hover:text-on-accent"
                        >
                            {{ t('contacts.index.create') }}
                        </button>
                    </Form>

                    <div
                        v-for="group in followingGroups"
                        :key="group.id"
                        class="mb-4 border-frame border-edge p-3"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-bold">{{
                                group.name
                            }}</span>
                            <Link
                                :href="contactGroups.destroy(group.id).url"
                                method="delete"
                                as="button"
                                preserve-scroll
                                class="text-xs underline"
                            >
                                {{ t('contacts.index.deleteGroup') }}
                            </Link>
                        </div>

                        <ul class="mb-2 flex flex-col gap-1">
                            <li
                                v-for="member in group.members"
                                :key="member.id"
                                class="flex items-center justify-between text-sm"
                            >
                                <PersonSummary v-bind="member" />
                                <Link
                                    :href="
                                        contactGroupMembers.destroy({
                                            contact_group: group.id,
                                            member: member.id,
                                        }).url
                                    "
                                    method="delete"
                                    as="button"
                                    preserve-scroll
                                    class="text-xs underline"
                                >
                                    {{ t('contacts.index.remove') }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>
        </aside>

        <div class="contacts-layout__search">
            <h2 class="mb-3 text-sm font-bold uppercase">
                {{ t('contacts.index.filterHeading') }}
            </h2>
            <input
                v-model="contactsQuery"
                type="text"
                :placeholder="t('contacts.index.filterPlaceholder')"
                class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
            />
        </div>

        <div class="contacts-layout__main">
            <section v-if="findQuery" class="mb-10 hidden lg:block">
                <h2 class="mb-3 text-sm font-bold uppercase">
                    {{ t('contacts.index.searchResultsHeading') }}
                </h2>
                <p v-if="!findResults.length" class="text-sm text-ink/70">
                    {{
                        t('contacts.index.findNoResults', { query: findQuery })
                    }}
                </p>
                <ul v-else class="flex flex-col gap-2">
                    <ContactSearchResult
                        v-for="result in findResults"
                        :key="result.id"
                        :result="result"
                    />
                </ul>
            </section>

            <section v-if="pendingFriendRequests.length" class="mb-10">
                <h2 class="mb-3 text-sm font-bold uppercase">
                    {{ t('contacts.index.friendRequests') }}
                </h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="request in pendingFriendRequests"
                        :key="request.friendship_id"
                        class="flex items-center justify-between border-frame border-edge px-3 py-2 text-sm"
                    >
                        <PersonSummary v-bind="request.user" />
                        <span class="flex gap-2">
                            <Form
                                v-bind="
                                    friendships.update.form(
                                        request.friendship_id,
                                    )
                                "
                                #default="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="action"
                                    value="accept"
                                />
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                                >
                                    {{ t('contacts.index.accept') }}
                                </button>
                            </Form>
                            <Form
                                v-bind="
                                    friendships.update.form(
                                        request.friendship_id,
                                    )
                                "
                                #default="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="action"
                                    value="decline"
                                />
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                                >
                                    {{ t('contacts.index.decline') }}
                                </button>
                            </Form>
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="pendingFollowerRequests.length" class="mb-10">
                <h2 class="mb-3 text-sm font-bold uppercase">
                    {{ t('contacts.index.followRequests') }}
                </h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="request in pendingFollowerRequests"
                        :key="request.follow_id"
                        class="flex items-center justify-between border-frame border-edge px-3 py-2 text-sm"
                    >
                        <PersonSummary v-bind="request.user" />
                        <span class="flex gap-2">
                            <Link
                                :href="follows.update(request.follow_id).url"
                                method="patch"
                                as="button"
                                preserve-scroll
                                class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                            >
                                {{ t('contacts.index.approve') }}
                            </Link>
                            <Link
                                :href="follows.destroy(request.follow_id).url"
                                method="delete"
                                as="button"
                                preserve-scroll
                                class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                            >
                                {{ t('contacts.index.reject') }}
                            </Link>
                        </span>
                    </li>
                </ul>
            </section>

            <section class="mb-10">
                <h2 class="mb-3 text-sm font-bold uppercase">
                    {{ t('contacts.index.friends') }}
                </h2>
                <p v-if="!friends.length" class="text-sm text-ink/70">
                    {{ t('contacts.index.noFriends') }}
                </p>
                <template v-else>
                    <p
                        v-if="!filteredFriends.length"
                        class="text-sm text-ink/70"
                    >
                        {{ t('contacts.index.noMatches') }}
                    </p>
                    <ul v-else class="flex flex-col gap-2">
                        <li
                            v-for="friend in filteredFriends"
                            :key="friend.id"
                            class="border-frame border-edge px-3 py-2 text-sm"
                        >
                            <PersonSummary
                                :id="friend.id"
                                :name="friend.name"
                                :username="friend.username"
                                :avatar_url="friend.avatar_url"
                            />
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <TagPreferencePicker
                                    :contact-id="friend.id"
                                    :categories="categories"
                                    :excluded-tag-ids="
                                        friend.tag_preference_ids ?? []
                                    "
                                    :mute-all="friend.muted ?? false"
                                />
                                <GroupMembershipDropdown
                                    :person-id="friend.id"
                                    :groups="
                                        groupsAvailableFor(
                                            friend.id,
                                            friendGroups,
                                        )
                                    "
                                />
                                <Link
                                    :href="startConversation().url"
                                    method="post"
                                    :data="{ user_ids: [friend.id] }"
                                    as="button"
                                    class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                                >
                                    {{ t('contacts.index.message') }}
                                </Link>
                            </div>
                        </li>
                    </ul>
                </template>

                <div v-if="sentFriendRequests.length" class="mt-4">
                    <h3 class="mb-2 text-xs font-bold text-ink/70 uppercase">
                        {{ t('contacts.index.requestsSent') }}
                    </h3>
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="request in sentFriendRequests"
                            :key="request.friendship_id"
                            class="flex items-center justify-between border-frame border-edge px-3 py-2 text-sm"
                        >
                            <PersonSummary v-bind="request.user" />
                            <Link
                                :href="
                                    friendships.destroy(request.friendship_id)
                                        .url
                                "
                                method="delete"
                                as="button"
                                preserve-scroll
                                class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                            >
                                {{ t('contacts.index.cancel') }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="mb-10">
                <h2 class="mb-3 text-sm font-bold uppercase">
                    {{ t('contacts.index.following') }}
                </h2>
                <p v-if="!following.length" class="text-sm text-ink/70">
                    {{ t('contacts.index.notFollowingAnyone') }}
                </p>
                <template v-else>
                    <p
                        v-if="!filteredFollowing.length"
                        class="text-sm text-ink/70"
                    >
                        {{ t('contacts.index.noMatches') }}
                    </p>
                    <ul v-else class="flex flex-col gap-2">
                        <li
                            v-for="entry in filteredFollowing"
                            :key="entry.follow_id"
                            class="border-frame border-edge px-3 py-2 text-sm"
                        >
                            <PersonSummary v-bind="entry.user" />
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <TagPreferencePicker
                                    :contact-id="entry.user.id"
                                    :categories="categories"
                                    :excluded-tag-ids="
                                        entry.tag_preference_ids ?? []
                                    "
                                    :mute-all="entry.muted ?? false"
                                />
                                <GroupMembershipDropdown
                                    :person-id="entry.user.id"
                                    :groups="
                                        groupsAvailableFor(
                                            entry.user.id,
                                            followingGroups,
                                        )
                                    "
                                />
                                <Link
                                    :href="follows.destroy(entry.follow_id).url"
                                    method="delete"
                                    as="button"
                                    preserve-scroll
                                    class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                                >
                                    {{ t('contacts.index.unfollow') }}
                                </Link>
                            </div>
                        </li>
                    </ul>
                </template>

                <div v-if="pendingFollowRequestsSent.length" class="mt-4">
                    <h3 class="mb-2 text-xs font-bold text-ink/70 uppercase">
                        {{ t('contacts.index.requestsAwaitingApproval') }}
                    </h3>
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="entry in pendingFollowRequestsSent"
                            :key="entry.follow_id"
                            class="flex items-center justify-between border-frame border-edge px-3 py-2 text-sm"
                        >
                            <PersonSummary v-bind="entry.user" />
                            <Link
                                :href="follows.destroy(entry.follow_id).url"
                                method="delete"
                                as="button"
                                preserve-scroll
                                class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                            >
                                {{ t('contacts.index.cancel') }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="mb-10">
                <h2 class="mb-3 text-sm font-bold uppercase">
                    {{ t('contacts.index.followers') }}
                </h2>
                <p v-if="!followers.length" class="text-sm text-ink/70">
                    {{ t('contacts.index.noFollowers') }}
                </p>
                <template v-else>
                    <p
                        v-if="!filteredFollowers.length"
                        class="text-sm text-ink/70"
                    >
                        {{ t('contacts.index.noMatches') }}
                    </p>
                    <ul v-else class="flex flex-col gap-2">
                        <li
                            v-for="entry in filteredFollowers"
                            :key="entry.follow_id"
                            class="flex items-center justify-between border-frame border-edge px-3 py-2 text-sm"
                        >
                            <PersonSummary v-bind="entry.user" />
                            <Link
                                :href="follows.destroy(entry.follow_id).url"
                                method="delete"
                                as="button"
                                preserve-scroll
                                class="border border-edge px-2 py-1 hover:bg-accent hover:text-on-accent"
                            >
                                {{ t('contacts.index.remove') }}
                            </Link>
                        </li>
                    </ul>
                </template>
            </section>
        </div>
    </div>
</template>

<style scoped>
/*
 * On mobile, "find new contacts" and the groups panel are collapsed
 * toggles stacked above the filter field and the main list — find comes
 * first since it's the more common reason to open the page, and its
 * results appear inline within its own toggle. On desktop, three equal
 * columns: "search" and "find" sit side by side directly above "main"
 * (together spanning the same width as "main", so each is half of it),
 * while "groups" spans the full height of its own column with nothing
 * above it. Find's results appear in the main column under their own
 * "Search results" heading rather than in the find column itself.
 * That's a genuine reorder, not just a resize, so this mirrors
 * EventCard.vue's use of grid-template-areas instead of pure utility
 * classes.
 */
.contacts-layout {
    display: grid;
    gap: 2rem;
    grid-template-areas:
        'find'
        'groups'
        'search'
        'main';
}

.contacts-layout__find {
    grid-area: find;
}

.contacts-layout__groups {
    grid-area: groups;
}

.contacts-layout__search {
    grid-area: search;
}

.contacts-layout__main {
    grid-area: main;
}

@media (min-width: 1024px) {
    .contacts-layout {
        grid-template-columns: 1fr 1fr 1fr;
        align-items: start;
        grid-template-areas:
            'search find groups'
            'main main groups';
    }
}
</style>
