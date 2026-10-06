<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    Crown,
    Ellipsis,
    LogOut,
    Pencil,
    Plus,
    UserMinus,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import DateBadge from '@/components/ui/DateBadge.vue';
import ListRow from '@/components/ui/ListRow.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import MenuSeparator from '@/components/ui/MenuSeparator.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import Tag from '@/components/ui/Tag.vue';
import { confirmAction } from '@/composables/useConfirm';
import type { AttendanceStatus, EventCover, OrganizerSummary } from '@/events';
import { useDates } from '@/lib/dates';
import { toast, toastError } from '@/lib/toast';
import {
    create as eventsCreate,
    index as eventsIndex,
    show as eventsShow,
} from '@/routes/events';
import { edit as organizersEdit } from '@/routes/organizers';
import {
    destroy as unfollowOrganizer,
    store as followOrganizer,
} from '@/routes/organizers/follow';
import {
    destroy as removeSpokesperson,
    store as inviteSpokesperson,
    update as updateSpokesperson,
} from '@/routes/organizers/spokespersons';
import { show as usersShow } from '@/routes/users';

/**
 * An organizer's page: who it is, what it has coming up, who speaks for
 * it, and the viewer's standing with it (follower, spokesperson, invited).
 */
type Person = {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
};

const props = defineProps<{
    organizer: OrganizerSummary & {
        description: string | null;
        followers_count: number;
    };
    is_following: boolean;
    my_role: 'main' | 'spokesperson' | null;
    my_invitation_id: number | null;
    my_listed: boolean | null;
    can_update: boolean;
    can_delete: boolean;
    spokespersons: {
        id: number;
        user: Person;
        role: 'main' | 'spokesperson';
        listed: boolean;
        pending: boolean;
    }[];
    inviteCandidates: Person[];
    events: {
        id: number;
        name: string;
        starts_at: string;
        ends_at: string | null;
        place: string | null;
        city: string | null;
        cover: EventCover | null;
        my_status: AttendanceStatus | null;
        is_host: boolean;
    }[];
}>();

const { t } = useI18n();
const dates = useDates();
const page = usePage();

const me = computed(() => page.props.auth.user);
const isSpokesperson = computed(() => props.my_role !== null);
const isMain = computed(() => props.my_role === 'main');
const busy = ref(false);

const options = {
    preserveScroll: true,
    onStart: () => {
        busy.value = true;
    },
    onFinish: () => {
        busy.value = false;
    },
    onError: () => toastError(t('ui.somethingWentWrong')),
};

function toggleFollow(): void {
    if (props.is_following) {
        router.delete(unfollowOrganizer(props.organizer.id).url, {
            ...options,
            onSuccess: () =>
                toast({
                    title: t('organizers.page.unfollowed', {
                        name: props.organizer.name,
                    }),
                }),
        });
    } else {
        router.post(
            followOrganizer(props.organizer.id).url,
            {},
            {
                ...options,
                onSuccess: () =>
                    toast({
                        title: t('organizers.page.followed', {
                            name: props.organizer.name,
                        }),
                        icon: Check,
                    }),
            },
        );
    }
}

function answerInvitation(action: 'accept' | 'decline'): void {
    router.patch(
        updateSpokesperson([props.organizer.id, me.value.id]).url,
        { action },
        {
            ...options,
            onSuccess: () => {
                if (action === 'accept') {
                    toast({
                        title: t('notifications.savedSpokesperson', {
                            organizer: props.organizer.name,
                        }),
                        icon: Check,
                    });
                }
            },
        },
    );
}

function setListed(listed: boolean): void {
    router.patch(
        updateSpokesperson([props.organizer.id, me.value.id]).url,
        { action: 'accept', listed },
        options,
    );
}

// Inviting: a dialog with the main spokesperson's friends not yet in.
const inviteOpen = ref(false);

