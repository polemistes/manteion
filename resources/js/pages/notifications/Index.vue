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

    <h1 class="text-xl mb-6 font-bold">{{ t('notifications.title') }}</h1>

    <p v-if="!notifications.length" class="text-sm text-ink/70">
        {{ t('notifications.empty') }}
    </p>

    <ul class="flex flex-col gap-2">
        <li
            v-for="notification in notifications"
            :key="notification.id"
            class="border-frame border-edge text-sm flex items-center justify-between gap-4 px-3 py-2"
            :class="notification.unread ? 'font-bold' : ''"
        >
            <Link :href="notification.url" class="flex-1 underline">
                {{ message(notification) }}
            </Link>
            <span class="text-xs shrink-0 text-ink/70">{{
                formatDate(notification.created_at)
            }}</span>
            <Link
                v-if="notification.unread"
                :href="read(notification.id).url"
                method="post"
                as="button"
                class="border-edge text-xs shrink-0 border px-2 py-1 hover:bg-accent hover:text-on-accent"
            >
                {{ t('notifications.markRead') }}
            </Link>
        </li>
    </ul>
</template>
