<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import { useDates } from '@/lib/dates';
import { show as usersShow } from '@/routes/users';
import type { Person } from './people';

/**
 * Someone waiting for an answer: "Camilla wants to be your friend" or
 * "Sara asked to follow you", with friends in common and when, and Accept
 * (or Approve) and Decline. On a phone the buttons go under the text.
 */
const props = withDefaults(
    defineProps<{
        person: Person;
        kind: 'friend' | 'follow';
        createdAt: string | null;
        mutualCount?: number | null;
        busy?: boolean;
    }>(),
    { mutualCount: null, busy: false },
);

const emit = defineEmits<{ accept: []; decline: [] }>();

const { t } = useI18n();
const dates = useDates();

const sub = computed(() =>
    [
        props.mutualCount ? t('people.mutual', props.mutualCount) : null,
        props.createdAt ? dates.ago(props.createdAt) : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <div
        class="group/row relative flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:gap-4"
    >
        <span
            class="pointer-events-none absolute top-0 right-0 left-[74px] h-px bg-line group-first/row:hidden"
            aria-hidden="true"
        />
        <div class="flex min-w-0 flex-1 items-center gap-3.5">
            <Avatar
                :name="props.person.name"
                :src="props.person.avatar_url"
                :size="44"
                decorative
            />
            <div class="min-w-0">
                <p class="text-body text-ink">
                    <i18n-t
                        :keypath="
                            props.kind === 'friend'
                                ? 'people.requests.wantsToBeFriends'
                                : 'people.requests.askedToFollow'
                        "
                        scope="global"
                    >
                        <template #name>
                            <Link
                                :href="usersShow(props.person.id).url"
                                class="font-semibold hover:underline"
                                >{{ props.person.name }}</Link
                            >
                        </template>
                    </i18n-t>
                </p>
                <p v-if="sub" class="mt-0.5 text-meta text-ink-muted">
                    {{ sub }}
                </p>
            </div>
        </div>
        <div class="flex shrink-0 gap-2 pl-[58px] sm:pl-0">
            <Button
                variant="primary"
                size="sm"
                :disabled="props.busy"
                @click="emit('accept')"
            >
                {{
                    props.kind === 'friend'
                        ? t('people.requests.accept')
                        : t('people.requests.approve')
                }}
            </Button>
            <Button size="sm" :disabled="props.busy" @click="emit('decline')">
                {{ t('people.requests.decline') }}
            </Button>
        </div>
    </div>
</template>
