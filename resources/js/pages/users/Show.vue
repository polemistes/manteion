<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/Avatar.vue';
import * as follows from '@/routes/follows';
import * as friendships from '@/routes/friendships';
import { store as startConversation } from '@/routes/messages';

const { t } = useI18n();

const props = defineProps<{
    profile: {
        id: number;
        name: string;
        username: string;
        bio: string | null;
        avatar_url: string | null;
    };
    is_self: boolean;
    friend_status: 'none' | 'request_sent' | 'request_received' | 'friends';
    friendship_id: number | null;
    follow_status: 'none' | 'pending' | 'following';
    follow_id: number | null;
}>();
</script>

<template>
    <Head :title="props.profile.name" />

    <div class="mb-6 flex items-start gap-4">
        <Avatar
            :url="props.profile.avatar_url"
            :name="props.profile.name"
            size="lg"
        />
        <div>
            <h1 class="text-xl font-bold">{{ props.profile.name }}</h1>
            <p class="text-sm text-black/70">@{{ props.profile.username }}</p>
        </div>
    </div>

    <p
        v-if="props.profile.bio"
        class="mb-6 max-w-prose font-sans text-sm whitespace-pre-line"
    >
        {{ props.profile.bio }}
    </p>
    <p v-else class="mb-6 text-sm text-black/70">
        {{ t('userProfile.noBio') }}
    </p>

    <div v-if="!props.is_self" class="flex flex-wrap gap-2">
        <Link
            v-if="props.friend_status === 'none'"
            :href="friendships.store().url"
            method="post"
            :data="{ user_id: props.profile.id }"
            as="button"
            preserve-scroll
            class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
        >
            {{ t('userProfile.addFriend') }}
        </Link>
        <span
            v-else-if="props.friend_status === 'request_sent'"
            class="flex items-center gap-2"
        >
            <span class="text-sm text-black/70">{{
                t('userProfile.friendRequestSent')
            }}</span>
            <Link
                :href="friendships.destroy(props.friendship_id!).url"
                method="delete"
                as="button"
                preserve-scroll
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('userProfile.cancel') }}
            </Link>
        </span>
        <span
            v-else-if="props.friend_status === 'request_received'"
            class="flex items-center gap-2"
        >
            <Link
                :href="friendships.update(props.friendship_id!).url"
                method="patch"
                :data="{ action: 'accept' }"
                as="button"
                preserve-scroll
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('userProfile.acceptFriendRequest') }}
            </Link>
            <Link
                :href="friendships.update(props.friendship_id!).url"
                method="patch"
                :data="{ action: 'decline' }"
                as="button"
                preserve-scroll
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('userProfile.decline') }}
            </Link>
        </span>
        <span v-else class="flex items-center gap-2">
            <span class="border border-black px-3 py-2 text-sm font-bold">{{
                t('userProfile.friends')
            }}</span>
            <Link
                :href="startConversation().url"
                method="post"
                :data="{ user_ids: [props.profile.id] }"
                as="button"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('userProfile.message') }}
            </Link>
        </span>

        <Link
            v-if="props.follow_status === 'none'"
            :href="follows.store().url"
            method="post"
            :data="{ user_id: props.profile.id }"
            as="button"
            preserve-scroll
            class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
        >
            {{ t('userProfile.follow') }}
        </Link>
        <span
            v-else-if="props.follow_status === 'pending'"
            class="flex items-center gap-2"
        >
            <span class="text-sm text-black/70">{{
                t('userProfile.followRequested')
            }}</span>
            <Link
                :href="follows.destroy(props.follow_id!).url"
                method="delete"
                as="button"
                preserve-scroll
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('userProfile.cancel') }}
            </Link>
        </span>
        <span v-else class="flex items-center gap-2">
            <span class="border border-black px-3 py-2 text-sm font-bold">{{
                t('userProfile.following')
            }}</span>
            <Link
                :href="follows.destroy(props.follow_id!).url"
                method="delete"
                as="button"
                preserve-scroll
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('userProfile.unfollow') }}
            </Link>
        </span>
    </div>
</template>
