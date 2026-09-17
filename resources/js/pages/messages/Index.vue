<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/Avatar.vue';
import { create, show } from '@/routes/messages';

const { t } = useI18n();

interface ConversationSummary {
    id: number;
    type: 'direct' | 'group';
    title: string;
    avatar_url: string | null;
    last_message: { body: string; is_mine: boolean } | null;
    unread: boolean;
}

defineProps<{
    conversations: ConversationSummary[];
}>();

usePoll(15000, { only: ['conversations', 'unreadMessagesCount'] });
</script>

<template>
    <Head :title="t('messages.index.title')" />

    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold">{{ t('messages.index.title') }}</h1>
        <Link
            :href="create().url"
            class="border border-edge bg-accent px-3 py-2 text-sm text-on-accent hover:bg-surface-raised hover:text-ink"
        >
            {{ t('messages.index.newMessage') }}
        </Link>
    </div>

    <p v-if="!conversations.length" class="text-sm text-ink/70">
        {{ t('messages.index.noConversations') }}
    </p>

    <ul class="flex flex-col gap-2">
        <li
            v-for="conversation in conversations"
            :key="conversation.id"
            class="border-frame border-edge"
        >
            <Link
                :href="show(conversation.id).url"
                class="flex items-center gap-3 px-3 py-3"
            >
                <Avatar
                    :url="conversation.avatar_url"
                    :name="conversation.title"
                    size="md"
                />
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-2">
                        <span
                            class="truncate text-sm"
                            :class="conversation.unread ? 'font-bold' : ''"
                        >
                            {{ conversation.title }}
                        </span>
                        <span
                            v-if="conversation.unread"
                            class="shrink-0 text-xs font-bold"
                            >●</span
                        >
                    </span>
                    <span
                        v-if="conversation.last_message"
                        class="block truncate font-sans text-xs text-ink/70"
                        :class="conversation.unread ? 'font-bold text-ink' : ''"
                    >
                        {{
                            conversation.last_message.is_mine
                                ? t('messages.index.you')
                                : ''
                        }}{{ conversation.last_message.body }}
                    </span>
                    <span v-else class="block text-xs text-ink/70">{{
                        t('messages.index.noMessagesYet')
                    }}</span>
                </span>
            </Link>
        </li>
    </ul>
</template>
