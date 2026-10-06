<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Tag from '@/components/ui/Tag.vue';
import type { EventSummary } from '@/events';
import { useDates } from '@/lib/dates';
import { show as eventsShow } from '@/routes/events';
import { dayPart } from './calendar';

/**
 * One event in a calendar list: the time it starts that day (or "All
 * day", "Until 12:00" on the later days of a longer event), the name,
 * where and whose, and your answer (under the name where the row is
 * narrow, as on phones and in the aside, so the name keeps the width).
 */
const props = defineProps<{
    event: EventSummary;
    day: string;
}>();

const { t } = useI18n();
const dates = useDates();

const part = computed(() => dayPart(props.event, props.day, dates.dayKey));

const startLabel = computed(() => {
    if (part.value === 'middle') {
        return t('events.calendar.allDay');
    }

    if (part.value === 'last' && props.event.ends_at) {
        return t('events.calendar.until', {
            time: dates.time(props.event.ends_at),
        });
    }

    return dates.time(props.event.starts_at);
});

const endLabel = computed(() =>
    part.value === 'single' && props.event.ends_at
        ? dates.time(props.event.ends_at)
        : null,
);

const meta = computed(() =>
    [
        props.event.place ?? props.event.city,
        props.event.is_host
            ? null
            : (props.event.organizer?.name ?? props.event.owner.name),
    ]
        .filter(Boolean)
        .join(' · '),
);

const status = computed<{ label: string; tone: 'going' | 'host' } | null>(
    () => {
        if (props.event.is_host) {
            return { label: t('events.index.hosting'), tone: 'host' };
        }

        if (props.event.my_status === 'attending') {
            return { label: t('events.rsvp.going'), tone: 'going' };
        }

        if (props.event.my_status === 'interested') {
            return { label: t('events.rsvp.maybe'), tone: 'host' };
        }

        if (props.event.invitation) {
            return { label: t('events.calendar.invited'), tone: 'host' };
        }

        return null;
    },
);
</script>

<template>
    <Link
        :href="eventsShow(props.event.id).url"
        class="@container flex items-start gap-3 rounded-md px-3 py-2.5 transition-colors duration-120 hover:bg-surface-2"
    >
        <span
            class="w-[5.25rem] shrink-0 pt-px text-label font-semibold text-ink"
        >
            {{ startLabel }}
            <span
                v-if="endLabel"
                class="block text-caption font-normal text-ink-subtle"
                >{{ endLabel }}</span
            >
        </span>
        <span class="min-w-0 flex-1">
            <span class="line-clamp-2 font-semibold text-ink">{{
                props.event.name
            }}</span>
            <span v-if="meta" class="block truncate text-meta text-ink-muted">{{
                meta
            }}</span>
            <span v-if="status" class="mt-1.5 block @md:hidden">
                <Tag :tone="status.tone">{{ status.label }}</Tag>
            </span>
        </span>
        <span v-if="status" class="mt-0.5 hidden @md:block">
            <Tag :tone="status.tone">{{ status.label }}</Tag>
        </span>
    </Link>
</template>
