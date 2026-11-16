import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/** The Intl locale for each app locale: both 24-hour, weeks from Monday. */
export const intlLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

const DEFAULT_TIMEZONE = 'Europe/Oslo';

const formatters = new Map<string, Intl.DateTimeFormat>();

function formatter(
    locale: string,
    timeZone: string,
    options: Intl.DateTimeFormatOptions,
): Intl.DateTimeFormat {
    const key = `${locale}|${timeZone}|${JSON.stringify(options)}`;
    let cached = formatters.get(key);

    if (!cached) {
        cached = new Intl.DateTimeFormat(locale, { timeZone, ...options });
        formatters.set(key, cached);
    }

    return cached;
}

function toDate(value: string | Date): Date {
    return value instanceof Date ? value : new Date(value);
}

/**
 * ICU versions differ in which space they put in dates ("Fri 6 Nov – Sun 8
 * Nov" may carry thin or narrow no-break spaces in one and plain spaces in
 * another), so the server's render and the browser's would not match.
 * Every formatted string goes through this.
 */
function plainSpaces(text: string): string {
    return text.replace(/[\u00a0\u2009\u202f]/g, ' ');
}

/**
 * The browser's clock once the app runs, ticking every 30 seconds. Until
 * then (on the server, and while the first page hydrates) relative times
 * count from the server's clock instead (the shared `now` prop), so both
 * renders write the same "2 min ago".
 */
const clock = ref<number | null>(null);
let ticking = false;

/** Switches relative times to the browser's clock. Call once, after mount. */
export function startClock(): void {
    if (typeof window === 'undefined' || ticking) {
        return;
    }

    ticking = true;
    clock.value = Date.now();
    setInterval(() => {
        clock.value = Date.now();
    }, 30_000);
}

