<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, Flag, LogOut, Users } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import { logout } from '@/routes';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as moderationReportsIndex } from '@/routes/moderation/reports';
import { show as usersShow } from '@/routes/users';
import { sections } from './sections';
import type { SettingsSection } from './sections';

/**
 * The way around Settings: who you are (to your profile), the panels, the
 * staff tools for moderators and administrators, and Log out. Beside the
 * panel from 768 (`aside`); the Settings screen itself on smaller screens
 * (`list`), where each panel opens on its own.
 */
const props = withDefaults(
    defineProps<{
        variant?: 'aside' | 'list';
        current?: SettingsSection | null;
    }>(),
    { variant: 'aside', current: null },
);

const { t } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth.user);

const staffLinks = computed(() => {
    const role = user.value.role;
    const links = [];

    if (role === 'moderator' || role === 'administrator') {
        links.push({
            label: t('settings.moderation'),
            href: moderationReportsIndex().url,
            icon: Flag,
        });
    }

    if (role === 'administrator') {
        links.push({
            label: t('settings.users'),
            href: adminUsersIndex().url,
            icon: Users,
        });
    }

    return links;
});
</script>

<template>
    <nav
        v-if="props.variant === 'aside'"
        :aria-label="t('settings.title')"
        class="flex flex-col gap-1"
    >
        <Link
            :href="usersShow(user.id).url"
            class="mb-5 flex items-center gap-3 rounded-lg border border-line bg-surface p-3 transition-colors duration-120 hover:bg-surface-2/55"
        >
            <Avatar :name="user.name" :src="user.avatar_url" :size="40" />
            <span class="min-w-0">
                <span class="block truncate font-semibold text-ink">{{
                    user.name
                }}</span>
                <span class="block truncate text-meta text-ink-muted"
                    >@{{ user.username }}</span
                >
            </span>
        </Link>

        <Link
            v-for="section in sections"
            :key="section.key"
            :href="section.href()"
            class="flex h-11 items-center gap-3 rounded-md px-3 text-label font-medium transition-colors duration-120"
            :class="
                props.current === section.key
                    ? 'bg-surface font-semibold text-ink ring-1 ring-line'
                    : 'text-ink-muted hover:bg-surface-2 hover:text-ink'
            "
            :aria-current="props.current === section.key ? 'page' : undefined"
        >
            <component
                :is="section.icon"
                :size="20"
                :stroke-width="1.75"
                :class="props.current === section.key ? 'text-accent' : ''"
                aria-hidden="true"
            />
            {{ t(`settings.sections.${section.key}`) }}
        </Link>

        <template v-if="staffLinks.length">
            <p class="mt-6 mb-1 px-3 text-kicker text-ink-muted uppercase">
                {{ t('settings.staff') }}
            </p>
            <Link
                v-for="link in staffLinks"
                :key="link.href"
                :href="link.href"
                class="flex h-11 items-center gap-3 rounded-md px-3 text-label font-medium text-ink-muted transition-colors duration-120 hover:bg-surface-2 hover:text-ink"
            >
                <component
                    :is="link.icon"
                    :size="20"
                    :stroke-width="1.75"
                    aria-hidden="true"
                />
                {{ link.label }}
            </Link>
        </template>

        <Link
            :href="logout()"
            as="button"
            class="mt-4 flex h-11 items-center gap-3 rounded-md px-3 text-left text-label font-medium text-ink-muted transition-colors duration-120 hover:bg-surface-2 hover:text-ink"
        >
            <LogOut :size="20" :stroke-width="1.75" aria-hidden="true" />
            {{ t('settings.logOut') }}
        </Link>
    </nav>

    <nav v-else :aria-label="t('settings.title')" class="flex flex-col gap-6">
        <Link
            :href="usersShow(user.id).url"
            class="flex items-center gap-3.5 rounded-lg border border-line bg-surface p-4 transition-colors duration-120 hover:bg-surface-2/55"
        >
            <Avatar :name="user.name" :src="user.avatar_url" :size="56" />
            <span class="min-w-0 flex-1">
                <span
                    class="block truncate font-display text-title-3 text-ink"
                    >{{ user.name }}</span
                >
                <span class="block truncate text-meta text-ink-muted">{{
                    t('settings.seeProfile', { username: user.username })
                }}</span>
            </span>
            <ChevronRight
                :size="18"
                :stroke-width="1.75"
                class="shrink-0 text-ink-subtle"
                aria-hidden="true"
            />
        </Link>

        <div class="overflow-hidden rounded-lg border border-line bg-surface">
            <Link
                v-for="section in sections"
                :key="section.key"
                :href="section.href()"
                class="group/row relative flex min-h-14 items-center gap-3.5 px-4 transition-colors duration-120 hover:bg-surface-2/55"
            >
                <span
                    class="pointer-events-none absolute top-0 right-0 left-[52px] h-px bg-line group-first/row:hidden"
                    aria-hidden="true"
                />
                <component
                    :is="section.icon"
                    :size="20"
                    :stroke-width="1.75"
                    class="shrink-0 text-ink-muted"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1 font-medium text-ink">{{
                    t(`settings.sections.${section.key}`)
                }}</span>
                <ChevronRight
                    :size="18"
                    :stroke-width="1.75"
                    class="shrink-0 text-ink-subtle"
                    aria-hidden="true"
                />
            </Link>
        </div>

        <section v-if="staffLinks.length">
            <h2 class="mb-2.5 text-kicker text-ink-muted uppercase">
                {{ t('settings.staff') }}
            </h2>
            <div
                class="overflow-hidden rounded-lg border border-line bg-surface"
            >
                <Link
                    v-for="link in staffLinks"
                    :key="link.href"
                    :href="link.href"
                    class="group/row relative flex min-h-14 items-center gap-3.5 px-4 transition-colors duration-120 hover:bg-surface-2/55"
                >
                    <span
                        class="pointer-events-none absolute top-0 right-0 left-[52px] h-px bg-line group-first/row:hidden"
                        aria-hidden="true"
                    />
                    <component
                        :is="link.icon"
                        :size="20"
                        :stroke-width="1.75"
                        class="shrink-0 text-ink-muted"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 font-medium text-ink">{{
                        link.label
                    }}</span>
                    <ChevronRight
                        :size="18"
                        :stroke-width="1.75"
                        class="shrink-0 text-ink-subtle"
                        aria-hidden="true"
                    />
                </Link>
            </div>
        </section>

        <div class="overflow-hidden rounded-lg border border-line bg-surface">
            <Link
                :href="logout()"
                as="button"
                class="flex min-h-14 w-full items-center gap-3.5 px-4 text-left transition-colors duration-120 hover:bg-surface-2/55"
            >
                <LogOut
                    :size="20"
                    :stroke-width="1.75"
                    class="shrink-0 text-ink-muted"
                    aria-hidden="true"
                />
                <span class="font-medium text-ink">{{
                    t('settings.logOut')
                }}</span>
            </Link>
        </div>
    </nav>
</template>