function invite(person: Person): void {
    router.post(
        inviteSpokesperson(props.organizer.id).url,
        { user_id: person.id },
        {
            ...options,
            onSuccess: () => {
                inviteOpen.value = false;
                toast({
                    title: t('organizers.page.inviteSent', {
                        name: person.name,
                    }),
                    icon: UserPlus,
                });
            },
        },
    );
}

async function makeMain(person: Person): Promise<void> {
    const confirmed = await confirmAction({
        title: t('organizers.page.makeMainConfirmTitle', { name: person.name }),
        message: t('organizers.page.makeMainConfirm'),
        confirmLabel: t('organizers.page.makeMain'),
        tone: 'primary',
    });

    if (confirmed) {
        router.patch(
            updateSpokesperson([props.organizer.id, person.id]).url,
            { action: 'make_main' },
            options,
        );
    }
}

async function remove(person: Person): Promise<void> {
    const confirmed = await confirmAction({
        title: t('organizers.page.removeConfirmTitle', { name: person.name }),
        message: t('organizers.page.removeConfirm', {
            organizer: props.organizer.name,
        }),
        confirmLabel: t('organizers.page.remove'),
    });

    if (confirmed) {
        router.delete(
            removeSpokesperson([props.organizer.id, person.id]).url,
            options,
        );
    }
}

async function leave(): Promise<void> {
    if (isMain.value) {
        toastError(t('organizers.page.handOverFirst'));

        return;
    }

    const confirmed = await confirmAction({
        title: t('organizers.page.leaveConfirmTitle', {
            organizer: props.organizer.name,
        }),
        message: t('organizers.page.leaveConfirm'),
        confirmLabel: t('organizers.page.leave'),
    });

    if (confirmed) {
        router.delete(
            removeSpokesperson([props.organizer.id, me.value.id]).url,
            {
                ...options,
                onSuccess: () => router.visit(eventsIndex().url),
            },
        );
    }
}

const eventStatus = (
    status: AttendanceStatus | null,
    isHost: boolean,
): string | null => {
    if (isHost) {
        return t('events.index.hosting');
    }

    if (status === 'attending') {
        return t('events.rsvp.going');
    }

    if (status === 'interested') {
        return t('events.rsvp.maybe');
    }

    return null;
};
</script>

