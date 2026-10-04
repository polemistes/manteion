<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { read } from '@/routes/notifications';
import type { ActivityItem } from '@/types';

defineProps<{
    notifications: ActivityItem[];
}>();

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}

function message(notification: ActivityItem): string {
    if (notification.type === 'InvitedToEvent') {
        return t('notifications.invitedToEvent', {
            name: notification.actor?.name ?? '',
            event: notification.subject_title,
        });
    }

    if (notification.type === 'FollowedUserPublishedEvent') {
        return t('notifications.followedUserPublishedEvent', {
            name: notification.actor?.name ?? '',
            event: notification.subject_title,
        });
    }

    return notification.type;
}
</script>

<template>
    <Head :title="t('notifications.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('notifications.title') }}</h1>

    <p v-if="!notifications.length" class="text-sm text-ink/70">
        {{ t('notifications.empty') }}
    </p>

    <ul class="flex flex-col gap-2">
        <li
            v-for="notification in notifications"
            :key="notification.id"
            class="flex items-center justify-between gap-4 border-frame border-edge px-3 py-2 text-sm"
            :class="notification.unread ? 'font-bold' : ''"
        >
            <Link :href="notification.url" class="flex-1 underline">
                {{ message(notification) }}
            </Link>
            <span class="shrink-0 text-xs text-ink/70">{{
                formatDate(notification.created_at)
            }}</span>
            <Link
                v-if="notification.unread"
                :href="read(notification.id).url"
                method="post"
                as="button"
                class="shrink-0 border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
            >
                {{ t('notifications.markRead') }}
            </Link>
        </li>
    </ul>
</template>
