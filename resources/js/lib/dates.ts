import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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

export type DaySection = 'today' | 'thisWeek' | 'later';

/**
 * Date and time formatting in the viewer's language and the app's display
 * time zone. Every formatter passes both explicitly, so the server-rendered
 * page and the browser always produce the same text (see .ai/rules/pages.md).
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
        formatter(intlLocale.value, timeZone.value, options).format(
            toDate(value),
        );

    const dayKey = (value: string | Date) => dayKeyIn(value, timeZone.value);
    const todayKey = () => dayKeyIn(new Date(), timeZone.value);

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

        return formatter(intlLocale.value, timeZone.value, {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }).formatRange(toDate(start), toDate(end));
    };

    /** The parts of a date tile: weekday and month in caps, the day number. */
    const tile = (value: string | Date) => ({
        weekday: format(value, { weekday: 'short' })
            .replace('.', '')
            .toUpperCase(),
        day: format(value, { day: 'numeric' }),
        month: format(value, { month: 'short' }).replace('.', '').toUpperCase(),
        isToday: daysFromToday(value) === 0,
    });

    /** "2 hr ago", "yesterday", "for 2 t siden". */
    const ago = (value: string | Date) => {
        const seconds = (toDate(value).getTime() - Date.now()) / 1000;
        const relative = new Intl.RelativeTimeFormat(intlLocale.value, {
            numeric: 'auto',
            style: 'short',
        });
        const abs = Math.abs(seconds);

        if (abs < 45) {
            return t('dates.justNow');
        }

        if (abs < 3600) {
            return relative.format(Math.round(seconds / 60), 'minute');
        }

        if (abs < 86_400) {
            return relative.format(Math.round(seconds / 3600), 'hour');
        }

        if (abs < 7 * 86_400) {
            return relative.format(Math.round(seconds / 86_400), 'day');
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

    /** Which feed section an upcoming instant belongs to. */
    const section = (value: string | Date): DaySection => {
        const days = daysFromToday(value);

        if (days <= 0) {
            return 'today';
        }

        const todayWeekday = isoWeekday(todayKey());

        return days <= 7 - todayWeekday ? 'thisWeek' : 'later';
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
        when,
        span,
        tile,
        ago,
        listTime,
        dayDivider,
        section,
        weekLabel,
        isoWeek: (value: string | Date) => isoWeekOfDay(dayKey(value)),
    };
}

/** 1 (Monday) … 7 (Sunday) for a YYYY-MM-DD day. */
function isoWeekday(dayKey: string): number {
    const [year, month, day] = dayKey.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day)).getUTCDay() || 7;
}
