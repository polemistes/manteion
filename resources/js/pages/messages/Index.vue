<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { MessageCircle, SquarePen } from '@lucide/vue';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ConversationList from '@/components/messages/ConversationList.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import IconButton from '@/components/ui/IconButton.vue';
import { onIncomingMessage, useBadges } from '@/realtime';
import { create } from '@/routes/messages';
import type { ConversationListItem } from '@/types';

defineProps<{
    conversations: ConversationListItem[];
}>();

const { t } = useI18n();
const { badges } = useBadges();

// New messages and reads elsewhere (another tab, the phone) reorder the list
// and change its counts; fold bursts into one reload.
let reloadTimer: ReturnType<typeof setTimeout> | undefined;

function reloadList(): void {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(
        () => router.reload({ only: ['conversations'] }),
        250,
    );
}

onIncomingMessage(reloadList);
watch(() => badges.value.messages, reloadList);
</script>

<template>
    <div class="flex h-dvh flex-col">
        <PageHeader
            :title="t('messages.index.title')"
            root
            header-hidden
            :alpha="false"
        >
            <template #phone-actions>
                <IconButton
                    :as="Link"
                    :href="create().url"
                    :icon="SquarePen"
                    :label="t('messages.index.newMessage')"
                />
            </template>
        </PageHeader>

        <div class="flex min-h-0 flex-1">
            <aside
                class="flex min-h-0 w-full flex-col md:w-[300px] md:shrink-0 md:border-r md:border-line lg:w-[360px]"
            >
                <ConversationList :conversations="conversations" tab-bar />
            </aside>
            <section
                class="hidden min-w-0 flex-1 items-center justify-center md:flex"
            >
                <EmptyState
                    :icon="MessageCircle"
                    :title="t('messages.index.pickTitle')"
                    :body="t('messages.index.pickBody')"
                />
            </section>
        </div>
    </div>
</template>
