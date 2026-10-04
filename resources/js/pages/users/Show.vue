<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    Eye,
    EyeOff,
    MessageCircle,
    Pencil,
    UserCheck,
    UserMinus,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { messagePerson } from '@/components/people/people';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import DateBadge from '@/components/ui/DateBadge.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import Tag from '@/components/ui/Tag.vue';
import { confirmAction } from '@/composables/useConfirm';
import type { AttendanceStatus, EventCover } from '@/events';
import { useDates } from '@/lib/dates';
import { toast, toastError } from '@/lib/toast';
import { index as contactsIndex } from '@/routes/contacts';
import { show as eventsShow } from '@/routes/events';
import {
    destroy as destroyFollow,
    store as storeFollow,
} from '@/routes/follows';
import {
    destroy as destroyFriendship,
    store as storeFriendship,
    update as answerFriendship,
} from '@/routes/friendships';
import { account } from '@/routes/settings';
import { show as usersShow } from '@/routes/users';

/**
 * Someone's profile: who they are, how you know each other (and changing
 * that), what they have coming up that you may see, and their friends when
 * they show them.
 */
const props = defineProps<{
    profile: {
        id: number;
        name: string;
        username: string;
        bio: string | null;
        avatar_url: string | null;
    };
    is_self: boolean;
    friend_status: 'none' | 'request_sent' | 'request_received' | 'friends';
    friendship_id: number | null;
    follow_status: 'none' | 'pending' | 'following';
    follow_id: number | null;
    friends: {
        id: number;
        name: string;
        username: string;
        avatar_url: string | null;
    }[];
    mutual_count: number | null;
    events: {
        id: number;
        name: string;
        starts_at: string;
        ends_at: string | null;
        place: string | null;
        city: string | null;
        cover: EventCover | null;
        my_status: AttendanceStatus | null;
    }[];
}>();

const { t } = useI18n();
const dates = useDates();

const busy = ref(false);

function visit(done: string) {
    busy.value = true;

    return {
        preserveScroll: true,
        onSuccess: () => toast({ title: done, icon: Check }),
        onError: () => toastError(t('settings.saveFailed')),
        onFinish: () => {
            busy.value = false;
        },
    };
}

const name = computed(() => props.profile.name);

function addFriend(): void {
    router.post(
        storeFriendship().url,
        { user_id: props.profile.id },
        visit(t('people.find.requestSent', { name: name.value })),
    );
}

function cancelFriendRequest(): void {
    if (props.friendship_id) {
        router.delete(
            destroyFriendship(props.friendship_id).url,
            visit(t('people.find.requestCancelled')),
        );
    }
}

function answer(accept: boolean): void {
    if (props.friendship_id) {
        router.patch(
            answerFriendship(props.friendship_id).url,
            { action: accept ? 'accept' : 'decline' },
            visit(
                accept
                    ? t('notifications.savedAccepted', { name: name.value })
                    : t('notifications.savedDeclined'),
            ),
        );
    }
}

async function unfriend(): Promise<void> {
    const confirmed = await confirmAction({
        title: t('people.confirm.unfriendTitle', { name: name.value }),
        message: t('people.confirm.unfriendBody', { name: name.value }),
        confirmLabel: t('people.menu.unfriend'),
    });

    if (confirmed && props.friendship_id) {
        router.delete(
            destroyFriendship(props.friendship_id).url,
            visit(t('people.profile.unfriended', { name: name.value })),
        );
    }
}

function follow(): void {
    router.post(
        storeFollow().url,
        { user_id: props.profile.id },
        visit(t('people.find.followed', { name: name.value })),
    );
}

async function stopFollowing(): Promise<void> {
    if (!props.follow_id) {
        return;
    }

    if (props.follow_status === 'following') {
        const confirmed = await confirmAction({
            title: t('people.confirm.unfollowTitle', { name: name.value }),
            message: t('people.confirm.unfollowBody', { name: name.value }),
            confirmLabel: t('people.menu.unfollow'),
        });

        if (!confirmed) {
            return;
        }
    }

    router.delete(
        destroyFollow(props.follow_id).url,
        visit(
            props.follow_status === 'pending'
                ? t('people.find.requestCancelled')
                : t('people.find.unfollowed', { name: name.value }),
        ),
    );
}

