<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import DateBadge from '@/components/ui/DateBadge.vue';
import type { EventSummary } from '@/events';
import { useDates } from '@/lib/dates';
import { index as eventsIndex, show as eventsShow } from '@/routes/events';

/** The next few events the viewer is going to or hosting. */
const props = defineProps<{ events: EventSummary[] }>();

const { t } = useI18n();
const dates = useDates();

function role(event: EventSummary): string {
    if (event.is_host) {
        return t('events.index.hosting');
    }

    return event.my_status === 'interested'
        ? t('events.rsvp.maybe')
        : t('events.rsvp.going');
}
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-line bg-surface">
        <div class="flex items-center justify-between gap-3 px-4 pt-4 pb-2">
            <h2 class="text-kicker text-ink-muted uppercase">
                {{ t('events.index.comingUp') }}
            </h2>
            <Link
                :href="eventsIndex({ query: { mine: 'going' } }).url"
                class="text-label font-semibold text-ink underline underline-offset-2"
            >
                {{ t('events.index.seeAll') }}
            </Link>
        </div>
        <p
            v-if="!props.events.length"
            class="px-4 pb-4 text-meta text-ink-muted"
        >
            {{ t('events.index.nothingComingUp') }}
        </p>
        <ul v-else>
            <li v-for="event in props.events" :key="event.id">
                <Link
                    :href="eventsShow(event.id).url"
                    class="group/row relative flex items-center gap-3 px-4 py-2.5 transition-colors duration-120 hover:bg-surface-2/55"
                >
                    <span
                        class="pointer-events-none absolute top-0 right-0 left-4 h-px bg-line group-first/row:hidden"
                        aria-hidden="true"
                    />
                    <DateBadge :date="event.starts_at" size="sm" />
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-2 text-label font-semibold">{{
                            event.name
                        }}</span>
                        <span class="block truncate text-meta text-ink-muted"
                            >{{ dates.when(event.starts_at) }} ·
                            {{ role(event) }}</span
                        >
                    </span>
                    <ChevronRight
                        :size="18"
                        :stroke-width="1.9"
                        class="shrink-0 text-ink-subtle"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>
    </section>
</template>