<template>
    <Head :title="props.organizer.name" />
    <PageHeader
        :title="props.organizer.name"
        :back="eventsIndex().url"
        :back-label="t('organizers.page.back')"
        header-hidden
    />

    <PageContainer class="pt-6 pb-16 sm:pt-7">
        <Link
            :href="eventsIndex().url"
            class="mb-5 -ml-3 hidden h-9 items-center gap-1.5 rounded-full px-3 text-label font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink sm:inline-flex"
        >
            <ArrowLeft :size="16" :stroke-width="2" aria-hidden="true" />
            {{ t('organizers.page.back') }}
        </Link>

        <section
            class="flex flex-col items-center text-center sm:flex-row sm:items-start sm:gap-7 sm:text-left"
        >
            <Avatar
                :name="props.organizer.name"
                :src="props.organizer.avatar_url"
                :size="104"
            />
            <div class="mt-4 min-w-0 flex-1 sm:mt-1">
                <h1
                    class="font-display text-title-1 text-pretty lg:text-display"
                >
                    {{ props.organizer.name }}
                </h1>
                <p class="mt-1 text-meta text-ink-muted">
                    {{ '@' + props.organizer.handle }} ·
                    {{
                        t(
                            'organizers.page.followers',
                            props.organizer.followers_count,
                        )
                    }}
                </p>
                <p
                    v-if="props.organizer.description"
                    class="mt-3 font-display text-reading whitespace-pre-line text-ink"
                >
                    {{ props.organizer.description }}
                </p>

                <div
                    class="mt-5 flex flex-wrap justify-center gap-2 sm:justify-start"
                >
                    <Button
                        v-if="!isSpokesperson"
                        :variant="props.is_following ? 'secondary' : 'primary'"
                        :icon="props.is_following ? Check : undefined"
                        :disabled="busy"
                        @click="toggleFollow"
                    >
                        {{
                            props.is_following
                                ? t('organizers.page.following')
                                : t('organizers.page.follow')
                        }}
                    </Button>
                    <Button
                        v-if="isSpokesperson"
                        :as="Link"
                        :href="eventsCreate().url"
                        :icon="Plus"
                    >
                        {{ t('organizers.page.newEvent') }}
                    </Button>
                    <Button
                        v-if="props.can_update"
                        :as="Link"
                        :href="organizersEdit(props.organizer.id).url"
                        :icon="Pencil"
                    >
                        {{ t('organizers.page.edit') }}
                    </Button>
                    <Menu v-if="isSpokesperson" align="start">
                        <template #trigger>
                            <Button :icon="Ellipsis" :aria-label="t('ui.more')">
                                <ChevronDown
                                    :size="16"
                                    :stroke-width="2"
                                    aria-hidden="true"
                                />
                            </Button>
                        </template>
                        <MenuItem
                            v-if="isMain"
                            :icon="UserPlus"
                            @select="inviteOpen = true"
                        >
                            {{ t('organizers.page.invite') }}
                        </MenuItem>
                        <MenuSeparator v-if="isMain" />
                        <MenuItem :icon="LogOut" danger @select="leave">
                            {{ t('organizers.page.leave') }}
                        </MenuItem>
                    </Menu>
                </div>
            </div>
        </section>

        <!-- Invited: answer here, knowing what it means. -->
        <section
            v-if="props.my_invitation_id !== null"
            class="mt-8 rounded-lg border border-accent-line bg-accent-soft p-5"
        >
            <p class="font-semibold text-ink">
                {{
                    t('organizers.page.invitation', {
                        name:
                            props.spokespersons.find((s) => s.role === 'main')
                                ?.user.name ?? t('notifications.someone'),
                        organizer: props.organizer.name,
                    })
                }}
            </p>
            <p class="mt-1 text-meta text-ink-muted">
                {{
                    t('notifications.spokespersonTerms', {
                        organizer: props.organizer.name,
                    })
                }}
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Button
                    variant="primary"
                    size="sm"
                    :disabled="busy"
                    @click="answerInvitation('accept')"
                >
                    {{ t('organizers.page.accept') }}
                </Button>
                <Button
                    size="sm"
                    :disabled="busy"
                    @click="answerInvitation('decline')"
                >
                    {{ t('organizers.page.decline') }}
                </Button>
            </div>
        </section>

        <SectionHeader :title="t('organizers.page.comingUp')" />
        <ul
            v-if="props.events.length"
            class="overflow-hidden rounded-lg border border-line bg-surface"
        >
            <li
                v-for="event in props.events"
                :key="event.id"
                class="group/row relative"
            >
                <span
                    class="pointer-events-none absolute top-0 right-0 left-[76px] h-px bg-line group-first/row:hidden"
                    aria-hidden="true"
                />
                <Link
                    :href="eventsShow(event.id).url"
                    class="flex items-center gap-4 px-4 py-3 transition-colors duration-120 hover:bg-surface-2/55"
                >
                    <DateBadge :date="event.starts_at" size="sm" />
                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-2 font-semibold text-ink">{{
                            event.name
                        }}</span>
                        <span class="block truncate text-meta text-ink-muted">{{
                            [
                                dates.span(event.starts_at, event.ends_at),
                                event.place ?? event.city,
                            ]
                                .filter(Boolean)
                                .join(' · ')
                        }}</span>
                    </span>
                    <Tag
                        v-if="eventStatus(event.my_status, event.is_host)"
                        :tone="
                            event.my_status === 'attending' && !event.is_host
                                ? 'going'
                                : 'host'
                        "
                    >
                        {{ eventStatus(event.my_status, event.is_host) }}
                    </Tag>
                </Link>
            </li>
        </ul>
        <p v-else class="text-meta text-ink-muted">
            {{ t('organizers.page.noEvents') }}
        </p>

        <template v-if="props.spokespersons.length">
            <SectionHeader
                :title="t('organizers.page.spokespersons')"
                :meta="String(props.spokespersons.length)"
            />
            <div
                class="overflow-hidden rounded-lg border border-line bg-surface"
            >
                <ListRow
                    v-for="member in props.spokespersons"
                    :key="member.id"
                    :as="Link"
                    :href="usersShow(member.user.id).url"
                    interactive
                    :inset="74"
                >
                    <template #leading>
                        <Avatar
                            :name="member.user.name"
                            :src="member.user.avatar_url"
                            :size="44"
                            decorative
                        />
                    </template>
                    <template #title>
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="truncate">{{ member.user.name }}</span>
                            <Tag
                                v-if="member.role === 'main'"
                                tone="host"
                                :icon="Crown"
                                >{{ t('organizers.role.main') }}</Tag
                            >
                            <Tag v-else-if="member.pending" tone="host">{{
                                t('organizers.pending')
                            }}</Tag>
                        </span>
                    </template>
                    <template #sub>{{ '@' + member.user.username }}</template>
                    <template
                        v-if="isMain && member.user.id !== me.id"
                        #trailing
                    >
                        <Menu align="end">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="relative z-10 grid size-11 place-items-center rounded-full text-ink-muted hover:bg-surface-2 hover:text-ink"
                                    :aria-label="t('ui.more')"
                                    @click.prevent
                                >
                                    <Ellipsis
                                        :size="20"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </button>
                            </template>
                            <MenuItem
                                v-if="!member.pending"
                                :icon="Crown"
                                @select="makeMain(member.user)"
                            >
                                {{ t('organizers.page.makeMain') }}
                            </MenuItem>
                            <MenuItem
                                :icon="UserMinus"
                                danger
                                @select="remove(member.user)"
                            >
                                {{ t('organizers.page.remove') }}
                            </MenuItem>
                        </Menu>
                    </template>
                </ListRow>
            </div>
            <div
                v-if="isSpokesperson && props.my_listed !== null"
                class="mt-4 overflow-hidden rounded-lg border border-line bg-surface"
            >
                <SwitchRow
                    :model-value="props.my_listed"
                    :title="t('organizers.page.listed')"
                    :description="t('organizers.page.listedHint')"
                    :disabled="busy"
                    @update:model-value="setListed"
                />
            </div>
        </template>

        <AppDialog
            v-model:open="inviteOpen"
            :title="t('organizers.page.invite')"
        >
            <p class="text-meta text-ink-muted">
                {{
                    t('organizers.page.inviteLede', {
                        terms: t('notifications.spokespersonTerms', {
                            organizer: props.organizer.name,
                        }),
                    })
                }}
            </p>
            <div
                v-if="props.inviteCandidates.length"
                class="mt-4 overflow-hidden rounded-lg border border-line"
            >
                <ListRow
                    v-for="person in props.inviteCandidates"
                    :key="person.id"
                    :title="person.name"
                    :sub="'@' + person.username"
                    :inset="74"
                >
                    <template #leading>
                        <Avatar
                            :name="person.name"
                            :src="person.avatar_url"
                            :size="44"
                            decorative
                        />
                    </template>
                    <template #trailing>
                        <Button
                            size="sm"
                            :icon="UserPlus"
                            :disabled="busy"
                            @click="invite(person)"
                        >
                            {{ t('organizers.page.invite') }}
                        </Button>
                    </template>
                </ListRow>
            </div>
            <p v-else class="mt-4 text-meta text-ink-muted">
                {{ t('organizers.page.noCandidates') }}
            </p>
        </AppDialog>
    </PageContainer>
</template>
