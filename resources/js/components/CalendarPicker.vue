<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    modelValue: Date | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Date];
}>();

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

const weekdayLabels = computed(() => [
    t('calendar.weekdayShort.mon'),
    t('calendar.weekdayShort.tue'),
    t('calendar.weekdayShort.wed'),
    t('calendar.weekdayShort.thu'),
    t('calendar.weekdayShort.fri'),
    t('calendar.weekdayShort.sat'),
    t('calendar.weekdayShort.sun'),
]);

const today = new Date();
const viewYear = ref((props.modelValue ?? today).getFullYear());
const viewMonth = ref((props.modelValue ?? today).getMonth());

watch(
    () => props.modelValue,
    (value) => {
        if (value) {
            viewYear.value = value.getFullYear();
            viewMonth.value = value.getMonth();
        }
    },
);

const monthLabel = computed(() =>
    new Date(viewYear.value, viewMonth.value, 1).toLocaleDateString(
        dateFnsLocales[locale.value] ?? locale.value,
        {
            month: 'long',
            year: 'numeric',
        },
    ),
);

function isoWeek(date: Date): number {
    const d = new Date(
        Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()),
    );
    const dayNum = (d.getUTCDay() + 6) % 7;
    d.setUTCDate(d.getUTCDate() - dayNum + 3);
    const firstThursday = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
    const firstDayNum = (firstThursday.getUTCDay() + 6) % 7;
    firstThursday.setUTCDate(firstThursday.getUTCDate() - firstDayNum + 3);

    return (
        1 +
        Math.round(
            (d.getTime() - firstThursday.getTime()) / (7 * 24 * 3600 * 1000),
        )
    );
}

interface DayCell {
    date: Date;
    inMonth: boolean;
}

const weeks = computed<{ week: number; days: DayCell[] }[]>(() => {
    const firstOfMonth = new Date(viewYear.value, viewMonth.value, 1);
    const startOffset = (firstOfMonth.getDay() + 6) % 7;
    const gridStart = new Date(firstOfMonth);
    gridStart.setDate(gridStart.getDate() - startOffset);

    const result: { week: number; days: DayCell[] }[] = [];
    const cursor = new Date(gridStart);

    for (let week = 0; week < 6; week++) {
        const days: DayCell[] = [];

        for (let day = 0; day < 7; day++) {
            days.push({
                date: new Date(cursor),
                inMonth: cursor.getMonth() === viewMonth.value,
            });
            cursor.setDate(cursor.getDate() + 1);
        }

        result.push({ week: isoWeek(days[0].date), days });
    }

    return result;
});

function isSameDay(a: Date, b: Date | null): boolean {
    return (
        !!b &&
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function previousMonth(): void {
    if (viewMonth.value === 0) {
        viewMonth.value = 11;
        viewYear.value -= 1;
    } else {
        viewMonth.value -= 1;
    }
}

function nextMonth(): void {
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
}

function select(date: Date): void {
    emit('update:modelValue', date);
}
</script>

<template>
    <div class="w-fit border border-edge">
        <div
            class="flex items-center justify-between border-b border-edge px-2 py-1"
        >
            <button
                type="button"
                class="px-1 font-bold hover:bg-accent hover:text-on-accent"
                @click="previousMonth"
            >
                ‹
            </button>
            <span class="text-sm font-bold">{{ monthLabel }}</span>
            <button
                type="button"
                class="px-1 font-bold hover:bg-accent hover:text-on-accent"
                @click="nextMonth"
            >
                ›
            </button>
        </div>
        <table class="border-collapse text-center text-xs">
            <thead>
                <tr>
                    <th
                        class="w-8 border-r border-b border-edge px-1 py-1 font-normal text-ink/50"
                    >
                        {{ t('calendar.weekAbbreviation') }}
                    </th>
                    <th
                        v-for="label in weekdayLabels"
                        :key="label"
                        class="w-8 border-b border-edge px-1 py-1 font-normal"
                    >
                        {{ label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in weeks" :key="row.week">
                    <td class="border-r border-edge px-1 py-1 text-ink/50">
                        {{ row.week }}
                    </td>
                    <td
                        v-for="cell in row.days"
                        :key="cell.date.getTime()"
                        class="p-0.5"
                    >
                        <button
                            type="button"
                            class="flex h-6 w-6 items-center justify-center"
                            :class="[
                                isSameDay(cell.date, props.modelValue)
                                    ? 'bg-accent text-on-accent'
                                    : 'hover:bg-accent hover:text-on-accent',
                                !cell.inMonth ? 'text-ink/30' : '',
                                isSameDay(cell.date, today) &&
                                !isSameDay(cell.date, props.modelValue)
                                    ? 'underline'
                                    : '',
                            ]"
                            @click="select(cell.date)"
                        >
                            {{ cell.date.getDate() }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
