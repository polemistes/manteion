import { Bell, Languages, Shield, SunMoon, User } from '@lucide/vue';
import type { Component } from 'vue';
import {
    account,
    appearance,
    language,
    notifications,
    privacy,
} from '@/routes/settings';

export type SettingsSection =
    'account' | 'privacy' | 'notifications' | 'appearance' | 'language';

/** The settings panels, in the order the nav lists them. */
export const sections: {
    key: SettingsSection;
    icon: Component;
    href: () => string;
}[] = [
    { key: 'account', icon: User, href: () => account().url },
    { key: 'privacy', icon: Shield, href: () => privacy().url },
    { key: 'notifications', icon: Bell, href: () => notifications().url },
    { key: 'appearance', icon: SunMoon, href: () => appearance().url },
    { key: 'language', icon: Languages, href: () => language().url },
];
