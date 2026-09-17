<script setup lang="ts">
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AlphaBanner from '@/components/AlphaBanner.vue';
import Avatar from '@/components/Avatar.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import StaffDeleteDialog from '@/components/StaffDeleteDialog.vue';
import { logout } from '@/routes';
import { index as contactsIndex } from '@/routes/contacts';
import { index as eventsIndex } from '@/routes/events';
import { index as messagesIndex } from '@/routes/messages';
import { edit as profileEdit } from '@/routes/profile';

const { t } = useI18n();

const page = usePage();
const user = computed(() => page.props.auth.user);
const unreadNotificationsCount = computed(
    () => page.props.unreadNotificationsCount,
);
const unreadMessagesCount = computed(() => page.props.unreadMessagesCount);
const pendingContactRequestsCount = computed(
    () => page.props.pendingContactRequestsCount,
);

usePoll(20000, {
    only: [
        'unreadNotificationsCount',
        'unreadMessagesCount',
        'pendingContactRequestsCount',
    ],
});

const isImpersonating = computed(() => page.props.impersonating);

const navItems = computed(() => {
    const currentUrl = page.url;

    return [
        {
            label: t('nav.events'),
            href: eventsIndex().url,
            badge: unreadNotificationsCount.value,
        },
        {
            label: t('nav.messages'),
            href: messagesIndex().url,
            badge: unreadMessagesCount.value,
        },
        {
            label: t('nav.contacts'),
            href: contactsIndex().url,
            badge: pendingContactRequestsCount.value,
        },
    ].map((item) => ({
        ...item,
        active:
            currentUrl === item.href || currentUrl.startsWith(`${item.href}?`),
    }));
});
</script>

<template>
    <div class="min-h-screen bg-surface font-body text-ink">
        <div
            class="mx-auto flex min-h-screen w-full max-w-7xl flex-col lg:border-x-frame lg:border-edge"
        >
            <AlphaBanner />
            <ImpersonationBanner v-if="isImpersonating" />

            <div class="lg:flex lg:flex-1">
                <aside
                    class="hidden lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:border-r-frame lg:border-edge lg:p-6"
                >
                    <Link
                        :href="eventsIndex().url"
                        class="mb-8 text-4xl font-bold tracking-tight"
                    >
                        Manteion
                    </Link>

                    <div class="mb-6 border-b-frame border-edge pb-4">
                        <Link
                            :href="profileEdit().url"
                            class="mb-2 flex items-center gap-2"
                        >
                            <Avatar :url="user.avatar_url" :name="user.name" />
                            <span
                                class="truncate text-xs text-ink/70"
                                :title="user.name"
                            >
                                @{{ user.username }}
                            </span>
                        </Link>
                        <LanguageSwitcher class="mb-2 w-full" />
                        <Link
                            :href="logout().url"
                            method="post"
                            as="button"
                            class="w-full rounded-control border border-edge px-3 py-2 text-left text-sm hover:bg-accent hover:text-on-accent"
                        >
                            {{ t('nav.logOut') }}
                        </Link>
                    </div>

                    <nav class="flex flex-col gap-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.label"
                            :href="item.href"
                            class="flex items-center justify-between rounded-control px-3 py-2 text-sm"
                            :class="
                                item.active
                                    ? 'bg-accent text-on-accent'
                                    : 'hover:bg-accent hover:text-on-accent'
                            "
                        >
                            {{ item.label }}
                            <span v-if="item.badge" class="text-xs">{{
                                item.badge
                            }}</span>
                        </Link>
                    </nav>
                </aside>

                <div class="flex min-h-screen flex-1 flex-col">
                    <div class="sticky top-0 z-10 bg-surface lg:hidden">
                        <header
                            class="flex items-center justify-between border-b-frame border-edge px-4 py-3"
                        >
                            <Link
                                :href="eventsIndex().url"
                                class="text-2xl font-bold tracking-tight"
                            >
                                Manteion
                            </Link>
                            <div class="flex items-center gap-3">
                                <LanguageSwitcher />
                                <Link
                                    :href="profileEdit().url"
                                    class="flex items-center gap-2 text-xs text-ink/70"
                                >
                                    <Avatar
                                        :url="user.avatar_url"
                                        :name="user.name"
                                    />
                                    @{{ user.username }}
                                </Link>
                            </div>
                        </header>

                        <nav
                            class="grid grid-cols-3 border-b-frame border-edge"
                        >
                            <Link
                                v-for="item in navItems"
                                :key="item.label"
                                :href="item.href"
                                class="flex flex-col items-center gap-0.5 py-2 text-sm"
                                :class="
                                    item.active
                                        ? 'bg-accent text-on-accent'
                                        : ''
                                "
                            >
                                {{ item.label }}
                                <span v-if="item.badge" class="text-xs">{{
                                    item.badge
                                }}</span>
                            </Link>
                        </nav>
                    </div>

                    <main class="flex-1 px-4 py-6 lg:px-10 lg:py-10">
                        <slot />
                    </main>
                </div>
            </div>
        </div>

        <ConfirmDialog />
        <StaffDeleteDialog />
    </div>
</template>