const SHOWN_FRIENDS = 12;
const allFriends = ref(false);
const shownFriends = computed(() =>
    allFriends.value ? props.friends : props.friends.slice(0, SHOWN_FRIENDS),
);

function eventStatus(status: AttendanceStatus | null): string | null {
    if (status === 'attending') {
        return t('events.rsvp.going');
    }

    if (status === 'interested') {
        return t('events.rsvp.maybe');
    }

    return status === 'no_response' ? t('events.calendar.invited') : null;
}
</script>

<template>
    <PageHeader
        :title="props.profile.name"
        :back="contactsIndex().url"
        :back-label="t('people.title')"
        header-hidden
    />

    <PageContainer class="pt-6 pb-16 sm:pt-7">
        <Link
            :href="contactsIndex().url"
            class="mb-5 -ml-3 hidden h-9 items-center gap-1.5 rounded-full px-3 text-label font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink sm:inline-flex"
        >
            <ArrowLeft :size="16" :stroke-width="2" aria-hidden="true" />
            {{ t('people.title') }}
        </Link>
        <section
            class="flex flex-col items-center text-center sm:flex-row sm:items-start sm:gap-7 sm:text-left"
        >
            <Avatar
                :name="props.profile.name"
                :src="props.profile.avatar_url"
                :size="104"
            />
            <div class="mt-4 min-w-0 flex-1 sm:mt-1">
                <h1
                    class="font-display text-title-1 text-pretty lg:text-display"
                >
                    {{ props.profile.name }}
                </h1>
                <p class="mt-1 text-meta text-ink-muted">
                    {{ '@' + props.profile.username
                    }}<template v-if="props.mutual_count">
                        · {{ t('people.mutual', props.mutual_count) }}
                    </template>
                </p>
                <p
                    v-if="props.profile.bio"
                    class="mt-3 font-display text-reading text-ink"
                >
                    {{ props.profile.bio }}
                </p>

                <div
                    class="mt-5 flex flex-wrap justify-center gap-2 sm:justify-start"
                >
                    <Button
                        v-if="props.is_self"
                        :as="Link"
                        :href="account().url"
                        :icon="Pencil"
                    >
                        {{ t('settings.overview.editAccount') }}
                    </Button>

                    <template v-else>
                        <Button
                            v-if="props.friend_status === 'none'"
                            variant="primary"
                            :icon="UserPlus"
                            :disabled="busy"
                            @click="addFriend"
                        >
                            {{ t('people.find.addFriend') }}
                        </Button>
                        <template
                            v-else-if="
                                props.friend_status === 'request_received'
                            "
                        >
                            <Button
                                variant="primary"
                                :disabled="busy"
                                @click="answer(true)"
                            >
                                {{ t('people.profile.acceptRequest') }}
                            </Button>
                            <Button :disabled="busy" @click="answer(false)">
                                {{ t('people.requests.decline') }}
                            </Button>
                        </template>
                        <Button
                            v-else-if="props.friend_status === 'request_sent'"
                            :disabled="busy"
                            @click="cancelFriendRequest"
                        >
                            {{ t('people.profile.cancelRequest') }}
                        </Button>
                        <Menu v-else align="start">
                            <template #trigger>
                                <Button :icon="UserCheck">
                                    {{ t('people.find.friends') }}
                                    <ChevronDown
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </template>
                            <!-- Following a friend lives in this menu, so a
                                 friend's actions fit one row on a phone. -->
                            <MenuItem
                                v-if="props.follow_status === 'none'"
                                :icon="Eye"
                                :disabled="busy"
                                @select="follow"
                            >
                                {{ t('people.find.follow') }}
                            </MenuItem>
                            <MenuItem
                                v-else
                                :icon="EyeOff"
                                :disabled="busy"
                                @select="stopFollowing"
                            >
                                {{
                                    props.follow_status === 'pending'
                                        ? t(
                                              'people.profile.cancelFollowRequest',
                                          )
                                        : t('people.menu.unfollow')
                                }}
                            </MenuItem>
                            <MenuSeparator />
                            <MenuItem
                                :icon="UserMinus"
                                danger
                                @select="unfriend"
                            >
                                {{ t('people.menu.unfriend') }}
                            </MenuItem>
                        </Menu>

                        <Button
                            v-if="props.friend_status === 'friends'"
                            :icon="MessageCircle"
                            @click="messagePerson(props.profile)"
                        >
                            {{ t('people.profile.message') }}
                        </Button>

                        <template v-if="props.friend_status !== 'friends'">
                            <Button
                                v-if="props.follow_status === 'none'"
                                :disabled="busy"
                                @click="follow"
                            >
                                {{ t('people.find.follow') }}
                            </Button>
                            <Button
                                v-else
                                variant="ghost"
                                :disabled="busy"
                                @click="stopFollowing"
                            >
                                {{
                                    props.follow_status === 'pending'
                                        ? t(
                                              'people.profile.cancelFollowRequest',
                                          )
                                        : t('people.find.following')
                                }}
                            </Button>
                        </template>
                    </template>
                </div>
            </div>
        </section>

        <template v-if="props.events.length">
            <SectionHeader :title="t('people.profile.comingUp')" />
            <ul
                class="overflow-hidden rounded-lg border border-line bg-surface"
            >
                <li
                    v-for="event in props.events"
                    :key="event.id"
                    class="group/row relative"
                >
                    <span
                        class="pointer-events-none absolute top-0 right-0 left-[76px] h-px bg-line group-first/row:hidden"
                        aria-hidden="true"
                    />
                    <Link
                        :href="eventsShow(event.id).url"
                        class="flex items-center gap-4 px-4 py-3 transition-colors duration-120 hover:bg-surface-2/55"
                    >
                        <DateBadge :date="event.starts_at" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="line-clamp-2 font-semibold text-ink">{{
                                event.name
                            }}</span>
                            <span
                                class="block truncate text-meta text-ink-muted"
                                >{{
                                    [
                                        dates.span(
                                            event.starts_at,
                                            event.ends_at,
                                        ),
                                        event.place ?? event.city,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}</span
                            >
                        </span>
                        <Tag
                            v-if="eventStatus(event.my_status)"
                            :tone="
                                event.my_status === 'attending'
                                    ? 'going'
                                    : 'host'
                            "
                        >
                            {{ eventStatus(event.my_status) }}
                        </Tag>
                    </Link>
                </li>
            </ul>
        </template>

        <template v-if="props.friends.length">
            <SectionHeader
                :title="t('people.tabs.friends')"
                :meta="String(props.friends.length)"
            />
            <ul
                class="grid grid-cols-3 gap-x-3 gap-y-5 sm:grid-cols-4 md:grid-cols-6"
            >
                <li v-for="friend in shownFriends" :key="friend.id">
                    <Link
                        :href="usersShow(friend.id).url"
                        class="flex flex-col items-center gap-2 rounded-md p-1 text-center hover:bg-surface-2/55"
                    >
                        <Avatar
                            :name="friend.name"
                            :src="friend.avatar_url"
                            :size="56"
                            decorative
                        />
                        <span
                            class="line-clamp-2 text-meta font-medium text-ink"
                            >{{ friend.name }}</span
                        >
                    </Link>
                </li>
            </ul>
            <p
                v-if="!allFriends && props.friends.length > SHOWN_FRIENDS"
                class="mt-4 text-center"
            >
                <button
                    type="button"
                    class="min-h-11 text-label font-semibold text-ink underline underline-offset-2"
                    @click="allFriends = true"
                >
                    {{ t('people.showAll', { n: props.friends.length }) }}
                </button>
            </p>
        </template>

        <p
            v-if="
                !props.is_self && !props.events.length && !props.friends.length
            "
            class="mt-10 text-center font-display text-input text-ink-muted italic"
        >
            {{ t('people.profile.nothingToShow', { name: name }) }}
        </p>
    </PageContainer>
</template>
