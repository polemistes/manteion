import type { EventSummary } from '@/events';
import { addDays, isoWeekday } from '@/lib/dates';

export type CalendarView = 'month' | 'week' | 'day';

/** Where a day falls in an event: its only day, first, in between, or last. */
export type DayPart = 'single' | 'first' | 'middle' | 'last';

type DayKeyOf = (value: string | Date) => string;

/**
 * A YYYY-MM-DD day as an instant the date formatters accept: noon UTC,
 * which falls on that same day in any time zone up to 11 hours off UTC.
 */
export function noonOf(day: string): string {
    return `${day}T12:00:00Z`;
}

/** The days a view shows: one, a Monday-to-Sunday week, or a month as whole weeks. */
export function daysOfView(view: CalendarView, date: string): string[] {
    if (view === 'day') {
        return [date];
    }

    if (view === 'week') {
        const monday = addDays(date, 1 - isoWeekday(date));

        return Array.from({ length: 7 }, (_, index) => addDays(monday, index));
    }

    const month = date.slice(0, 7);
    const first = `${month}-01`;
    const days: string[] = [];

    for (
        let monday = addDays(first, 1 - isoWeekday(first));
        monday.slice(0, 7) <= month;
        monday = addDays(monday, 7)
    ) {
        for (let index = 0; index < 7; index++) {
            days.push(addDays(monday, index));
        }
    }

    return days;
}

/** The date of the view one step back (-1) or forward (1). */
export function shiftDate(
    view: CalendarView,
    date: string,
    step: number,
): string {
    if (view === 'month') {
        const [year, month] = date.split('-').map(Number);

        return new Date(Date.UTC(year, month - 1 + step, 1))
            .toISOString()
            .slice(0, 10);
    }

    return addDays(date, view === 'week' ? 7 * step : step);
}

/** The last day an event runs on; one that ends at midnight ends the day before. */
function lastDayOf(event: EventSummary, dayKey: DayKeyOf): string {
    const first = dayKey(event.starts_at);

    if (!event.ends_at) {
        return first;
    }

    const last = dayKey(new Date(new Date(event.ends_at).getTime() - 60_000));

    return last < first ? first : last;
}

/** Each of the given days with the events running on it, in start order. */
export function eventsByDay(
    events: EventSummary[],
    days: string[],
    dayKey: DayKeyOf,
): Map<string, EventSummary[]> {
    const byDay = new Map<string, EventSummary[]>(days.map((day) => [day, []]));
    const firstShown = days[0];
    const lastShown = days[days.length - 1];

    for (const event of events) {
        const first = dayKey(event.starts_at);
        const last = lastDayOf(event, dayKey);

        for (
            let day = first < firstShown ? firstShown : first;
            day <= last && day <= lastShown;
            day = addDays(day, 1)
        ) {
            byDay.get(day)?.push(event);
        }
    }

    return byDay;
}

export function dayPart(
    event: EventSummary,
    day: string,
    dayKey: DayKeyOf,
): DayPart {
    const first = dayKey(event.starts_at);
    const last = lastDayOf(event, dayKey);

    if (first === last) {
        return 'single';
    }

    if (day === first) {
        return 'first';
    }

    return day === last ? 'last' : 'middle';
}
