<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { House, MapPin, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import AvatarStack from '@/components/ui/AvatarStack.vue';
import Button from '@/components/ui/Button.vue';
import DateBadge from '@/components/ui/DateBadge.vue';
import type { EventSummary } from '@/events';
import { useDates } from '@/lib/dates';
import { usePlaces } from '@/lib/places';
import { show as eventsShow } from '@/routes/events';
import AttendanceVisibilityDialog from './AttendanceVisibilityDialog.vue';
import RsvpControl from './RsvpControl.vue';
import RsvpStatusPill from './RsvpStatusPill.vue';
import { useRsvp } from './useRsvp';

/**
 * One event in a list. It lays itself out by the width of its container
 * (put `@container` on the list): stacked under 32rem, image beside the
 * text from there. A photo may be cropped; a poster is always shown whole;
 * without an image the date stands in. The action follows the viewer's
 * relationship to it: answer pills, their answer, Invite for the host.
 */
const props = withDefaults(
    defineProps<{
        event: EventSummary;
        reload?: string[];
    }>(),
    { reload: () => ['events', 'comingUp'] },
);

const { t } = useI18n();
const dates = useDates();
const { cityAndCountry } = usePlaces();

const shape = computed(() => props.event.cover?.shape ?? 'none');

/** Who hosts: the organizer when there is one, else the person. */
const host = computed(() => props.event.organizer ?? props.event.owner);
const href = computed(() => eventsShow(props.event.id).url);

const isPast = computed(() =>
    dates.isOver(props.event.starts_at, props.event.ends_at),
);

const rsvp = useRsvp({
    eventId: () => props.event.id,
    host: () => props.event.owner.name,
    initial: () => ({
        status: props.event.my_status,
        visibility: props.event.my_attendance_visibility,
    }),
    reload: props.reload,
});

const answered = computed(
    () => rsvp.status.value !== null && rsvp.status.value !== 'no_response',
);

// Once answered, an invitation stays on screen and shows the answer.
const showPills = computed(
    () => !props.event.is_host && !isPast.value && !answered.value,
);

const status = computed({
    get: () => rsvp.status.value,
    set: (next) => rsvp.save({ status: next }),
});

const visibilityOpen = ref(false);

const when = computed(() =>
    props.event.ends_at
        ? dates.span(props.event.starts_at, props.event.ends_at)
        : dates.when(props.event.starts_at),
);
const isToday = computed(
    () => dates.daysFromToday(props.event.starts_at) === 0,
);

const place = computed(
    () =>
        props.event.place ||
        cityAndCountry(
            props.event.city,
            props.event.country,
            props.event.country_code,
        ) ||
        null,
);

const layout = computed(
    () =>
        ({
            photo: 'grid-cols-1 @lg:grid-cols-[var(--spacing-media)_minmax(0,1fr)] @lg:grid-rows-[1fr_auto]',
            poster: 'grid-cols-[auto_minmax(0,1fr)] @lg:grid-cols-[var(--spacing-media)_minmax(0,1fr)] @lg:grid-rows-[1fr_auto]',
            none: 'grid-cols-[auto_minmax(0,1fr)]',
        })[shape.value],
);
</script>

<template>
    <article
        class="relative overflow-hidden rounded-lg border bg-surface transition-colors duration-120 [--avatar-ring:var(--color-surface)] hover:border-line-strong has-[.card-link:focus-visible]:outline-2 has-[.card-link:focus-visible]:outline-offset-2 has-[.card-link:focus-visible]:outline-focus"
        :class="props.event.invitation ? 'border-accent-line' : 'border-line'"
    >
        <div
            v-if="props.event.invitation"
            class="flex min-h-11 items-center gap-2.5 bg-accent-soft px-3.5 py-2 text-label text-ink @lg:px-4.5"
        >
            <Avatar
                v-if="props.event.invitation.by"
                :name="props.event.invitation.by.name"
                :src="props.event.invitation.by.avatar_url"
                :size="28"
                decorative
            />
            <span class="min-w-0 flex-1 truncate">
                <template v-if="props.event.invitation.by">
                    <i18n-t keypath="events.card.invitedYou" scope="global">
                        <template #name>
                            <strong class="font-semibold">{{
                                props.event.invitation.by.name
                            }}</strong>
                        </template>
                    </i18n-t>
                </template>
                <template v-else>{{ t('events.card.youreInvited') }}</template>
            </span>
            <span class="shrink-0 text-meta text-ink-muted">{{
                dates.ago(props.event.invitation.at)
            }}</span>
        </div>

        <div class="grid" :class="layout">
            <!-- Media: photo, poster or the date. -->
            <div
                v-if="shape === 'photo'"
                class="overflow-hidden bg-surface-2 @lg:row-span-2"
            >
                <img
                    :src="props.event.cover!.url"
                    :width="props.event.cover!.width ?? undefined"
                    :height="props.event.cover!.height ?? undefined"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="aspect-video w-full object-cover @lg:aspect-auto @lg:h-full @lg:min-h-[212px]"
                />
            </div>
            <div
                v-else-if="shape === 'poster'"
                class="mt-3.5 ml-3.5 @lg:row-span-2 @lg:m-0 @lg:flex @lg:items-center @lg:justify-center @lg:bg-surface-2 @lg:p-4"
            >
                <img
                    :src="props.event.cover!.url"
                    :width="props.event.cover!.width ?? undefined"
                    :height="props.event.cover!.height ?? undefined"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="h-[156px] w-[104px] rounded-sm bg-surface-2 object-contain ring-1 ring-line @lg:h-auto @lg:max-h-[260px] @lg:w-auto @lg:max-w-[156px] @lg:rounded-xs @lg:shadow-sm @lg:ring-0"
                />
            </div>
            <div v-else class="mt-3.5 ml-3.5 @lg:mt-[18px] @lg:ml-5">
                <DateBadge :date="props.event.starts_at" />
            </div>

            <!-- Main: when, title, description, place and host. -->
            <div
                class="min-w-0"
                :class="
                    shape === 'photo'
                        ? 'px-4 pt-3.5 @lg:px-5 @lg:pt-[18px]'
                        : 'pt-3.5 pr-4 pl-3.5 @lg:pt-[18px] @lg:pr-5 @lg:pl-5'
                "
            >
                <p
                    class="text-meta font-semibold"
                    :class="isToday ? 'text-accent-ink' : 'text-ink-muted'"
                >
                    {{ when }}
                </p>
                <h3
                    class="mt-1 mb-2 line-clamp-3 font-display text-title-3 text-pretty text-ink"
                >
                    <Link
                        :href="href"
                        class="card-link after:absolute after:inset-0 focus-visible:outline-none"
                    >
                        {{ props.event.name }}
                    </Link>
                </h3>
                <p
                    v-if="props.event.description"
                    class="mb-2 hidden text-label text-ink-muted @lg:line-clamp-2"
                >
                    {{ props.event.description }}
                </p>
                <ul class="flex flex-col gap-1 text-label text-ink-muted">
                    <li v-if="place" class="flex min-w-0 items-center gap-2">
                        <MapPin
                            :size="16"
                            :stroke-width="2"
                            class="shrink-0 text-ink-subtle"
                            aria-hidden="true"
                        />
                        <span class="truncate">{{ place }}</span>
                        <span
                            v-if="props.event.distance_km !== null"
                            class="shrink-0 text-ink-subtle"
                            >·
                            {{
                                t('events.card.away', {
                                    distance: props.event.distance_km,
                                })
                            }}</span
                        >
                    </li>
                    <li
                        v-if="props.event.is_host"
                        class="flex items-center gap-2"
                    >
                        <House
                            :size="16"
                            :stroke-width="2"
                            class="shrink-0 text-ink-subtle"
                            aria-hidden="true"
                        />
                        {{ t('events.card.youreHosting') }}
                    </li>
                    <li v-else class="flex min-w-0 items-center gap-2">
                        <Avatar
                            :name="host.name"
                            :src="host.avatar_url ?? null"
                            :size="20"
                            decorative
                        />
                        <span class="truncate">{{ host.name }}</span>
                    </li>
                </ul>
            </div>

            <!-- Foot: who's going, then the action. -->
            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-3 px-4 pt-3 pb-4"
                :class="[
                    shape === 'photo'
                        ? '@lg:col-start-2 @lg:px-5 @lg:pb-[18px]'
                        : 'col-span-2',
                    shape === 'poster'
                        ? '@lg:col-span-1 @lg:col-start-2 @lg:px-5 @lg:pb-[18px]'
                        : '',
                    shape === 'none' ? '@lg:px-5 @lg:pb-[18px]' : '',
                ]"
            >
                <span class="flex min-w-0 items-center gap-2.5">
                    <AvatarStack
                        v-if="props.event.going.length"
                        :people="props.event.going"
                        :total="props.event.attending_count"
                        :size="28"
                    />
                    <span class="text-meta text-ink-muted">
                        <template
                            v-if="
                                props.event.attending_count ||
                                props.event.interested_count
                            "
                        >
                            <span class="font-semibold text-ink">{{
                                t('events.card.going', {
                                    n: props.event.attending_count,
                                })
                            }}</span>
                            <template v-if="props.event.interested_count">
                                ·
                                {{
                                    t('events.card.maybe', {
                                        n: props.event.interested_count,
                                    })
                                }}</template
                            >
                        </template>
                        <template v-else>{{
                            t('events.card.noOneYet')
                        }}</template>
                    </span>
                </span>

                <Button
                    v-if="props.event.is_host && !isPast"
                    :as="Link"
                    :href="`${href}#invite`"
                    :icon="UserPlus"
                    size="sm"
                    class="relative z-10 ml-auto"
                >
                    {{ t('events.card.invite') }}
                </Button>
                <RsvpStatusPill
                    v-else-if="answered && !isPast"
                    v-model="status"
                    class="relative z-10 ml-auto"
                    @visibility="visibilityOpen = true"
                />
                <div
                    v-if="showPills"
                    class="relative z-10 w-full basis-full @lg:max-w-[420px]"
                >
                    <RsvpControl v-model="status" />
                </div>
            </div>
        </div>

        <AttendanceVisibilityDialog
            v-model:open="visibilityOpen"
            :host="props.event.owner.name"
            :visibility="rsvp.visibility.value"
            @save="(visibility) => rsvp.save({ visibility })"
        />
    </article>
</template>
