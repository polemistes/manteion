<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/Avatar.vue';
import PersonSummary from '@/components/PersonSummary.vue';
import { confirmAction } from '@/composables/useConfirm';
import * as follows from '@/routes/follows';
import * as friendships from '@/routes/friendships';
import { store as startConversation } from '@/routes/messages';

interface ContactOption {
    id: number;
    name: string;
    username: string;
    avatar_url?: string | null;
}

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
    friends: ContactOption[];
}>();

const friendsOpen = ref(false);

async function unfriend(): Promise<void> {
    const confirmed = await confirmAction({
        message: t('userProfile.confirmUnfriend'),
    });

    if (!confirmed) {
        return;
    }

    router.delete(friendships.destroy(props.friendship_id!).url, {
        preserveScroll: true,
    });
}

function toggleFollow(): void {
    if (props.follow_status === 'following') {
        router.delete(follows.destroy(props.follow_id!).url, {
            preserveScroll: true,
        });
    } else {
        router.post(
            follows.store().url,
            { user_id: props.profile.id },
            { preserveScroll: true },
        );
    }
}
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

    <div class="grid w-fit gap-2">
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
            <button
                v-else
                type="button"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
                @click="unfriend"
            >
                {{ t('userProfile.unfriend') }}
            </button>

            <button
                v-if="
                    props.follow_status === 'none' ||
                    props.follow_status === 'following'
                "
                type="button"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
                @click="toggleFollow"
            >
                {{
                    props.follow_status === 'following'
                        ? t('userProfile.unfollow')
                        : t('userProfile.follow')
                }}
            </button>
            <span v-else class="flex items-center gap-2">
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

            <Link
                v-if="props.friend_status === 'friends'"
                :href="startConversation().url"
                method="post"
                :data="{ user_ids: [props.profile.id] }"
                as="button"
                class="border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('userProfile.message') }}
            </Link>
        </div>

        <button
            v-if="props.friends.length"
            type="button"
            class="w-full border border-black px-3 py-2 text-sm hover:bg-black hover:text-white"
            @click="friendsOpen = !friendsOpen"
        >
            {{
                friendsOpen
                    ? t('userProfile.hideFriends')
                    : t('userProfile.showFriends')
            }}
        </button>
    </div>

    <div v-if="props.friends.length && friendsOpen" class="mt-3">
        <h2 class="mb-2 text-sm font-bold uppercase">
            {{ t('userProfile.friendsHeading') }}
        </h2>
        <ul class="flex flex-col gap-2">
            <li v-for="friend in props.friends" :key="friend.id">
                <PersonSummary v-bind="friend" />
            </li>
        </ul>
    </div>
</template>
