<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PersonSummary from '@/components/PersonSummary.vue';
import * as follows from '@/routes/follows';
import * as friendships from '@/routes/friendships';

const { t } = useI18n();

defineProps<{
    result: {
        id: number;
        name: string;
        username: string;
        avatar_url: string | null;
        friend_status: 'none' | 'request_sent' | 'request_received' | 'friends';
        friendship_id: number | null;
        follow_status: 'none' | 'pending' | 'following';
        follow_id: number | null;
    };
}>();
</script>

<template>
    <li
        class="flex flex-col gap-2 border-frame border-edge px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between sm:gap-3"
    >
        <PersonSummary
            :id="result.id"
            :name="result.name"
            :username="result.username"
            :avatar_url="result.avatar_url"
        />

        <span class="flex flex-wrap gap-2 sm:shrink-0">
            <Link
                v-if="result.friend_status === 'none'"
                :href="friendships.store().url"
                method="post"
                :data="{ user_id: result.id }"
                as="button"
                preserve-scroll
                class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
            >
                {{ t('contacts.index.addFriend') }}
            </Link>
            <span
                v-else-if="result.friend_status === 'request_sent'"
                class="flex items-center gap-2"
            >
                <span class="text-xs text-ink/70">{{
                    t('contacts.index.requestSent')
                }}</span>
                <Link
                    :href="friendships.destroy(result.friendship_id!).url"
                    method="delete"
                    as="button"
                    preserve-scroll
                    class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                >
                    {{ t('contacts.index.cancel') }}
                </Link>
            </span>
            <span
                v-else-if="result.friend_status === 'request_received'"
                class="flex items-center gap-2"
            >
                <Link
                    :href="friendships.update(result.friendship_id!).url"
                    method="patch"
                    :data="{ action: 'accept' }"
                    as="button"
                    preserve-scroll
                    class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                >
                    {{ t('contacts.index.accept') }}
                </Link>
                <Link
                    :href="friendships.update(result.friendship_id!).url"
                    method="patch"
                    :data="{ action: 'decline' }"
                    as="button"
                    preserve-scroll
                    class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                >
                    {{ t('contacts.index.decline') }}
                </Link>
            </span>
            <span v-else class="text-xs font-bold">{{
                t('contacts.index.friends')
            }}</span>

            <Link
                v-if="result.follow_status === 'none'"
                :href="follows.store().url"
                method="post"
                :data="{ user_id: result.id }"
                as="button"
                preserve-scroll
                class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
            >
                {{ t('contacts.index.follow') }}
            </Link>
            <span
                v-else-if="result.follow_status === 'pending'"
                class="flex items-center gap-2"
            >
                <span class="text-xs text-ink/70">{{
                    t('contacts.index.requested')
                }}</span>
                <Link
                    :href="follows.destroy(result.follow_id!).url"
                    method="delete"
                    as="button"
                    preserve-scroll
                    class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                >
                    {{ t('contacts.index.cancel') }}
                </Link>
            </span>
            <span v-else class="flex items-center gap-2">
                <span class="text-xs font-bold">{{
                    t('contacts.index.following')
                }}</span>
                <Link
                    :href="follows.destroy(result.follow_id!).url"
                    method="delete"
                    as="button"
                    preserve-scroll
                    class="border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                >
                    {{ t('contacts.index.unfollow') }}
                </Link>
            </span>
        </span>
    </li>
</template>
