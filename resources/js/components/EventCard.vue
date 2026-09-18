<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import EventThumbnail from '@/components/EventThumbnail.vue';
import PersonSummary from '@/components/PersonSummary.vue';
import { update as updateAttendance } from '@/routes/events/attendance';

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
    my_attendance_visibility: string | null;
    cover_image_url: string | null;
    attending_count: number;
    interested_count: number;
    distance_km: number | null;
}

const props = defineProps<{
    event: EventSummary;
}>();

const { t, te, locale } = useI18n();

const locationLabel = computed(
    () =>
        props.event.place ||
        [props.event.city, props.event.country].filter(Boolean).join(', ') ||
        null,
);

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

function isSameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function formatEventDateTime(value: string): string {
    const date = new Date(value);
    const now = new Date();
    const localeCode = dateFnsLocales[locale.value] ?? locale.value;

    const time = date.toLocaleTimeString(localeCode, {
        hour: '2-digit',
        minute: '2-digit',
    });

    if (isSameDay(date, now)) {
        return `${t('events.index.today')}, ${time}`;
    }

    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (isSameDay(date, tomorrow)) {
        return `${t('events.index.tomorrow')}, ${time}`;
    }

    const shortDate = date.toLocaleDateString(localeCode, {
        day: 'numeric',
        month: 'short',
    });

    return `${shortDate}, ${time}`;
}

const statusKeys: Record<string, string> = {
    no_response: 'events.show.statusNoResponse',
    attending: 'events.show.statusAttending',
    interested: 'events.show.statusInterested',
    not_attending: 'events.show.statusNotAttending',
};

function statusLabel(status: string): string {
    return t(statusKeys[status] ?? status);
}

function saveAttendance(status: string, attendanceVisibility: string): void {
    router.put(
        updateAttendance(props.event.id).url,
        { status, attendance_visibility: attendanceVisibility },
        { preserveState: true, preserveScroll: true },
    );
}

function onStatusChange(event: Event): void {
    saveAttendance(
        (event.target as HTMLSelectElement).value,
        props.event.my_attendance_visibility ?? 'everyone',
    );
}

function onVisibilityChange(event: Event): void {
    saveAttendance(
        props.event.my_status ?? 'no_response',
        (event.target as HTMLSelectElement).value,
    );
}
</script>

<template>
    <div
        class="event-card h-full min-w-0 border-frame border-edge p-3"
        :class="{ 'event-card--no-image': !props.event.cover_image_url }"
    >
        <Link
            v-if="props.event.cover_image_url"
            :href="`/events/${props.event.id}`"
            class="event-card__image block h-full w-full"
        >
            <div class="md:hidden">
                <EventThumbnail
                    :url="props.event.cover_image_url"
                    :alt="props.event.name"
                />
            </div>
            <div class="hidden h-full md:block">
                <img
                    :src="props.event.cover_image_url"
                    :alt="props.event.name"
                    class="h-full w-full object-cover"
                />
            </div>
        </Link>

        <p class="event-card__meta truncate text-xs text-ink/70">
            {{ formatEventDateTime(props.event.starts_at) }}
            <template v-if="locationLabel">: {{ locationLabel }}</template>
        </p>

        <Link
            :href="`/events/${props.event.id}`"
            class="event-card__title overflow-hidden text-base font-bold text-ellipsis whitespace-nowrap md:line-clamp-2 md:text-2xl md:text-clip md:whitespace-normal"
        >
            {{ props.event.name }}
        </Link>

        <div class="event-card__categories flex flex-wrap gap-2">
            <span
                v-for="tag in props.event.tags"
                :key="tag.id"
                class="border border-edge px-2 py-0.5 text-xs"
            >
                {{ categoryLabel(tag) }}
            </span>
        </div>

        <div class="event-card__response flex flex-wrap items-center gap-2">
            <select
                class="w-fit border border-edge bg-surface-raised px-2 py-1 text-xs"
                :value="props.event.my_status ?? 'no_response'"
                @change="onStatusChange"
            >
                <option value="no_response">
                    {{ t('events.show.statusNoResponse') }}
                </option>
                <option value="attending">
                    {{ t('events.show.statusAttending') }}
                </option>
                <option value="interested">
                    {{ t('events.show.statusInterested') }}
                </option>
                <option value="not_attending">
                    {{ t('events.show.statusNotAttending') }}
                </option>
            </select>

            <select
                class="w-fit border border-edge bg-surface-raised px-2 py-1 text-xs"
                :value="props.event.my_attendance_visibility ?? 'everyone'"
                @change="onVisibilityChange"
            >
                <option value="everyone">
                    {{ t('events.show.visibilityEveryone') }}
                </option>
                <option value="friends">
                    {{ t('events.show.visibilityFriends') }}
                </option>
                <option value="none">
                    {{ t('events.show.visibilityNone') }}
                </option>
            </select>
        </div>

        <div
            class="event-card__counts flex flex-wrap items-center gap-2 text-xs text-ink/70"
        >
            <span>
                {{
                    t('events.index.attendingCount', {
                        count: props.event.attending_count,
                    })
                }}
            </span>
            <span>
                {{
                    t('events.index.interestedCount', {
                        count: props.event.interested_count,
                    })
                }}
            </span>
            <span v-if="props.event.my_status" class="font-bold text-ink">
                {{ t('events.index.you') }}
                {{ statusLabel(props.event.my_status) }}
            </span>
            <span v-if="props.event.distance_km !== null">
                {{
                    t('location.distanceAway', {
                        distance: props.event.distance_km,
                    })
                }}
            </span>
        </div>

        <div class="event-card__organizer flex items-center text-xs">
            <PersonSummary v-bind="props.event.owner" />
        </div>
    </div>
</template>

<style scoped>
/*
 * The mobile and desktop layouts don't just resize — they reorder content
 * (e.g. attendance counts sit before the image on mobile but after it on
 * desktop, and the image goes from one row to spanning the full card
 * height). Tailwind's utility classes can't express "same elements,
 * different relative order per breakpoint" without either duplicating
 * markup or fighting arbitrary-value syntax for a multi-row
 * grid-template-areas string, so this is a plain CSS grid instead.
 */
.event-card {
    display: grid;
    gap: 0.5rem;
    /* minmax(0, …) lets the text column shrink below its longest word, so
       truncate/line-clamp can do their job instead of widening the card. */
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
        'meta'
        'title'
        'counts'
        'image'
        'categories'
        'response'
        'organizer';
}

.event-card__image {
    grid-area: image;
}

.event-card__meta {
    grid-area: meta;
}

.event-card__title {
    grid-area: title;
}

.event-card__categories {
    grid-area: categories;
}

.event-card__response {
    grid-area: response;
}

.event-card__counts {
    grid-area: counts;
}

.event-card__organizer {
    grid-area: organizer;
}

/* Without an image the text takes the whole card, in the same order as
   the desktop layout below. */
.event-card--no-image {
    grid-template-areas:
        'meta'
        'title'
        'categories'
        'response'
        'counts'
        'organizer';
}

@media (min-width: 768px) {
    .event-card {
        grid-template-columns: 12rem minmax(0, 1fr);
        grid-template-rows: auto auto 1fr auto auto auto;
        column-gap: 1rem;
        grid-template-areas:
            'image meta'
            'image title'
            'image categories'
            'image response'
            'image counts'
            'image organizer';
    }

    .event-card--no-image {
        grid-template-columns: minmax(0, 1fr);
        grid-template-rows: auto auto 1fr auto auto auto;
        grid-template-areas:
            'meta'
            'title'
            'categories'
            'response'
            'counts'
            'organizer';
    }
}
</style>
