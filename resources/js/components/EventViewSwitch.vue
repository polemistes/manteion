<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    calendar as eventsCalendar,
    index as eventsIndex,
} from '@/routes/events';

const props = defineProps<{ active: 'list' | 'calendar' }>();

const { t } = useI18n();
</script>

<template>
    <nav class="flex" :aria-label="t('events.calendar.viewSwitch')">
        <Link
            :href="eventsIndex().url"
            class="border border-edge px-3 py-1 text-xs"
            :class="
                props.active === 'list'
                    ? 'bg-accent text-on-accent'
                    : 'hover:bg-accent hover:text-on-accent'
            "
            :aria-current="props.active === 'list' ? 'page' : undefined"
        >
            {{ t('events.calendar.listView') }}
        </Link>
        <Link
            :href="eventsCalendar().url"
            class="-ml-px border border-edge px-3 py-1 text-xs"
            :class="
                props.active === 'calendar'
                    ? 'bg-accent text-on-accent'
                    : 'hover:bg-accent hover:text-on-accent'
            "
            :aria-current="props.active === 'calendar' ? 'page' : undefined"
        >
            {{ t('events.calendar.calendarView') }}
        </Link>
    </nav>
</template>
