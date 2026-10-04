<script setup lang="ts">
import type { Page } from '@inertiajs/core';
import { Link, router } from '@inertiajs/vue3';
import {
    Calendar,
    CalendarPlus,
    Check,
    MessageSquare,
    Newspaper,
    UserCheck,
    UserPlus,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import RsvpControl from '@/components/events/RsvpControl.vue';
import { useRsvp } from '@/components/events/useRsvp';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import DateBadge from '@/components/ui/DateBadge.vue';
import { useDates } from '@/lib/dates';
import { toast, toastError } from '@/lib/toast';
import {
    destroy as declineFollowRequest,
    store as follow,
    update as approveFollowRequest,
} from '@/routes/follows';
import { update as answerFriendRequest } from '@/routes/friendships';
import type { ActivityItem } from '@/types';

/**
 * One activity item: who did what, when, and a way to answer right here,
 * whether that is an invitation (Going / Maybe / Can't go), a friend or
 * follow request, or a new follower to follow back. Comments and leaflet
 * responses quote what was written. The sentence opens what it is about.
 */
const props = defineProps<{
    item: ActivityItem;
    /** Shown as new: the dot, and the time in terracotta. */
    unread: boolean;
}>();

const { t } = useI18n();
const dates = useDates();

const actorName = computed(
    () => props.item.actor?.name ?? t('notifications.someone'),
);
const context = computed(() => props.item.context);
const event = computed(() => context.value?.event ?? null);
const isFollowRequest = computed(
    () => props.item.type === 'NewFollower' && props.item.pending === true,
);
/** A friend or follow request the user answers here (not a plain follow). */
const request = computed(() =>
    props.item.type === 'FriendRequestReceived' || isFollowRequest.value
        ? (context.value?.request ?? null)
        : null,
);

const sentenceKey = computed(
    () =>
        `notifications.sentence.${isFollowRequest.value ? 'NewFollowerRequest' : props.item.type}`,
);

const icon = computed<Component>(() => {
    switch (props.item.type) {
        case 'InvitedToEvent':
            return Calendar;
        case 'FollowedUserPublishedEvent':
            return CalendarPlus;
        case 'NewEventComment':
            return MessageSquare;
        case 'FriendRequestReceived':
            return UserPlus;
        case 'NewFollower':
            return isFollowRequest.value ? UserPlus : UserCheck;
        case 'FriendRequestAccepted':
            return UserCheck;
        case 'FollowRequestApproved':
            return Check;
        case 'NewLeafletResponse':
            return Newspaper;
        default:
            return Check;
    }
});

// Invitations: answer in place while the event is still to come.
const canAnswer = computed(
    () =>
        props.item.type === 'InvitedToEvent' &&
        event.value !== null &&
        !event.value.is_host &&
        !event.value.is_over,
);

const rsvp = useRsvp({
    eventId: () => props.item.subject_id,
    host: () => event.value?.host_name ?? actorName.value,
    initial: () => ({
        status: event.value?.my_status ?? null,
        visibility: event.value?.my_attendance_visibility ?? null,
    }),
    reload: ['notifications'],
});

const status = computed({
    get: () => rsvp.status.value,
    set: (next) => rsvp.save({ status: next }),
});

const unanswered = computed(
    () => status.value === null || status.value === 'no_response',
);

/** Still waiting for the user: the terracotta type badge. */
const needsYou = computed(() => {
    if (props.item.type === 'InvitedToEvent') {
        return context.value ? canAnswer.value && unanswered.value : true;
    }

    if (props.item.type === 'FriendRequestReceived' || isFollowRequest.value) {
        return context.value ? request.value?.status === 'pending' : true;
    }

    return false;
});

const eventWhen = computed(() =>
    event.value &&
    (props.item.type === 'InvitedToEvent' ||
        props.item.type === 'FollowedUserPublishedEvent')
        ? dates.span(event.value.starts_at, event.value.ends_at)
        : null,
);

// Requests and follows.
const busy = ref(false);

function visitOptions(onSuccess: (page: Page) => void) {
    busy.value = true;

    return {
        preserveScroll: true,
        preserveState: true,
        only: ['notifications'],
        onSuccess,
        onError: () => toastError(t('notifications.failed')),
        onFinish: () => {
            busy.value = false;
        },
    };
}

function answerFriendship(action: 'accept' | 'decline'): void {
    if (!request.value) {
        return;
    }

    router.patch(
        answerFriendRequest(request.value.id).url,
        { action },
        visitOptions(() =>
            toast(
                action === 'accept'
                    ? {
                          title: t('notifications.savedAccepted', {
                              name: actorName.value,
                          }),
                          icon: UserCheck,
                      }
                    : { title: t('notifications.savedDeclined') },
            ),
        ),
    );
}

function approveFollow(): void {
    if (!request.value) {
        return;
    }

    router.patch(
        approveFollowRequest(request.value.id).url,
        {},
        visitOptions(() =>
            toast({
                title: t('notifications.savedApproved', {
                    name: actorName.value,
                }),
                icon: UserCheck,
            }),
        ),
    );
}

function declineFollow(): void {
    if (!request.value) {
        return;
    }

    router.delete(
        declineFollowRequest(request.value.id).url,
        visitOptions(() => toast({ title: t('notifications.savedDeclined') })),
    );
}

function followBack(): void {
    if (!props.item.actor) {
        return;
    }

    router.post(
        follow().url,
        { user_id: props.item.actor.id },
        visitOptions((page) => {
            const items = page.props.notifications as ActivityItem[];
            const following = items.find((item) => item.id === props.item.id)
                ?.context?.following_actor;

            toast({
                title: t(
                    following === 'pending'
                        ? 'notifications.savedRequested'
                        : 'notifications.savedFollowing',
                    { name: actorName.value },
                ),
                icon: UserCheck,
            });
        }),
    );
}

const showFollowBack = computed(
    () =>
        props.item.type === 'NewFollower' &&
        props.item.actor !== null &&
        context.value !== undefined &&
        context.value.following_actor === null &&
        (!isFollowRequest.value || request.value?.status === 'accepted'),
);
const followRequested = computed(
    () => context.value?.following_actor === 'pending',
);

/** An invitation to an event that has since been deleted or hidden. */
const eventGone = computed(
    () =>
        props.item.type === 'InvitedToEvent' &&
        context.value !== undefined &&
        event.value === null,
);

const hasExtra = computed(
    () =>
        canAnswer.value ||
        eventGone.value ||
        Boolean(props.item.excerpt) ||
        request.value !== null ||
        showFollowBack.value ||
        followRequested.value,
);
</script>

<template>
    <article
        class="relative grid grid-cols-[44px_minmax(0,1fr)_auto] gap-x-3 px-4 py-3.5 [grid-template-areas:'av_main_thumb''av_extra_extra'] sm:gap-x-3.5 sm:px-5 sm:py-4"
    >
        <div class="relative self-start [grid-area:av]">
            <Avatar
                :name="actorName"
                :src="props.item.actor?.avatar_url"
                :size="44"
                decorative
            />
            <span
                class="absolute -right-1 -bottom-1 grid size-[22px] place-items-center rounded-full ring-2 ring-canvas sm:ring-surface"
                :class="
                    needsYou
                        ? 'bg-accent text-on-accent'
                        : 'bg-surface text-ink-muted inset-ring inset-ring-line'
                "
                aria-hidden="true"
            >
                <component :is="icon" :size="14" :stroke-width="2.2" />
            </span>
        </div>

        <div class="min-w-0 [grid-area:main]">
            <p class="text-body text-ink">
                <Link
                    :href="props.item.url"
                    class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:rounded-md focus-visible:after:outline-2 focus-visible:after:outline-offset-[-2px] focus-visible:after:outline-ink"
                >
                    <i18n-t :keypath="sentenceKey" scope="global">
                        <template #name>
                            <strong class="font-semibold">{{
                                actorName
                            }}</strong>
                        </template>
                        <template #subject>
                            <strong class="font-semibold">{{
                                props.item.type === 'NewLeafletResponse'
                                    ? `“${props.item.subject_title}”`
                                    : props.item.subject_title
                            }}</strong>
                        </template>
                    </i18n-t>
                </Link>
            </p>
            <p class="mt-0.5 text-meta text-ink-muted">
                <template v-if="eventWhen">{{ eventWhen }} · </template>
                <span v-if="props.unread" class="sr-only">{{
                    t('notifications.unread')
                }}</span>
                <span
                    v-if="props.unread"
                    class="mr-1 inline-block size-[7px] rounded-full bg-accent align-middle"
                    aria-hidden="true"
                />
                <time
                    :datetime="props.item.created_at"
                    :class="props.unread ? 'font-semibold text-accent-ink' : ''"
                    >{{ dates.ago(props.item.created_at) }}</time
                >
            </p>
        </div>

        <div v-if="event" class="ml-2 self-start [grid-area:thumb]">
            <img
                v-if="event.cover && event.cover.shape === 'photo'"
                :src="event.cover.url"
                alt=""
                class="size-12 rounded-sm object-cover"
                loading="lazy"
            />
            <img
                v-else-if="event.cover"
                :src="event.cover.url"
                alt=""
                class="h-[60px] w-10 rounded-sm bg-surface-2 object-contain"
                loading="lazy"
            />
            <DateBadge v-else :date="event.starts_at" size="sm" />
        </div>

        <div
            v-if="hasExtra"
            class="relative z-10 mt-3 flex flex-col items-start gap-3 [grid-area:extra]"
        >
            <div v-if="canAnswer" class="w-full max-w-[400px]">
                <RsvpControl v-model="status" />
            </div>

            <p v-else-if="eventGone" class="text-meta text-ink-subtle">
                {{ t('notifications.eventGone') }}
            </p>

            <blockquote
                v-if="props.item.excerpt"
                class="w-full rounded-r-sm border-l-2 border-line-strong bg-surface px-3.5 py-2.5 font-display text-input text-ink italic sm:bg-canvas"
            >
                “{{ props.item.excerpt }}”
            </blockquote>

            <div
                v-if="request && request.status === 'pending'"
                class="flex flex-wrap gap-2"
            >
                <Button
                    variant="primary"
                    size="sm"
                    :disabled="busy"
                    @click="
                        isFollowRequest
                            ? approveFollow()
                            : answerFriendship('accept')
                    "
                >
                    {{
                        isFollowRequest
                            ? t('notifications.approve')
                            : t('notifications.accept')
                    }}
                </Button>
                <Button
                    size="sm"
                    :disabled="busy"
                    @click="
                        isFollowRequest
                            ? declineFollow()
                            : answerFriendship('decline')
                    "
                >
                    {{ t('notifications.decline') }}
                </Button>
            </div>
            <p
                v-else-if="request"
                class="flex items-center gap-1.5 text-meta text-ink-muted"
            >
                <Check :size="14" :stroke-width="2.2" aria-hidden="true" />
                {{
                    request.status === 'declined'
                        ? t('notifications.declined')
                        : isFollowRequest
                          ? t('notifications.approved')
                          : t('notifications.friendsNow')
                }}
            </p>

            <Button
                v-if="showFollowBack"
                size="sm"
                :icon="UserPlus"
                :disabled="busy"
                @click="followBack"
            >
                {{ t('notifications.followBack') }}
            </Button>
            <p v-else-if="followRequested" class="text-meta text-ink-muted">
                {{ t('notifications.followRequested') }}
            </p>
        </div>
    </article>
</template>
