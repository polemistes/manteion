<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    ChevronDown,
    ChevronLeft,
    Earth,
    Ellipsis,
    Eye,
    Flag,
    Lock,
    Maximize2,
    MapPin,
    Pencil,
    Search,
    Share,
    Trash,
    UserPlus,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AttendanceVisibilityDialog from '@/components/events/AttendanceVisibilityDialog.vue';
import CommentThread from '@/components/events/CommentThread.vue';
import type { EventCommentItem } from '@/components/events/CommentThread.vue';
import EventMapCard from '@/components/events/EventMapCard.vue';
import GuestFaces from '@/components/events/GuestFaces.vue';
import InviteDialog from '@/components/events/InviteDialog.vue';
import RsvpControl from '@/components/events/RsvpControl.vue';
import { useRsvp } from '@/components/events/useRsvp';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import DateBadge from '@/components/ui/DateBadge.vue';
import DateLeaf from '@/components/ui/DateLeaf.vue';
import IconButton from '@/components/ui/IconButton.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import Tag from '@/components/ui/Tag.vue';
import { confirmAction } from '@/composables/useConfirm';
import { report } from '@/composables/useReport';
import type {
    AttendanceStatus,
    AttendanceVisibility,
    EventCover,
    EventInvitation,
    TagOption,
} from '@/events';
import { useDates } from '@/lib/dates';
import { usePlaces } from '@/lib/places';
import { toast, toastError } from '@/lib/toast';
import { useEventChannel } from '@/realtime';
import {
    calendarFile,
    destroy as destroyEvent,
    edit as editEvent,
    index as eventsIndex,
} from '@/routes/events';
import { store as makeSearchable } from '@/routes/events/searchable';
import { show as usersShow } from '@/routes/users';

interface Person {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
}

const props = defineProps<{
    event: {
        id: number;
        name: string;
        place: string | null;
        latitude: number | null;
        longitude: number | null;
        city: string | null;
        country: string | null;
        country_code: string | null;
        description: string;
        starts_at: string;
        ends_at: string | null;
        visibility: 'private' | 'public' | 'searchable';
        allow_invitee_invites: boolean;
        allow_invitee_comments: boolean;
        is_searchable_paid: boolean;
        owner: Person;
        tags: TagOption[];
        images: EventCover[];
        is_owner: boolean;
        is_deleted: boolean;
        can_invite: boolean;
        can_comment: boolean;
        can_delete: boolean;
        can_report: boolean;
    };
    myAttendance: {
        status: AttendanceStatus;
        attendance_visibility: AttendanceVisibility;
    } | null;
    invitation: EventInvitation | null;
    attendees: { id: number; user: Person; status: string }[];
    comments: EventCommentItem[];
    inviteCandidates: Person[];
}>();

const { t, te } = useI18n();
const dates = useDates();
const { cityAndCountry } = usePlaces();

const cover = computed<EventCover | null>(() => props.event.images[0] ?? null);
const shape = computed<'photo' | 'poster' | 'none'>(
    () => cover.value?.shape ?? 'none',
);

const isPast = computed(() =>
    dates.isOver(props.event.starts_at, props.event.ends_at),
);

const canAnswer = computed(
    () => !props.event.is_owner && !isPast.value && !props.event.is_deleted,
);

// Live: answers, comments and edits by others refresh the page's data.
useEventChannel(
    () => props.event.id,
    () =>
        router.reload({
            only: [
                'event',
                'attendees',
                'comments',
                'myAttendance',
                'invitation',
            ],
        }),
);

const rsvp = useRsvp({
    eventId: () => props.event.id,
    host: () => props.event.owner.name,
    initial: () => ({
        status: props.myAttendance?.status ?? null,
        visibility: props.myAttendance?.attendance_visibility ?? null,
    }),
    reload: ['event', 'attendees', 'myAttendance', 'invitation'],
});

const status = computed({
    get: () => rsvp.status.value,
    set: (next) => rsvp.save({ status: next }),
});

