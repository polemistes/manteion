<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { utcToZoned, zonedToUtc } from '@/lib/dates';

/**
 * A date and a time, as the phone's own pickers, read as wall-clock time
 * in `timeZone` (the event's own; the viewer's when not given) and
 * submitted as a UTC instant in a hidden input named `name`. `change`
 * reports the instant ('' for none). When the time zone changes (the pin
 * moved to another one), the clock time typed stays and the instant moves.
 *
 * An end (`after` is the start it follows) is a pair: a time typed without
 * a day means that day, so the day is filled in beside it (the start's
 * day, or the next when the time is not after the start's) and follows the
 * start until the host sets one; a day picked without a time gets the
 * default time. Nothing typed is dropped unseen, and "Clear" empties both.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        defaultValue?: string | null;
        defaultTime?: string;
        timeZone?: string | null;
        /** The instant this one follows (an end's start), '' or null for none. */
        after?: string | null;
        required?: boolean;
        optional?: boolean;
        error?: string;
    }>(),
    {
        defaultValue: null,
        defaultTime: '18:00',
        timeZone: null,
        after: null,
        required: false,
        optional: false,
        error: undefined,
    },
);

const emit = defineEmits<{ change: [value: string] }>();

const { t } = useI18n();
const page = usePage();
const id = useId();

const timeZone = computed(
    () =>
        props.timeZone ||
        (page.props.timezone as string | undefined) ||
        'Europe/Oslo',
);

const initial = props.defaultValue
    ? utcToZoned(props.defaultValue, timeZone.value)
    : null;

const day = ref(initial?.day ?? '');
const time = ref(initial?.time ?? (props.required ? props.defaultTime : ''));
// True while the day was filled in from the start, not set by the host.
const dayDerived = ref(false);

function dayAfter(key: string): string {
    const [year, month, date] = key.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, date + 1))
        .toISOString()
        .slice(0, 10);
}

/** The day a time alone means: the start's, or the next one. */
function derivedDay(): string {
    if (!props.after || !time.value) {
        return '';
    }

    const start = utcToZoned(props.after, timeZone.value);

    return time.value > start.time ? start.day : dayAfter(start.day);
}

function onDayInput(event: Event): void {
    day.value = (event.target as HTMLInputElement).value;
    dayDerived.value = false;

    if (day.value && !time.value) {
        time.value = props.defaultTime;
    }
}

function onTimeInput(event: Event): void {
    time.value = (event.target as HTMLInputElement).value;

    if (time.value && (!day.value || dayDerived.value) && props.after) {
        day.value = derivedDay();
        dayDerived.value = true;
    }
}

function clear(): void {
    day.value = '';
    time.value = '';
    dayDerived.value = false;
}

// A day filled in from the start moves with it.
watch(
    () => props.after,
    () => {
        if (dayDerived.value && time.value) {
            day.value = derivedDay();
        }
    },
);

const value = computed(() => {
    // A time without a day (the host emptied the day) still means a day.
    const onDay = day.value || derivedDay();

    if (!onDay) {
        return '';
    }

    return zonedToUtc(
        onDay,
        time.value || props.defaultTime,
        timeZone.value,
    ).toISOString();
});

watch(value, (next) => emit('change', next));
</script>

<template>
    <fieldset class="flex flex-col gap-1.5">
        <legend
            class="mb-1.5 flex w-full items-center gap-2 text-label font-semibold text-ink"
        >
            <span class="min-w-0 flex-1">
                {{ props.label }}
                <span v-if="props.optional" class="font-normal text-ink-muted"
                    >({{ t('ui.optional') }})</span
                >
            </span>
            <button
                v-if="props.optional && (day || time)"
                type="button"
                class="relative shrink-0 font-semibold text-ink underline underline-offset-2 after:absolute after:-inset-x-2 after:-inset-y-3"
                :aria-label="
                    t('events.form.clearTimeOf', { field: props.label })
                "
                @click="clear"
            >
                {{ t('events.form.clearTime') }}
            </button>
        </legend>
        <input type="hidden" :name="props.name" :value="value" />
        <div class="grid grid-cols-[1.6fr_1fr] gap-2">
            <input
                :id="`${id}-day`"
                :value="day"
                type="date"
                :required="props.required"
                :aria-label="props.label"
                :aria-invalid="props.error ? 'true' : undefined"
                @input="onDayInput"
                class="h-12 w-full rounded-md bg-surface px-3.5 text-input text-ink ring-1 ring-control ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none"
                :class="props.error ? 'ring-2 ring-danger' : ''"
            />
            <input
                :id="`${id}-time`"
                :value="time"
                type="time"
                :required="props.required"
                :aria-label="`${props.label} – ${t('events.form.time')}`"
                @input="onTimeInput"
                class="h-12 w-full rounded-md bg-surface px-3.5 text-input text-ink ring-1 ring-control ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none"
            />
        </div>
        <p
            v-if="props.error"
            class="flex items-center gap-1.5 text-meta font-medium text-danger"
        >
            <CircleAlert :size="16" :stroke-width="2" aria-hidden="true" />
            {{ props.error }}
        </p>
    </fieldset>
</template>
