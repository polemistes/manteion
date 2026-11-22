<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { addDays, utcToZoned, zonedToUtc } from '@/lib/dates';

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
 *
 * `presetTime` opens the pair with a clock time and no day yet (an event
 * started from a template, whose times are known and whose day is not).
 * For an end, `presetDaysLater` says how many days after the start that
 * time falls, until the host types a time or a day of their own.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        label: string;
        defaultValue?: string | null;
        defaultTime?: string;
        /** A clock time to open with while there is no day. */
        presetTime?: string | null;
        /** How many days after the start the preset time falls (an end). */
        presetDaysLater?: number;
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
        presetTime: null,
        presetDaysLater: 0,
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
const time = ref(
    initial?.time ??
        props.presetTime ??
        (props.required ? props.defaultTime : ''),
);
// True while the day was filled in from the start, not set by the host.
const dayDerived = ref(false);
// True while the time is the preset one, untouched: its day is then the
// preset number of days after the start.
let presetHolds = !initial && Boolean(props.presetTime);

/**
 * The day a time alone means: the start's, or the next one when the time
 * is not after the start's; a preset time, the day it was preset for.
 */
function derivedDay(): string {
    if (!props.after || !time.value) {
        return '';
    }

    const start = utcToZoned(props.after, timeZone.value);
    const atLeast = time.value > start.time ? 0 : 1;

    return addDays(
        start.day,
        presetHolds ? Math.max(props.presetDaysLater, atLeast) : atLeast,
    );
}

function onDayInput(event: Event): void {
    day.value = (event.target as HTMLInputElement).value;
    dayDerived.value = false;
    presetHolds = false;

    if (day.value && !time.value) {
        time.value = props.defaultTime;
    }
}

function onTimeInput(event: Event): void {
    time.value = (event.target as HTMLInputElement).value;
    presetHolds = false;

    if (time.value && (!day.value || dayDerived.value) && props.after) {
        day.value = derivedDay();
        dayDerived.value = true;
    } else if (!time.value && dayDerived.value) {
        // The time was what the day stood on: without it there is no end.
        day.value = '';
        dayDerived.value = false;
    }
}

function clear(): void {
    day.value = '';
    time.value = '';
    dayDerived.value = false;
    presetHolds = false;
}

// A day filled in from the start moves with it, and a time typed before
// there was a start gets its day once there is one.
watch(
    () => props.after,
    () => {
        if (time.value && (dayDerived.value || !day.value)) {
            day.value = derivedDay();
            dayDerived.value = day.value !== '';
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
