<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BellOff, MessageCircle, SquarePen } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import CountBadge from '@/components/ui/CountBadge.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import GroupAvatar from '@/components/ui/GroupAvatar.vue';
import SearchField from '@/components/ui/SearchField.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';
import { useDates } from '@/lib/dates';
import { create, show } from '@/routes/messages';
import type { ConversationListItem } from '@/types';
import PushPrompt from './PushPrompt.vue';

/**
 * The list of conversations: search, All / Unread / Groups, and 72px rows
 * (face, name, one-line preview, time; unread rows are bold with a count).
 * It scrolls inside itself; `tabBar` leaves room for the phone tab bar.
 */
const props = withDefaults(
    defineProps<{
        conversations: ConversationListItem[];
        activeId?: number | null;
        tabBar?: boolean;
        heading?: boolean;
    }>(),
    { activeId: null, tabBar: false, heading: true },
);

const { t } = useI18n();
const { listTime } = useDates();

const query = ref('');
const filter = ref('all');

const shown = computed(() => {
    const term = query.value.trim().toLocaleLowerCase();

    return props.conversations.filter((conversation) => {
        if (filter.value === 'unread' && !conversation.unread) {
            return false;
        }

        if (filter.value === 'groups' && conversation.type !== 'group') {
            return false;
        }

        return (
            term === '' ||
            conversation.title.toLocaleLowerCase().includes(term) ||
            (conversation.last_message?.body ?? '')
                .toLocaleLowerCase()
                .includes(term)
        );
    });
});

function preview(conversation: ConversationListItem): string {
    const last = conversation.last_message;

    if (!last) {
        return t('messages.index.noMessagesYet');
    }

    if (last.is_deleted) {
        return t('messages.index.deleted');
    }

    if (last.is_mine) {
        return `${t('messages.index.you')}${last.body ?? ''}`;
    }

    return conversation.type === 'group'
        ? `${last.sender_name}: ${last.body ?? ''}`
        : (last.body ?? '');
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div
            v-if="props.heading"
            class="hidden items-center justify-between gap-3 px-5 pt-7 pb-1 sm:flex"
        >
            <h1 class="font-display text-title-1">
                {{ t('messages.index.title') }}
            </h1>
            <Button :as="Link" :href="create().url" :icon="SquarePen" size="sm">
                {{ t('messages.index.newMessage') }}
            </Button>
        </div>

        <div class="flex flex-col gap-3 px-4 pt-3 pb-3 sm:px-5">
            <SearchField v-model="query" :label="t('messages.index.search')" />
            <SegmentedControl
                v-model="filter"
                :label="t('messages.index.filter')"
                :segments="[
                    { value: 'all', label: t('messages.index.all') },
                    { value: 'unread', label: t('messages.index.unread') },
                    { value: 'groups', label: t('messages.index.groups') },
                ]"
                class="self-start"
            />
        </div>

        <div
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
            :class="
                props.tabBar
                    ? 'pb-[calc(var(--spacing-tabbar)+env(safe-area-inset-bottom)+16px)] sm:pb-4'
                    : 'pb-4'
            "
        >
            <PushPrompt class="mx-4 mb-3 sm:mx-5" />

            <EmptyState
                v-if="!props.conversations.length"
                :icon="MessageCircle"
                :title="t('messages.index.emptyTitle')"
                :body="t('messages.index.emptyBody')"
            >
                <Button
                    :as="Link"
                    :href="create().url"
                    variant="primary"
                    :icon="SquarePen"
                >
                    {{ t('messages.index.newMessage') }}
                </Button>
            </EmptyState>
            <p
                v-else-if="!shown.length"
                class="px-5 py-8 text-center text-ink-muted"
            >
                {{ t('messages.index.noMatches') }}
            </p>

            <ul v-else class="[--avatar-ring:var(--color-canvas)]">
                <li v-for="conversation in shown" :key="conversation.id">
                    <Link
                        :href="show(conversation.id).url"
                        :aria-current="
                            conversation.id === props.activeId
                                ? 'page'
                                : undefined
                        "
                        class="mx-2 flex min-h-[72px] items-center gap-3 rounded-md px-2 py-2.5 transition-colors duration-120 sm:mx-3"
                        :class="
                            conversation.id === props.activeId
                                ? 'bg-surface ring-1 ring-line [--avatar-ring:var(--color-surface)] ring-inset'
                                : 'hover:bg-surface-2/60'
                        "
                    >
                        <GroupAvatar
                            v-if="
                                conversation.type === 'group' &&
                                conversation.avatars.length > 1
                            "
                            :members="conversation.avatars"
                            :name="conversation.title"
                            :size="48"
                        />
                        <Avatar
                            v-else
                            :name="conversation.title"
                            :src="
                                conversation.avatar_url ??
                                conversation.avatars[0]?.avatar_url ??
                                null
                            "
                            :size="48"
                            decorative
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline gap-2">
                                <span
                                    class="min-w-0 flex-1 truncate text-body"
                                    :class="
                                        conversation.unread
                                            ? 'font-semibold text-ink'
                                            : 'font-medium text-ink'
                                    "
                                    >{{ conversation.title }}</span
                                >
                                <span
                                    v-if="conversation.last_message"
                                    class="shrink-0 text-meta"
                                    :class="
                                        conversation.unread
                                            ? 'font-semibold text-accent-ink'
                                            : 'text-ink-subtle'
                                    "
                                    >{{
                                        listTime(
                                            conversation.last_message
                                                .created_at,
                                        )
                                    }}</span
                                >
                            </span>
                            <span class="mt-0.5 flex items-center gap-2">
                                <span
                                    class="min-w-0 flex-1 truncate text-label"
                                    :class="[
                                        conversation.unread
                                            ? 'font-medium text-ink'
                                            : 'text-ink-muted',
                                        !conversation.last_message ||
                                        conversation.last_message.is_deleted
                                            ? 'italic'
                                            : '',
                                    ]"
                                    >{{ preview(conversation) }}</span
                                >
                                <BellOff
                                    v-if="conversation.muted"
                                    :size="15"
                                    :stroke-width="2"
                                    class="shrink-0 text-ink-subtle"
                                    :aria-label="t('messages.index.muted')"
                                />
                                <CountBadge
                                    :count="conversation.unread_count"
                                    :tone="
                                        conversation.muted
                                            ? 'neutral'
                                            : 'attention'
                                    "
                                    :label="
                                        t(
                                            'messages.index.unreadCount',
                                            conversation.unread_count,
                                        )
                                    "
                                />
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