/** The calendar day of an instant in a time zone, as YYYY-MM-DD. */
export function dayKeyIn(value: string | Date, timeZone: string): string {
    const parts = formatter('en-CA', timeZone, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(toDate(value));
    const part = (type: string) =>
        parts.find((p) => p.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}`;
}

/** Whole days from day key a to day key b (calendar days, no DST drift). */
function daysBetween(a: string, b: string): number {
    const [ay, am, ad] = a.split('-').map(Number);
    const [by, bm, bd] = b.split('-').map(Number);

    return Math.round(
        (Date.UTC(by, bm - 1, bd) - Date.UTC(ay, am - 1, ad)) / 86_400_000,
    );
}

/** ISO 8601 week number of a YYYY-MM-DD day. */
export function isoWeekOfDay(dayKey: string): number {
    const [year, month, day] = dayKey.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    const weekday = date.getUTCDay() || 7;

    date.setUTCDate(date.getUTCDate() + 4 - weekday);

    const yearStart = Date.UTC(date.getUTCFullYear(), 0, 1);

    return Math.ceil(((date.getTime() - yearStart) / 86_400_000 + 1) / 7);
}

export type DaySection = 'today' | 'thisWeek' | 'nextWeek' | 'later';

/** Whether two time zones show the same clock at an instant. */
export function sameClock(
    value: string | Date,
    timeZone: string,
    otherTimeZone: string,
): boolean {
    const instant = toDate(value).getTime();

    return (
        timeZone === otherTimeZone ||
        wallClockOf(instant, timeZone) === wallClockOf(instant, otherTimeZone)
    );
}

/** The city a time zone is named after: "New York" for America/New_York. */
export function cityOfTimeZone(timeZone: string): string {
    return (timeZone.split('/').pop() ?? timeZone).replace(/_/g, ' ');
}

/**
 * Date and time formatting in the viewer's language and the viewer's own
 * time zone (the shared `timezone` prop: their device's, as the server
 * knows it). Every formatter passes both explicitly, so the server-rendered
 * page and the browser always produce the same text (see .ai/rules/js.md).
 * An event in a place on another clock also gets its local time, through
 * `elsewhere`.
 */
export function useDates() {
    const { t, locale } = useI18n();
    const page = usePage();

    const intlLocale = computed(
        () => intlLocales[locale.value] ?? locale.value,
    );
    const timeZone = computed(
        () => (page.props.timezone as string | undefined) || DEFAULT_TIMEZONE,
    );

    const format = (
        value: string | Date,
        options: Intl.DateTimeFormatOptions,
    ) =>
        plainSpaces(
            formatter(intlLocale.value, timeZone.value, options).format(
                toDate(value),
            ),
        );

    const now = (): number => {
        if (clock.value !== null) {
            return clock.value;
        }

        const serverNow = page.props.now as string | undefined;

        return serverNow ? Date.parse(serverNow) : Date.now();
    };

    const dayKey = (value: string | Date) => dayKeyIn(value, timeZone.value);
    const todayKey = () => dayKeyIn(new Date(now()), timeZone.value);

    /** Calendar days from today to the given instant (0 today, 1 tomorrow). */
    const daysFromToday = (value: string | Date) =>
        daysBetween(todayKey(), dayKey(value));

    /** "19:00" */
    const time = (value: string | Date) =>
        format(value, { hour: '2-digit', minute: '2-digit' });

    /** "Fri 16 Oct" / "fre. 16. okt." */
    const shortDate = (value: string | Date) =>
        format(value, { weekday: 'short', day: 'numeric', month: 'short' });

    /** "Friday 16 October" / "fredag 16. oktober" */
    const longDate = (value: string | Date) =>
        format(value, { weekday: 'long', day: 'numeric', month: 'long' });

    /** "16 Oct 2026, 19:00" — for timestamps that are not about "when". */
    const dateTime = (value: string | Date) =>
        format(value, { dateStyle: 'medium', timeStyle: 'short' });

    /** "October 2026" */
    const monthYear = (value: string | Date) =>
        format(value, { month: 'long', year: 'numeric' });

    /** "October" / "oktober" */
    const monthName = (value: string | Date) =>
        format(value, { month: 'long' });

    /** "5 Oct" / "5. okt." */
    const dayMonth = (value: string | Date) =>
        format(value, { day: 'numeric', month: 'short' });

    /** "5" / "5." */
    const dayNumber = (value: string | Date) =>
        format(value, { day: 'numeric' });

    /** "Mon" / "man", or "M" narrow, "Monday" long. */
    const weekday = (
        value: string | Date,
        style: 'long' | 'short' | 'narrow' = 'short',
    ) => format(value, { weekday: style }).replace('.', '');

    /** The event "when" line: "Today · 19:00", "Tomorrow · 19:00", "Fri 9 Oct · 20:00". */
    const when = (value: string | Date) => {
        const days = daysFromToday(value);

        if (days === 0) {
            return `${t('dates.today')} · ${time(value)}`;
        }

        if (days === 1) {
            return `${t('dates.tomorrow')} · ${time(value)}`;
        }

        return `${shortDate(value)} · ${time(value)}`;
    };

    /** A start–end span: one day gives "Fri 16 Oct · 16:00–18:00", several "Fri 16 – Sun 18 Oct". */
    const span = (start: string | Date, end: string | Date | null) => {
        if (!end) {
            return when(start);
        }

        if (dayKey(start) === dayKey(end)) {
            return `${when(start)}–${time(end)}`;
        }

        // Built from parts rather than formatRange, whose spacing differs
        // between ICU versions.
        const sameMonth = dayKey(start).slice(0, 7) === dayKey(end).slice(0, 7);
        const from = sameMonth
            ? format(start, { weekday: 'short', day: 'numeric' })
            : shortDate(start);

        return `${from} – ${shortDate(end)}`;
    };

    /**
     * An event's time where it happens, for one on another clock than the
     * viewer's: "20:00–22:00 local time", written beside the viewer's own
     * time. Null when both clocks agree, so most events get nothing.
     *
     * The day is named only where it is another day than the viewer's
     * ("Fri 20:00 local time" beside Saturday 02:00). `span` follows
     * `span()` above, which gives several-day events without times: those
     * get their local days, and only if they differ. `full` always gives
     * the times (the event page).
     */
    const elsewhere = (
        start: string | Date,
        end: string | Date | null,
        eventTimeZone: string | null | undefined,
        style: 'span' | 'full' = 'span',
    ): string | null => {
        const zone = eventTimeZone;

        if (
            !zone ||
            (sameClock(start, zone, timeZone.value) &&
                (!end || sameClock(end, zone, timeZone.value)))
        ) {
            return null;
        }

        const formatThere = (
            value: string | Date,
            options: Intl.DateTimeFormatOptions,
        ) =>
            plainSpaces(
                formatter(intlLocale.value, zone, options).format(
                    toDate(value),
                ),
            );
        const timeThere = (value: string | Date) =>
            formatThere(value, { hour: '2-digit', minute: '2-digit' });
        const weekdayThere = (value: string | Date) =>
            formatThere(value, { weekday: 'short' }).replace('.', '');
        const dayThere = (value: string | Date) => dayKeyIn(value, zone);
        const phrase = (text: string) => t('dates.localTime', { time: text });

        const startShifted = dayThere(start) !== dayKey(start);
        const startText = startShifted
            ? `${weekdayThere(start)} ${timeThere(start)}`
            : timeThere(start);

        if (!end) {
            return phrase(startText);
        }

        const endShifted = dayThere(end) !== dayKey(end);
        const severalDays = dayKey(start) !== dayKey(end);
        const severalDaysThere = dayThere(start) !== dayThere(end);

        // A festival: days, as the viewer's own line gives it.
        if (style === 'span' && severalDays && severalDaysThere) {
            if (!startShifted && !endShifted) {
                return null;
            }

            const date: Intl.DateTimeFormatOptions = {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
            };

            return phrase(
                `${formatThere(start, date)} – ${formatThere(end, date)}`,
            );
        }

        // The end's day is named when it is not the one a reader expects:
        // another than the viewer's, and not simply the start's again.
        const endText =
            endShifted && severalDaysThere
                ? `${weekdayThere(end)} ${timeThere(end)}`
                : timeThere(end);

        return phrase(`${startText}–${endText}`);
    };

    /** The parts of a date tile: weekday and month in caps, the day number. */
    const tile = (value: string | Date) => ({
        weekday: format(value, { weekday: 'short' })
            .replace('.', '')
            .toUpperCase(),
        // Norwegian writes the day as "5."; the tile shows the number alone.
        day: format(value, { day: 'numeric' }).replace('.', ''),
        month: format(value, { month: 'short' }).replace('.', '').toUpperCase(),
        isToday: daysFromToday(value) === 0,
    });

    /** "2 hr ago", "yesterday", "for 2 t siden". */
    const ago = (value: string | Date) => {
        const seconds = (toDate(value).getTime() - now()) / 1000;
        const relative = new Intl.RelativeTimeFormat(intlLocale.value, {
            numeric: 'auto',
            style: 'short',
        });
        const abs = Math.abs(seconds);

        if (abs < 45) {
            return t('dates.justNow');
        }

        if (abs < 3600) {
            return plainSpaces(
                relative.format(Math.round(seconds / 60), 'minute'),
            );
        }

        if (abs < 86_400) {
            return plainSpaces(
                relative.format(Math.round(seconds / 3600), 'hour'),
            );
        }

        if (abs < 7 * 86_400) {
            return plainSpaces(
                relative.format(Math.round(seconds / 86_400), 'day'),
            );
        }

        return format(value, { day: 'numeric', month: 'short' });
    };

    /** A chat or list timestamp: the time today, the weekday this week, else the date. */
    const listTime = (value: string | Date) => {
        const days = -daysFromToday(value);

        if (days <= 0) {
            return time(value);
        }

        if (days === 1) {
            return t('dates.yesterday');
        }

        if (days < 7) {
            return format(value, { weekday: 'short' });
        }

        return format(value, { day: 'numeric', month: 'short' });
    };

    /** A chat day divider: "Today", "Yesterday", "Thu 1 Oct". */
    const dayDivider = (value: string | Date) => {
        const days = -daysFromToday(value);

        if (days === 0) {
            return t('dates.today');
        }

        if (days === 1) {
            return t('dates.yesterday');
        }

        return shortDate(value);
    };

    /**
     * Which feed section an upcoming instant belongs to. Weeks run Monday to
     * Sunday, so on a Sunday tomorrow is already next week.
     */
    const section = (value: string | Date): DaySection => {
        const days = daysFromToday(value);

        if (days <= 0) {
            return 'today';
        }

        const daysLeftThisWeek = 7 - isoWeekday(todayKey());

        if (days <= daysLeftThisWeek) {
            return 'thisWeek';
        }

        return days <= daysLeftThisWeek + 7 ? 'nextWeek' : 'later';
    };

    /**
     * Whether an event is over: past its end, or for one without an end
     * time, past the end of the day it starts on where it happens (as
     * Event::isOver).
     */
    const isOver = (
        startsAt: string,
        endsAt: string | null,
        eventTimeZone?: string | null,
    ) => {
        if (endsAt) {
            return Date.parse(endsAt) < now();
        }

        const zone = eventTimeZone || timeZone.value;
        const nextDay = addDays(dayKeyIn(startsAt, zone), 1);

        return zonedToUtc(nextDay, '00:00', zone).getTime() <= now();
    };

    /** "Week 40" / "Uke 40" */
    const weekLabel = (value: string | Date) =>
        t('dates.week', { week: isoWeekOfDay(dayKey(value)) });

    return {
        timeZone,
        intlLocale,
        dayKey,
        todayKey,
        daysFromToday,
        time,
        shortDate,
        longDate,
        dateTime,
        monthYear,
        monthName,
        dayMonth,
        dayNumber,
        weekday,
        when,
        span,
        elsewhere,
        tile,
        ago,
        listTime,
        dayDivider,
        section,
        weekLabel,
        isOver,
        isoWeek: (value: string | Date) => isoWeekOfDay(dayKey(value)),
    };
}

/** 1 (Monday) … 7 (Sunday) for a YYYY-MM-DD day. */
export function isoWeekday(dayKey: string): number {
    const [year, month, day] = dayKey.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day)).getUTCDay() || 7;
}

/** Adds whole days to a YYYY-MM-DD day key. */
export function addDays(dayKey: string, days: number): string {
    const [year, month, day] = dayKey.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day + days));

    return date.toISOString().slice(0, 10);
}

/** The Saturday–Sunday span for "this weekend", seen from the given day. */
export function weekendOf(dayKey: string): { start: string; end: string } {
    const weekday = isoWeekday(dayKey);

    if (weekday === 7) {
        return { start: dayKey, end: dayKey };
    }

    const saturday = addDays(dayKey, 6 - weekday);

    return { start: saturday, end: addDays(saturday, 1) };
}

function wallClockOf(instant: number, timeZone: string): number {
    const parts = formatter('en-CA', timeZone, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(instant));
    const get = (type: string) =>
        Number(parts.find((part) => part.type === type)?.value ?? 0);

    return Date.UTC(
        get('year'),
        get('month') - 1,
        get('day'),
        get('hour'),
        get('minute'),
    );
}

/**
 * The UTC instant of a wall-clock date (YYYY-MM-DD) and time (HH:mm) in a
 * time zone, e.g. 16:00 in Oslo. Two passes settle daylight-saving edges.
 */
export function zonedToUtc(day: string, time: string, timeZone: string): Date {
    const [year, month, date] = day.split('-').map(Number);
    const [hours, minutes] = time.split(':').map(Number);
    const wall = Date.UTC(year, month - 1, date, hours, minutes);
    let utc = wall - (wallClockOf(wall, timeZone) - wall);

    utc = wall - (wallClockOf(utc, timeZone) - utc);

    return new Date(utc);
}

/** An instant as wall-clock date (YYYY-MM-DD) and time (HH:mm) in a time zone. */
export function utcToZoned(
    value: string | Date,
    timeZone: string,
): { day: string; time: string } {
    const wall = new Date(wallClockOf(toDate(value).getTime(), timeZone));

    return {
        day: wall.toISOString().slice(0, 10),
        time: wall.toISOString().slice(11, 16),
    };
}
