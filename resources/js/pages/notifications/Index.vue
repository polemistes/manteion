<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { read } from '@/routes/notifications';

interface NotificationItem {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

defineProps<{
    notifications: NotificationItem[];
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

function message(notification: NotificationItem): string {
    if (notification.type === 'InvitedToEvent') {
        return t('notifications.invitedToEvent', {
            name: notification.data.invited_by_name,
            event: notification.data.event_name,
        });
    }

    if (notification.type === 'FollowedUserPublishedEvent') {
        return t('notifications.followedUserPublishedEvent', {
            name: notification.data.owner_name,
            event: notification.data.event_name,
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
            :class="notification.read_at ? '' : 'font-bold'"
        >
            <Link
                :href="`/events/${notification.data.event_id}`"
                class="flex-1 underline"
            >
                {{ message(notification) }}
            </Link>
            <span class="shrink-0 text-xs text-ink/70">{{
                formatDate(notification.created_at)
            }}</span>
            <Link
                v-if="!notification.read_at"
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
