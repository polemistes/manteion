<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import EventThumbnail from '@/components/EventThumbnail.vue';
import PersonSummary from '@/components/PersonSummary.vue';

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
    place: string;
    starts_at: string;
    visibility: string;
    owner: ContactOption;
    tags: TagOption[];
    my_status: string | null;
    cover_image_url: string | null;
    attending_count: number;
    interested_count: number;
}

const props = defineProps<{
    event: EventSummary;
}>();

const { t, locale } = useI18n();

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
</script>

<template>
    <div class="flex gap-3 border-[3px] border-black p-3">
        <Link
            :href="`/events/${props.event.id}`"
            class="flex w-32 shrink-0 flex-col gap-1"
        >
            <p class="text-xs text-black/70">
                {{ formatEventDateTime(props.event.starts_at) }}
            </p>
            <p class="truncate text-xs text-black/70">
                {{ props.event.place }}
            </p>
            <EventThumbnail
                :url="props.event.cover_image_url"
                :alt="props.event.name"
            />
        </Link>

        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <Link
                :href="`/events/${props.event.id}`"
                class="truncate text-base font-bold underline"
            >
                {{ props.event.name }}
            </Link>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-for="tag in props.event.tags"
                    :key="tag.id"
                    class="border border-black px-2 py-0.5 text-xs"
                >
                    {{ tag.name }}
                </span>
                <span class="text-xs text-black/70">
                    {{
                        t('events.index.attendingCount', {
                            count: props.event.attending_count,
                        })
                    }}
                </span>
                <span class="text-xs text-black/70">
                    {{
                        t('events.index.interestedCount', {
                            count: props.event.interested_count,
                        })
                    }}
                </span>
                <span v-if="props.event.my_status" class="text-xs font-bold">
                    {{ t('events.index.you') }}
                    {{ statusLabel(props.event.my_status) }}
                </span>
            </div>

            <div class="mt-auto flex items-center gap-1 text-xs text-black/70">
                <span>{{ t('events.index.organizer') }}</span>
                <PersonSummary v-bind="props.event.owner" />
            </div>
        </div>
    </div>
</template>
