<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';
import { computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import ReportDialog from '@/components/ReportDialog.vue';
import AlphaNotice from '@/components/shell/AlphaNotice.vue';
import { rememberTab, useNavigation } from '@/components/shell/useNavigation';
import StaffDeleteDialog from '@/components/StaffDeleteDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import CountBadge from '@/components/ui/CountBadge.vue';
import Mark from '@/components/ui/Mark.vue';
import Toaster from '@/components/ui/Toaster.vue';
import { startClock } from '@/lib/dates';
import { showMessageBanner } from '@/lib/toast';
import {
    onIncomingMessage,
    playMessageSound,
    viewedConversationId,
} from '@/realtime';
import { index as eventsIndex } from '@/routes/events';
import { show as messagesShow } from '@/routes/messages';
import { index as settingsIndex } from '@/routes/settings';

const { t } = useI18n();
const page = usePage();
const { items, tabItems } = useNavigation();

watch(() => page.url, rememberTab, { immediate: true });

// Relative times follow the browser's clock from here on (see lib/dates).
onMounted(startClock);

const user = computed(() => page.props.auth.user);

/** Screens that bring their own bottom bar (composer, answer bar, Create). */
const ownBottomBar: Record<string, string> = {
    'messages/Show': 'calc(72px + env(safe-area-inset-bottom))',
    'messages/Create': 'calc(16px + env(safe-area-inset-bottom))',
    'events/Show': 'calc(136px + env(safe-area-inset-bottom))',
    'events/Create': 'calc(100px + env(safe-area-inset-bottom))',
    'events/Edit': 'calc(100px + env(safe-area-inset-bottom))',
    'leaflets/Edit': 'calc(16px + env(safe-area-inset-bottom))',
};

const hasTabBar = computed(() => !(page.component in ownBottomBar));

/** Pages that fill the viewport and scroll inside themselves (the list, the thread). */
const fullHeight = computed(
    () =>
        page.component === 'messages/Index' ||
        page.component === 'messages/Show',
);

const toastBottom = computed(
    () =>
        ownBottomBar[page.component] ??
        'calc(var(--spacing-tabbar) + env(safe-area-inset-bottom) + 12px)',
);

const meItem = computed(() => items.value.find((item) => item.key === 'me')!);

const railItems = computed(() =>
    items.value.filter((item) => item.key !== 'me'),
);

// Someone wrote: a banner and the message sound, unless that chat is open.
onIncomingMessage((message) => {
    if (viewedConversationId.value === message.conversation_id) {
        return;
    }

    const previews =
        (
            user.value.notification_settings as
                { message_previews?: boolean } | null | undefined
        )?.message_previews ?? true;
    const isGroup = message.conversation_type === 'group';

    showMessageBanner({
        conversationId: message.conversation_id,
        name: isGroup ? message.conversation_title : message.sender.name,
        avatarUrl: message.sender.avatar_url,
        preview: previews
            ? isGroup
                ? `${message.sender.name}: ${message.preview}`
                : message.preview
            : t('shell.newMessage'),
        href: messagesShow(message.conversation_id).url,
    });

    playMessageSound();
});
</script>

<template>
    <div
        class="min-h-dvh bg-canvas text-ink"
        :style="{ '--toast-bottom': toastBottom }"
    >
        <a
            href="#main"
            class="sr-only z-[70] rounded-full bg-surface px-4 py-2 font-semibold shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
        >
            {{ t('ui.skipToContent') }}
        </a>

        <ImpersonationBanner v-if="page.props.impersonating" />

        <div class="sm:flex">
            <!-- Tablet: a 104px rail of icons with labels under them. -->
            <nav
                :aria-label="t('ui.mainNavigation')"
                class="sticky top-0 hidden h-dvh w-rail shrink-0 flex-col items-center border-r border-line bg-canvas px-1 pt-[18px] pb-4 sm:flex lg:hidden"
            >
                <Link
                    :href="eventsIndex().url"
                    :aria-label="t('nav.home')"
                    class="mb-5 grid size-11 place-items-center rounded-full"
                >
                    <Mark class="h-[30px] w-5 text-mark" />
                </Link>
                <div class="flex flex-col items-center gap-1">
                    <Link
                        v-for="item in railItems"
                        :key="item.key"
                        :href="item.href"
                        :aria-current="item.active ? 'page' : undefined"
                        class="group flex w-24 flex-col items-center gap-1 rounded-md py-1.5"
                    >
                        <span
                            class="relative grid h-8 w-14 place-items-center rounded-full transition-colors duration-200"
                            :class="
                                item.active
                                    ? 'bg-accent-soft text-accent-ink'
                                    : 'text-ink-muted group-hover:bg-surface-2'
                            "
                        >
                            <component
                                :is="item.icon"
                                :size="22"
                                :stroke-width="1.75"
                                aria-hidden="true"
                            />
                            <CountBadge
                                :count="item.badge"
                                :label="item.badgeLabel"
                                ring
                                class="absolute -top-[3px] left-8"
                            />
                        </span>
                        <span
                            class="text-caption"
                            :class="
                                item.active
                                    ? 'font-semibold text-ink'
                                    : 'font-medium text-ink-muted'
                            "
                            >{{ item.label }}</span
                        >
                    </Link>
                </div>
                <Link
                    :href="settingsIndex().url"
                    :aria-label="t('shell.yourProfile')"
                    :aria-current="meItem.active ? 'page' : undefined"
                    class="mt-auto grid size-11 place-items-center rounded-full"
                >
                    <Avatar
                        :name="user.name"
                        :src="user.avatar_url"
                        :size="36"
                        decorative
                        :class="
                            meItem.active
                                ? 'ring-2 ring-accent ring-offset-2 ring-offset-canvas'
                                : ''
                        "
                    />
                </Link>
            </nav>

            <!-- Desktop: a 256px sidebar with the wordmark and full labels. -->
            <nav
                :aria-label="t('ui.mainNavigation')"
                class="sticky top-0 hidden h-dvh w-sidebar shrink-0 flex-col border-r border-line bg-canvas px-4 pt-[22px] pb-4 lg:flex"
            >
                <Link
                    :href="eventsIndex().url"
                    :aria-label="t('nav.home')"
                    class="mb-6.5 flex items-center gap-2.5 px-2.5 py-0.5"
                >
                    <Mark class="h-[30px] w-5 text-mark" />
                    <span class="font-display text-title-2 font-medium"
                        >Manteion</span
                    >
                </Link>
                <div class="flex flex-col gap-1">
                    <Link
                        v-for="item in railItems"
                        :key="item.key"
                        :href="item.href"
                        :aria-current="item.active ? 'page' : undefined"
                        class="flex h-11 items-center gap-3 rounded-md pr-2.5 pl-2 transition-colors duration-120"
                        :class="
                            item.active
                                ? 'bg-surface ring-1 ring-line ring-inset'
                                : 'hover:bg-surface-2'
                        "
                    >
                        <component
                            :is="item.icon"
                            :size="22"
                            :stroke-width="1.75"
                            :class="
                                item.active
                                    ? 'text-accent-ink'
                                    : 'text-ink-muted'
                            "
                            aria-hidden="true"
                        />
                        <span
                            class="min-w-0 flex-1 truncate text-body"
                            :class="
                                item.active ? 'font-semibold' : 'font-medium'
                            "
                            >{{ item.label }}</span
                        >
                        <CountBadge
                            :count="item.badge"
                            :label="item.badgeLabel"
                        />
                    </Link>
                </div>
                <div class="mt-auto flex flex-col gap-3">
                    <AlphaNotice variant="card" />
                    <Link
                        :href="settingsIndex().url"
                        :aria-current="meItem.active ? 'page' : undefined"
                        class="flex items-center gap-3 rounded-md px-2 py-2 transition-colors duration-120"
                        :class="
                            meItem.active
                                ? 'bg-surface ring-1 ring-line ring-inset'
                                : 'hover:bg-surface-2'
                        "
                    >
                        <Avatar
                            :name="user.name"
                            :src="user.avatar_url"
                            :size="36"
                            decorative
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-label font-semibold"
                                >{{ user.name }}</span
                            >
                            <span
                                class="block truncate text-meta text-ink-muted"
                                >@{{ user.username }}</span
                            >
                        </span>
                        <Settings
                            :size="20"
                            :stroke-width="1.8"
                            class="shrink-0 text-ink-muted"
                            :aria-label="t('shell.openSettings')"
                        />
                    </Link>
                </div>
            </nav>

            <main
                id="main"
                class="min-w-0 flex-1"
                :class="
                    fullHeight
                        ? 'h-dvh overflow-hidden'
                        : hasTabBar
                          ? 'pb-[calc(var(--spacing-tabbar)+env(safe-area-inset-bottom)+24px)] sm:pb-12'
                          : 'sm:pb-12'
                "
            >
                <slot />
            </main>
        </div>

        <!-- Phone: the bottom tab bar. -->
        <nav
            v-if="hasTabBar"
            :aria-label="t('ui.mainNavigation')"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] [--badge-ring:var(--color-surface)] sm:hidden"
        >
            <div class="flex h-tabbar px-1">
                <Link
                    v-for="item in tabItems"
                    :key="item.key"
                    :href="item.href"
                    :aria-current="item.active ? 'page' : undefined"
                    class="flex min-w-max flex-1 basis-0 flex-col items-center justify-center gap-1"
                >
                    <span
                        class="relative grid h-[30px] w-14 place-items-center rounded-full transition-colors duration-200"
                        :class="
                            item.active
                                ? 'bg-accent-soft text-accent-ink'
                                : 'text-ink-muted'
                        "
                    >
                        <Avatar
                            v-if="item.key === 'me'"
                            :name="user.name"
                            :src="user.avatar_url"
                            :size="28"
                            decorative
                            :class="
                                item.active
                                    ? 'ring-2 ring-accent ring-offset-2 ring-offset-surface'
                                    : ''
                            "
                        />
                        <component
                            :is="item.icon"
                            v-else
                            :size="22"
                            :stroke-width="1.75"
                            aria-hidden="true"
                        />
                        <CountBadge
                            :count="item.badge"
                            :label="item.badgeLabel"
                            ring
                            class="absolute -top-[3px] left-8"
                        />
                    </span>
                    <span
                        class="text-caption"
                        :class="
                            item.active
                                ? 'font-semibold text-ink'
                                : 'font-medium text-ink-muted'
                        "
                        >{{ item.label }}</span
                    >
                </Link>
            </div>
        </nav>

        <Toaster />
        <ConfirmDialog />
        <StaffDeleteDialog />
        <ReportDialog />
    </div>
</template>
