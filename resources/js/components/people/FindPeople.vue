<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Check, UserPlus } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import SearchField from '@/components/ui/SearchField.vue';
import { toast, toastError } from '@/lib/toast';
import { index as contactsIndex } from '@/routes/contacts';
import { destroy as unfollow, store as follow } from '@/routes/follows';
import {
    destroy as cancelFriendship,
    store as requestFriendship,
    update as answerFriendship,
} from '@/routes/friendships';
import { show as usersShow } from '@/routes/users';
import { PEOPLE_PROPS } from './people';
import type { FindResult } from './people';

/**
 * Finding someone new by name or @username: they get a friend request or
 * a follow. Only people who let themselves be found show up.
 */
const props = defineProps<{
    query: string;
    results: FindResult[];
}>();

const { t } = useI18n();

const term = ref(props.query);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(term, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            contactsIndex().url,
            value.trim() ? { find: value.trim() } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['findQuery', 'findResults'],
            },
        );
    }, 350);
});

onBeforeUnmount(() => clearTimeout(timer));

const busy = ref<number | null>(null);

function run(
    result: FindResult,
    visit: (options: Record<string, unknown>) => void,
    done: string,
): void {
    busy.value = result.id;
    visit({
        preserveScroll: true,
        preserveState: true,
        only: PEOPLE_PROPS,
        onSuccess: () => toast({ title: done, icon: Check }),
        onError: () => toastError(t('settings.saveFailed')),
        onFinish: () => {
            busy.value = null;
        },
    });
}

function friendAction(result: FindResult): void {
    if (result.friend_status === 'none') {
        run(
            result,
            (options) =>
                router.post(
                    requestFriendship().url,
                    { user_id: result.id },
                    options,
                ),
            t('people.find.requestSent', { name: result.name }),
        );
    } else if (
        result.friend_status === 'request_sent' &&
        result.friendship_id
    ) {
        const id = result.friendship_id;

        run(
            result,
            (options) => router.delete(cancelFriendship(id).url, options),
            t('people.find.requestCancelled'),
        );
    } else if (
        result.friend_status === 'request_received' &&
        result.friendship_id
    ) {
        const id = result.friendship_id;

        run(
            result,
            (options) =>
                router.patch(
                    answerFriendship(id).url,
                    { action: 'accept' },
                    options,
                ),
            t('notifications.savedAccepted', { name: result.name }),
        );
    }
}

function followAction(result: FindResult): void {
    if (result.follow_status === 'none') {
        run(
            result,
            (options) =>
                router.post(follow().url, { user_id: result.id }, options),
            t('people.find.followed', { name: result.name }),
        );
    } else if (result.follow_id) {
        const id = result.follow_id;

        run(
            result,
            (options) => router.delete(unfollow(id).url, options),
            result.follow_status === 'pending'
                ? t('people.find.requestCancelled')
                : t('people.find.unfollowed', { name: result.name }),
        );
    }
}

function friendLabel(result: FindResult): string {
    switch (result.friend_status) {
        case 'request_sent':
            return t('people.find.requested');
        case 'request_received':
            return t('people.requests.accept');
        case 'friends':
            return t('people.find.friends');
        default:
            return t('people.find.addFriend');
    }
}

function followLabel(result: FindResult): string {
    switch (result.follow_status) {
        case 'pending':
            return t('people.find.requested');
        case 'following':
            return t('people.find.following');
        default:
            return t('people.find.follow');
    }
}
</script>

<template>
    <div>
        <SearchField
            v-model="term"
            :label="t('people.find.label')"
            :placeholder="t('people.find.placeholder')"
        />

        <ul v-if="props.results.length" class="mt-3 flex flex-col">
            <li
                v-for="result in props.results"
                :key="result.id"
                class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-line py-3 first:border-t-0"
            >
                <Link
                    :href="usersShow(result.id).url"
                    class="flex min-w-0 flex-1 basis-48 items-center gap-3"
                >
                    <Avatar
                        :name="result.name"
                        :src="result.avatar_url"
                        :size="40"
                        decorative
                    />
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-ink">{{
                            result.name
                        }}</span>
                        <span class="block truncate text-meta text-ink-muted"
                            >{{ '@' + result.username
                            }}<template v-if="result.mutual_count">
                                ·
                                {{ t('people.mutual', result.mutual_count) }}
                            </template></span
                        >
                    </span>
                </Link>
                <div class="flex shrink-0 gap-2">
                    <Button
                        size="sm"
                        :variant="
                            result.friend_status === 'request_received'
                                ? 'primary'
                                : result.friend_status === 'none'
                                  ? 'secondary'
                                  : 'ghost'
                        "
                        :icon="
                            result.friend_status === 'none'
                                ? UserPlus
                                : undefined
                        "
                        :disabled="
                            busy === result.id ||
                            result.friend_status === 'friends'
                        "
                        @click="friendAction(result)"
                    >
                        {{ friendLabel(result) }}
                    </Button>
                    <Button
                        size="sm"
                        :variant="
                            result.follow_status === 'none'
                                ? 'secondary'
                                : 'ghost'
                        "
                        :disabled="busy === result.id"
                        @click="followAction(result)"
                    >
                        {{ followLabel(result) }}
                    </Button>
                </div>
            </li>
        </ul>
        <p v-else-if="props.query" class="mt-3 text-meta text-ink-muted">
            {{ t('people.find.none', { query: props.query }) }}
        </p>
    </div>
</template>
