<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PersonSummary from '@/components/PersonSummary.vue';
import { search } from '@/routes/contacts';
import * as follows from '@/routes/follows';
import * as friendships from '@/routes/friendships';

const { t } = useI18n();

interface SearchResult {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
    friend_status: 'none' | 'request_sent' | 'request_received' | 'friends';
    friendship_id: number | null;
    follow_status: 'none' | 'pending' | 'following';
    follow_id: number | null;
}

const props = defineProps<{
    q: string;
    results: SearchResult[];
}>();

const query = ref(props.q);

function submit(): void {
    router.get(
        search().url,
        { q: query.value },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('contacts.search.title')" />

    <h1 class="mb-6 text-xl font-bold">{{ t('contacts.search.title') }}</h1>

    <form class="mb-6 flex gap-2" @submit.prevent="submit">
        <input
            v-model="query"
            type="text"
            :placeholder="t('contacts.search.placeholder')"
            autofocus
            class="flex-1 border border-black bg-white px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-black focus:outline-none"
        />
        <button
            type="submit"
            class="border border-black px-4 py-2 text-sm hover:bg-black hover:text-white"
        >
            {{ t('contacts.search.search') }}
        </button>
    </form>

    <p v-if="q && !results.length" class="text-sm text-black/70">
        {{ t('contacts.search.noResults', { query: q }) }}
    </p>

    <ul class="flex flex-col gap-2">
        <li
            v-for="result in results"
            :key="result.id"
            class="flex flex-col gap-2 border-[3px] border-black px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <PersonSummary
                :id="result.id"
                :name="result.name"
                :username="result.username"
                :avatar_url="result.avatar_url"
            />

            <span class="flex flex-wrap gap-2">
                <Link
                    v-if="result.friend_status === 'none'"
                    :href="friendships.store().url"
                    method="post"
                    :data="{ user_id: result.id }"
                    as="button"
                    preserve-scroll
                    class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                >
                    {{ t('contacts.search.addFriend') }}
                </Link>
                <span
                    v-else-if="result.friend_status === 'request_sent'"
                    class="flex items-center gap-2"
                >
                    <span class="text-xs text-black/70">{{
                        t('contacts.search.requestSent')
                    }}</span>
                    <Link
                        :href="friendships.destroy(result.friendship_id!).url"
                        method="delete"
                        as="button"
                        preserve-scroll
                        class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                    >
                        {{ t('contacts.search.cancel') }}
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
                        class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                    >
                        {{ t('contacts.search.accept') }}
                    </Link>
                    <Link
                        :href="friendships.update(result.friendship_id!).url"
                        method="patch"
                        :data="{ action: 'decline' }"
                        as="button"
                        preserve-scroll
                        class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                    >
                        {{ t('contacts.search.decline') }}
                    </Link>
                </span>
                <span v-else class="text-xs font-bold">{{
                    t('contacts.search.friends')
                }}</span>

                <Link
                    v-if="result.follow_status === 'none'"
                    :href="follows.store().url"
                    method="post"
                    :data="{ user_id: result.id }"
                    as="button"
                    preserve-scroll
                    class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                >
                    {{ t('contacts.search.follow') }}
                </Link>
                <span
                    v-else-if="result.follow_status === 'pending'"
                    class="flex items-center gap-2"
                >
                    <span class="text-xs text-black/70">{{
                        t('contacts.search.requested')
                    }}</span>
                    <Link
                        :href="follows.destroy(result.follow_id!).url"
                        method="delete"
                        as="button"
                        preserve-scroll
                        class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                    >
                        {{ t('contacts.search.cancel') }}
                    </Link>
                </span>
                <span v-else class="flex items-center gap-2">
                    <span class="text-xs font-bold">{{
                        t('contacts.search.following')
                    }}</span>
                    <Link
                        :href="follows.destroy(result.follow_id!).url"
                        method="delete"
                        as="button"
                        preserve-scroll
                        class="border border-black px-2 py-1 text-xs hover:bg-black hover:text-white"
                    >
                        {{ t('contacts.search.unfollow') }}
                    </Link>
                </span>
            </span>
        </li>
    </ul>
</template>
