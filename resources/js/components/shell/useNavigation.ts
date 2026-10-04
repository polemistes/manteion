import { usePage } from '@inertiajs/vue3';
import { Bell, Calendar, MessageCircle, Newspaper, Users } from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBadges } from '@/realtime';
import { index as contactsIndex } from '@/routes/contacts';
import { index as eventsIndex } from '@/routes/events';
import { index as leafletsIndex } from '@/routes/leaflets';
import { index as messagesIndex } from '@/routes/messages';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as settingsIndex } from '@/routes/settings';

export type NavKey =
    'events' | 'leaflets' | 'messages' | 'people' | 'activity' | 'me';

export interface NavItem {
    key: NavKey;
    label: string;
    href: string;
    icon: Component | null;
    badge: number;
    badgeLabel?: string;
    active: boolean;
}

/** Path prefixes that belong to each section of the app. */
const sections: Record<NavKey, string[]> = {
    events: ['/events'],
    leaflets: ['/leaflets'],
    messages: ['/messages'],
    people: ['/contacts', '/users'],
    activity: ['/notifications'],
    me: ['/settings', '/profile', '/admin', '/moderation'],
};

function inSection(url: string, key: NavKey): boolean {
    const path = url.split('?')[0];

    return sections[key].some(
        (prefix) => path === prefix || path.startsWith(`${prefix}/`),
    );
}

const TABS: NavKey[] = ['events', 'leaflets', 'messages', 'people', 'me'];

/**
 * The tab last visited, so Activity (opened from the bell, not a tab of its
 * own) keeps it selected on a phone and goes back to it. Only the browser
 * remembers: a server render starts from Events, as a fresh load does.
 */
const lastTab = ref<NavKey>('events');

export function rememberTab(url: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    const tab = TABS.find((key) => inSection(url, key));

    if (tab) {
        lastTab.value = tab;
    }
}

/**
 * The app's sections with their live badges, for the tab bar, rail and
 * sidebar alike. The phone tab bar leaves Activity out (it is the bell in
 * the top bar); the rail and sidebar show it as an item.
 */
export function useNavigation() {
    const { t } = useI18n();
    const page = usePage();
    const { badges } = useBadges();

    const items = computed<NavItem[]>(() => {
        const url = page.url;

        return [
            {
                key: 'events',
                label: t('nav.events'),
                href: eventsIndex().url,
                icon: Calendar,
                badge: 0,
            },
            {
                key: 'leaflets',
                label: t('nav.leaflets'),
                href: leafletsIndex().url,
                icon: Newspaper,
                badge: 0,
            },
            {
                key: 'messages',
                label: t('nav.messages'),
                href: messagesIndex().url,
                icon: MessageCircle,
                badge: badges.value.messages,
                badgeLabel: t('shell.unreadChats', badges.value.messages),
            },
            {
                key: 'people',
                label: t('nav.contacts'),
                href: contactsIndex().url,
                icon: Users,
                badge: badges.value.requests,
                badgeLabel: t('shell.pendingRequests', badges.value.requests),
            },
            {
                key: 'activity',
                label: t('nav.activity'),
                href: notificationsIndex().url,
                icon: Bell,
                badge: badges.value.activity,
                badgeLabel: t('shell.unreadActivity', badges.value.activity),
            },
            {
                key: 'me',
                label: t('nav.me'),
                href: settingsIndex().url,
                icon: null,
                badge: 0,
            },
        ].map((item) => ({
            ...item,
            key: item.key as NavKey,
            active: inSection(url, item.key as NavKey),
        }));
    });

    const activity = computed(() =>
        items.value.find((item) => item.key === 'activity')!,
    );

    /** The phone's tab bar: Activity is the bell, so it keeps the last tab lit. */
    const tabItems = computed(() => {
        const onActivity = inSection(page.url, 'activity');

        return items.value
            .filter((item) => item.key !== 'activity')
            .map((item) =>
                onActivity
                    ? { ...item, active: item.key === lastTab.value }
                    : item,
            );
    });

    const previousTab = computed(() =>
        items.value.find((item) => item.key === lastTab.value)!,
    );

    return { items, tabItems, activity, previousTab, badges };
}