const answerState = computed(() => {
    switch (rsvp.status.value) {
        case 'attending':
            return t('events.rsvp.youreGoing');
        case 'interested':
            return t('events.rsvp.youSaidMaybe');
        case 'not_attending':
            return t('events.rsvp.youCantGo');
        default:
            return t('events.rsvp.areYouGoing');
    }
});

const visibilityLabels = computed(() => ({
    short: {
        everyone: t('events.rsvp.everyoneShort'),
        friends: t('events.rsvp.friendsShort'),
        none: t('events.rsvp.noneShort'),
    }[rsvp.visibility.value],
    long: {
        everyone: t('events.rsvp.everyone'),
        friends: t('events.rsvp.friends'),
        none: t('events.rsvp.none'),
    }[rsvp.visibility.value],
}));

const visibilityOpen = ref(false);
const inviteOpen = ref(false);
const posterOpen = ref(false);

// "Invite" on a feed card lands here with #invite.
onMounted(() => {
    if (window.location.hash === '#invite' && props.event.can_invite) {
        inviteOpen.value = true;
    }
});

// Facts: when, where, who.
const whenLine = computed(() => {
    const start = props.event.starts_at;
    const end = props.event.ends_at;
    const startText = `${dates.shortDate(start)}, ${dates.time(start)}`;

    if (!end) {
        return startText;
    }

    return dates.dayKey(start) === dates.dayKey(end)
        ? `${startText}–${dates.time(end)}`
        : `${startText} – ${dates.shortDate(end)}, ${dates.time(end)}`;
});

const nights = computed(() => {
    if (!props.event.ends_at) {
        return 0;
    }

    const start = Date.parse(
        `${dates.dayKey(props.event.starts_at)}T00:00:00Z`,
    );
    const end = Date.parse(`${dates.dayKey(props.event.ends_at)}T00:00:00Z`);

    return Math.round((end - start) / 86_400_000);
});

const address = computed(() =>
    cityAndCountry(
        props.event.city,
        props.event.country,
        props.event.country_code,
    ),
);

const hasMap = computed(
    () => props.event.latitude !== null && props.event.longitude !== null,
);

/** The map card shows for a pinned place with a name. */
const hasMapCard = computed(() => hasMap.value && !!props.event.place);

const directionsUrl = computed(() =>
    hasMap.value
        ? `https://www.google.com/maps/dir/?api=1&destination=${props.event.latitude},${props.event.longitude}`
        : null,
);

const visibilityLabel = computed(
    () =>
        ({
            private: t('events.show.visibilityPrivate'),
            public: t('events.show.visibilityPublic'),
            searchable: t('events.show.visibilitySearchable'),
        })[props.event.visibility],
);

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

// Actions.
async function share(): Promise<void> {
    const url = window.location.href;

    if (navigator.share) {
        try {
            await navigator.share({ title: props.event.name, url });
        } catch {
            // Dismissed: nothing to do.
        }

        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        toast({ title: t('events.show.linkCopied'), icon: Share });
    } catch {
        toastError(t('ui.somethingWentWrong'));
    }
}

async function deleteEvent(): Promise<void> {
    const confirmed = await confirmAction({
        title: t('events.show.confirmDeleteTitle'),
        message: t('events.show.confirmDelete'),
        confirmLabel: t('events.show.delete'),
    });

    if (confirmed) {
        router.delete(destroyEvent(props.event.id).url);
    }
}

function searchable(): void {
    router.post(
        makeSearchable(props.event.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () =>
                toast({ title: t('events.show.searchableDone'), icon: Search }),
        },
    );
}

const showMenu = computed(
    () =>
        props.event.is_owner ||
        props.event.can_invite ||
        props.event.can_delete ||
        props.event.can_report,
);
</script>

<template>
    <Head :title="props.event.name" />

    <PageHeader
        :title="props.event.name"
        :back="eventsIndex().url"
        :back-label="t('events.show.back')"
        :phone-hidden="shape !== 'none'"
        header-hidden
        width="wide"
    >
        <template #phone-actions>
            <IconButton
                :icon="Share"
                :label="t('events.show.share')"
                @click="share"
            />
        </template>
    </PageHeader>

    <PageContainer
        width="wide"
        class="pb-[calc(140px+env(safe-area-inset-bottom))] sm:pb-0"
        :class="!canAnswer ? 'pb-10' : ''"
    >
        <div class="xl:grid xl:grid-cols-[minmax(0,1fr)_360px] xl:gap-14">
            <article class="min-w-0">
                <!-- Tablet and desktop: crumb and actions. -->
                <div
                    class="hidden items-center justify-between gap-3 pt-6 pb-4 sm:flex"
                >
                    <Link
                        :href="eventsIndex().url"
                        class="-ml-3 inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-label font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink"
                    >
                        <ArrowLeft
                            :size="16"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                        {{ t('events.show.back') }}
                    </Link>
                    <div class="flex items-center gap-1">
                        <IconButton
                            :icon="Share"
                            :label="t('events.show.share')"
                            @click="share"
                        />
                        <Menu v-if="showMenu">
                            <template #trigger>
                                <IconButton
                                    :icon="Ellipsis"
                                    :label="t('events.show.more')"
                                />
                            </template>
                            <MenuItem
                                v-if="props.event.is_owner"
                                :icon="Pencil"
                                as-child
                            >
                                <Link :href="editEvent(props.event.id).url">{{
                                    t('events.show.edit')
                                }}</Link>
                            </MenuItem>
                            <MenuItem
                                v-if="props.event.can_invite"
                                :icon="UserPlus"
                                @select="inviteOpen = true"
                                >{{ t('events.show.invite') }}</MenuItem
                            >
                            <MenuItem
                                v-if="
                                    props.event.is_owner &&
                                    props.event.visibility === 'public' &&
                                    !props.event.is_searchable_paid
                                "
                                :icon="Search"
                                @select="searchable"
                                >{{ t('events.show.makeSearchable') }}</MenuItem
                            >
                            <MenuItem
                                v-if="props.event.can_report"
                                :icon="Flag"
                                @select="report('event', props.event.id)"
                                >{{ t('events.show.report') }}</MenuItem
                            >
                            <template v-if="props.event.can_delete">
                                <MenuSeparator />
                                <MenuItem
                                    :icon="Trash"
                                    danger
                                    @select="deleteEvent"
                                    >{{ t('events.show.delete') }}</MenuItem
                                >
                            </template>
                        </Menu>
                    </div>
                </div>

                <!-- The cover, before xl (where it moves to the aside). -->
                <div
                    v-if="shape !== 'none'"
                    class="relative -mx-4 sm:mx-0 xl:hidden"
                >
                    <div
                        v-if="shape === 'photo'"
                        class="aspect-[3/2] overflow-hidden bg-surface-2 sm:aspect-video sm:rounded-lg"
                    >
                        <img
                            :src="cover!.url"
                            :width="cover!.width ?? undefined"
                            :height="cover!.height ?? undefined"
                            alt=""
                            class="size-full object-cover"
                        />
                    </div>
                    <div
                        v-else
                        class="flex justify-center bg-surface-2 px-6 pt-[calc(56px+env(safe-area-inset-top))] pb-6 sm:rounded-lg sm:p-6"
                    >
                        <button
                            type="button"
                            class="relative"
                            :aria-label="t('events.show.expandPoster')"
                            @click="posterOpen = true"
                        >
                            <img
                                :src="cover!.url"
                                :width="cover!.width ?? undefined"
                                :height="cover!.height ?? undefined"
                                alt=""
                                class="h-[min(58vh,480px)] w-auto max-w-full rounded-xs object-contain shadow-lg sm:h-[440px]"
                            />
                            <span
                                class="absolute right-2 bottom-2 grid size-11 place-items-center rounded-full bg-surface/92 text-ink shadow-sm"
                                aria-hidden="true"
                            >
                                <Maximize2 :size="20" :stroke-width="1.8" />
                            </span>
                        </button>
                    </div>

                    <!-- Phone: floating buttons over the image. -->
                    <div
                        class="absolute inset-x-3 top-[calc(8px+env(safe-area-inset-top))] flex items-center justify-between sm:hidden"
                    >
                        <IconButton
                            :as="Link"
                            :href="eventsIndex().url"
                            :icon="ChevronLeft"
                            :label="t('events.show.back')"
                            variant="floating"
                        />
                        <div class="flex gap-2">
                            <IconButton
                                :icon="Share"
                                :label="t('events.show.share')"
                                variant="floating"
                                @click="share"
                            />
                            <Menu v-if="showMenu">
                                <template #trigger>
                                    <IconButton
                                        :icon="Ellipsis"
                                        :label="t('events.show.more')"
                                        variant="floating"
                                    />
                                </template>
                                <MenuItem
                                    v-if="props.event.is_owner"
                                    :icon="Pencil"
                                    as-child
                                >
                                    <Link
                                        :href="editEvent(props.event.id).url"
                                        >{{ t('events.show.edit') }}</Link
                                    >
                                </MenuItem>
                                <MenuItem
                                    v-if="props.event.can_invite"
                                    :icon="UserPlus"
                                    @select="inviteOpen = true"
                                    >{{ t('events.show.invite') }}</MenuItem
                                >
                                <MenuItem
                                    v-if="props.event.can_report"
                                    :icon="Flag"
                                    @select="report('event', props.event.id)"
                                    >{{ t('events.show.report') }}</MenuItem
                                >
                                <template v-if="props.event.can_delete">
                                    <MenuSeparator />
                                    <MenuItem
                                        :icon="Trash"
                                        danger
                                        @select="deleteEvent"
                                        >{{ t('events.show.delete') }}</MenuItem
                                    >
                                </template>
                            </Menu>
                        </div>
                    </div>
                </div>

                <div
                    v-if="props.event.is_deleted"
                    class="mt-5 rounded-md bg-danger-soft px-4 py-3 text-label font-medium text-danger"
                >
                    {{ t('events.show.deletedEvent') }}
                </div>

                <!-- Invitation, title. From xl the cover is in the aside, so
                     the title follows the crumb row closely. -->
                <div class="mt-5 sm:mt-6 xl:mt-1">
                    <p
                        v-if="props.invitation?.by"
                        class="mb-3 inline-flex max-w-full items-center gap-2 rounded-full bg-accent-soft py-1 pr-3 pl-1 text-label text-ink"
                    >
                        <Avatar
                            :name="props.invitation.by.name"
                            :src="props.invitation.by.avatar_url"
                            :size="28"
                            decorative
                        />
                        <span class="truncate">
                            <i18n-t
                                keypath="events.card.invitedYou"
                                scope="global"
                            >
                                <template #name>
                                    <strong class="font-semibold">{{
                                        props.invitation.by.name
                                    }}</strong>
                                </template>
                            </i18n-t>
                            · {{ dates.ago(props.invitation.at) }}
                        </span>
                    </p>
                    <div class="flex items-start gap-4">
                        <DateBadge
                            v-if="shape === 'none'"
                            :date="props.event.starts_at"
                            size="lg"
                            class="xl:hidden"
                        />
                        <h1
                            class="min-w-0 font-display text-title-1 text-pretty lg:text-display"
                        >
                            {{ props.event.name }}
                        </h1>
                    </div>
                </div>

                <!-- Facts. -->
                <ul class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <li class="flex gap-3.5">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                        >
                            <Calendar
                                :size="20"
                                :stroke-width="1.8"
                                aria-hidden="true"
                            />
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ whenLine }}</p>
                            <p class="text-meta text-ink-muted">
                                <template v-if="nights > 0"
                                    >{{ t('events.show.nights', nights) }} ·
                                </template>
                                <a
                                    :href="calendarFile(props.event.id).url"
                                    class="text-ink underline underline-offset-2"
                                    >{{ t('events.show.addToCalendar') }}</a
                                >
                            </p>
                        </div>
                    </li>
                    <li v-if="props.event.place" class="flex gap-3.5">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                        >
                            <MapPin
                                :size="20"
                                :stroke-width="1.8"
                                aria-hidden="true"
                            />
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">
                                {{ props.event.place }}
                            </p>
                            <p class="text-meta text-ink-muted">
                                <template v-if="address">{{
                                    address
                                }}</template>
                                <template v-if="address && directionsUrl">
                                    ·
                                </template>
                                <a
                                    v-if="directionsUrl"
                                    :href="directionsUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-ink underline underline-offset-2"
                                    >{{ t('events.show.directions') }}</a
                                >
                            </p>
                        </div>
                    </li>
                    <li class="flex gap-3.5 sm:col-span-2 xl:col-span-1">
                        <Link
                            :href="usersShow(props.event.owner.id).url"
                            class="shrink-0"
                        >
                            <Avatar
                                :name="props.event.owner.name"
                                :src="props.event.owner.avatar_url"
                                :size="40"
                            />
                        </Link>
                        <div class="min-w-0">
                            <Link
                                :href="usersShow(props.event.owner.id).url"
                                class="font-semibold text-ink hover:underline"
                                >{{ props.event.owner.name }}</Link
                            >
                            <p
                                class="flex items-center gap-1.5 text-meta text-ink-muted"
                            >
                                {{ t('events.show.host') }} ·
                                <component
                                    :is="
                                        props.event.visibility === 'private'
                                            ? Lock
                                            : Earth
                                    "
                                    :size="13"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                                {{ visibilityLabel }}
                            </p>
                        </div>
                    </li>
                </ul>

                <!-- Tablet and desktop: the answer as a card. -->
                <section
                    v-if="canAnswer"
                    class="mt-6 hidden rounded-lg border border-line bg-surface px-4.5 pt-4 pb-4.5 sm:block xl:px-5 xl:pt-4.5 xl:pb-5"
                >
                    <div
                        class="mb-3 flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="font-display text-title-4">
                            {{ answerState }}
                        </h2>
                        <button
                            type="button"
                            class="relative inline-flex min-h-8 items-center gap-1.5 text-meta font-medium text-ink-muted after:absolute after:inset-x-0 after:-inset-y-1.5 hover:text-ink"
                            @click="visibilityOpen = true"
                        >
                            <Eye
                                :size="16"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                            {{ visibilityLabels.long }}
                            <ChevronDown
                                :size="16"
                                :stroke-width="2"
                                aria-hidden="true"
                            />
                        </button>
                    </div>
                    <RsvpControl v-model="status" class="max-w-[460px]" />
                </section>
                <p
                    v-else-if="isPast"
                    class="mt-6 rounded-md bg-surface-2 px-4 py-3 text-label text-ink-muted"
                >
                    {{ t('events.show.ended') }}
                </p>

                <!-- Who's coming, About and the map. Phone: in that order.
                     640–767: guests, map, About. 768–1279: guests and map
                     side by side, then About. xl: guests and map move to
                     the aside (the map card hidden here waits to start). -->
                <div
                    class="mt-8 grid gap-y-8 md:grid-cols-2 md:gap-x-6 md:gap-y-8 xl:block"
                >
                    <div
                        class="border-y border-line py-6 sm:border-0 sm:py-0 xl:hidden"
                    >
                        <GuestFaces
                            :attendees="props.attendees"
                            :host="props.event.owner"
                            :viewer-is-host="props.event.is_owner"
                        />
                    </div>
                    <EventMapCard
                        v-if="hasMapCard"
                        class="row-start-3 sm:row-start-2 md:col-start-2 md:row-start-1 xl:hidden"
                        :latitude="props.event.latitude!"
                        :longitude="props.event.longitude!"
                        :place="props.event.place ?? ''"
                        :address="address"
                    />

                    <!-- About. -->
                    <section
                        class="md:col-span-2"
                        :class="
                            hasMapCard
                                ? 'row-start-2 sm:row-start-3 md:row-start-2'
                                : ''
                        "
                    >
                        <h2 class="mb-2 text-kicker text-ink-muted uppercase">
                            {{ t('events.show.about') }}
                        </h2>
                        <div
                            class="max-w-reading font-display text-reading whitespace-pre-line text-ink"
                        >
                            {{ props.event.description }}
                        </div>
                        <div
                            v-if="props.event.tags.length"
                            class="mt-5 flex flex-wrap gap-2"
                        >
                            <Tag
                                v-for="tag in props.event.tags"
                                :key="tag.id"
                                >{{ categoryLabel(tag) }}</Tag
                            >
                        </div>
                    </section>
                </div>

                <div class="mt-10 max-w-reading">
                    <CommentThread
                        :event-id="props.event.id"
                        :host-id="props.event.owner.id"
                        :comments="props.comments"
                        :can-comment="props.event.can_comment"
                    />
                </div>
            </article>

            <!-- xl: the cover, guests and map beside the text. -->
            <aside class="hidden xl:block">
                <div class="sticky top-6 flex flex-col gap-4 pt-6">
                    <div
                        v-if="shape === 'photo'"
                        class="aspect-[3/2] overflow-hidden rounded-lg bg-surface-2"
                    >
                        <img
                            :src="cover!.url"
                            alt=""
                            class="size-full object-cover"
                        />
                    </div>
                    <button
                        v-else-if="shape === 'poster'"
                        type="button"
                        class="flex justify-center rounded-lg bg-surface-2 p-5"
                        :aria-label="t('events.show.expandPoster')"
                        @click="posterOpen = true"
                    >
                        <img
                            :src="cover!.url"
                            alt=""
                            class="max-h-[560px] w-auto max-w-full rounded-xs object-contain shadow-lg"
                        />
                    </button>
                    <DateLeaf
                        v-else
                        :date="props.event.starts_at"
                        :end="props.event.ends_at"
                    />
                    <div class="rounded-lg border border-line bg-surface p-4">
                        <GuestFaces
                            :attendees="props.attendees"
                            :host="props.event.owner"
                            :viewer-is-host="props.event.is_owner"
                        />
                    </div>
                    <EventMapCard
                        v-if="hasMapCard"
                        :latitude="props.event.latitude!"
                        :longitude="props.event.longitude!"
                        :place="props.event.place ?? ''"
                        :address="address"
                    />
                </div>
            </aside>
        </div>
    </PageContainer>

    <!-- Phone: the answer bar. -->
    <div
        v-if="canAnswer"
        class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface px-4 pt-2.5 pb-[calc(10px+env(safe-area-inset-bottom))] shadow-[0_-8px_24px_-16px_var(--shadow-tint-strong)] sm:hidden"
    >
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="font-semibold text-ink">{{ answerState }}</p>
            <button
                type="button"
                class="relative inline-flex min-h-8 items-center gap-1 text-meta font-medium text-ink-muted after:absolute after:inset-x-0 after:-inset-y-1.5"
                @click="visibilityOpen = true"
            >
                <Eye :size="16" :stroke-width="2" aria-hidden="true" />
                {{ visibilityLabels.short }}
                <ChevronDown :size="16" :stroke-width="2" aria-hidden="true" />
            </button>
        </div>
        <RsvpControl v-model="status" />
    </div>

    <AttendanceVisibilityDialog
        v-model:open="visibilityOpen"
        :host="props.event.owner.name"
        :visibility="rsvp.visibility.value"
        @save="(visibility) => rsvp.save({ visibility })"
    />
    <InviteDialog
        v-if="props.event.can_invite"
        v-model:open="inviteOpen"
        :event-id="props.event.id"
        :candidates="props.inviteCandidates"
    />
    <AppDialog
        v-if="cover && shape === 'poster'"
        v-model:open="posterOpen"
        :title="props.event.name"
        hide-title
        size="lg"
    >
        <img
            :src="cover.url"
            alt=""
            class="mx-auto max-h-[80dvh] w-auto max-w-full rounded-xs object-contain"
        />
    </AppDialog>
</template>
