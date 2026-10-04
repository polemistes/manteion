<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bell, ChevronLeft, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';
import Mark from '@/components/ui/Mark.vue';
import AlphaNotice from './AlphaNotice.vue';
import PageContainer from './PageContainer.vue';
import { useNavigation } from './useNavigation';

/**
 * A page's title, rendered twice and switched by CSS (so server and browser
 * agree): the phone top bar (under 640px) and the page header from 640.
 *
 * - `root` pages (a tab's first screen) get the mark, the Activity bell and
 *   one contextual action (`phone-actions` slot) in the top bar, and the
 *   alpha notice under it.
 * - Pushed pages get a back link (`back`, optional `backLabel`), creation
 *   flows a Cancel × (`close`).
 * - From 640 the header shows an optional crumb, `kicker`, the title, the
 *   `actions` slot and a `lede`.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        documentTitle?: string;
        root?: boolean;
        back?: string;
        backLabel?: string;
        close?: string;
        kicker?: string;
        lede?: string;
        phoneHidden?: boolean;
        headerHidden?: boolean;
        width?: 'feed' | 'wide' | 'panel';
    }>(),
    {
        documentTitle: undefined,
        root: false,
        back: undefined,
        backLabel: undefined,
        close: undefined,
        kicker: undefined,
        lede: undefined,
        phoneHidden: false,
        headerHidden: false,
        width: 'feed',
    },
);

const { t } = useI18n();
const { activity } = useNavigation();
</script>

<template>
    <Head :title="props.documentTitle ?? props.title" />

    <header
        v-if="!props.phoneHidden"
        class="sticky top-0 z-30 border-b border-line bg-canvas pt-[env(safe-area-inset-top)] sm:hidden"
    >
        <div
            class="flex h-topbar items-center gap-1"
            :class="props.root ? 'pr-2 pl-4' : 'px-1'"
        >
            <template v-if="props.root">
                <Mark class="mr-2 h-[25px] w-[17px] shrink-0 text-mark" />
                <h1
                    class="min-w-0 flex-1 truncate font-display text-title-2 font-medium"
                >
                    {{ props.title }}
                </h1>
                <IconButton
                    :as="Link"
                    :href="activity.href"
                    :icon="Bell"
                    :label="activity.label"
                    :badge="activity.badge"
                    :badge-label="activity.badgeLabel"
                />
                <slot name="phone-actions" />
            </template>
            <template v-else>
                <Link
                    v-if="props.close"
                    :href="props.close"
                    :aria-label="t('shell.cancel')"
                    class="grid size-11 shrink-0 place-items-center rounded-full text-ink hover:bg-surface-2"
                >
                    <X :size="22" :stroke-width="1.75" aria-hidden="true" />
                </Link>
                <Link
                    v-else-if="props.back"
                    :href="props.back"
                    :aria-label="props.backLabel ?? t('ui.back')"
                    class="flex h-11 shrink-0 items-center rounded-full pr-2 pl-1 text-ink hover:bg-surface-2"
                >
                    <ChevronLeft
                        :size="22"
                        :stroke-width="1.75"
                        aria-hidden="true"
                    />
                    <span
                        v-if="props.backLabel"
                        class="max-w-[9rem] truncate text-input font-medium text-ink-muted"
                        aria-hidden="true"
                        >{{ props.backLabel }}</span
                    >
                </Link>
                <h1
                    class="min-w-0 flex-1 truncate px-1 text-input font-semibold"
                >
                    {{ props.title }}
                </h1>
                <slot name="phone-actions" />
            </template>
        </div>
    </header>

    <AlphaNotice v-if="props.root" class="mx-4 mt-3 sm:hidden" />

    <PageContainer
        v-if="props.root || !props.headerHidden"
        :width="props.width"
        class="hidden sm:block"
    >
        <div v-if="props.root" class="pt-4 lg:hidden">
            <AlphaNotice />
        </div>

        <div v-if="!props.headerHidden" class="pt-7 pb-4.5 lg:pt-9 lg:pb-5.5">
            <Link
                v-if="props.back && !props.root"
                :href="props.back"
                class="mb-3 -ml-3 inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-label font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink"
            >
                <ArrowLeft :size="16" :stroke-width="2" aria-hidden="true" />
                {{ props.backLabel ?? t('ui.back') }}
            </Link>
            <div
                class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-x-4 gap-y-2"
            >
                <div class="min-w-0">
                    <p
                        v-if="props.kicker"
                        class="mb-1.5 text-kicker tracking-[0.1em] text-ink-muted uppercase"
                    >
                        {{ props.kicker }}
                    </p>
                    <h1
                        class="font-display text-title-1 text-pretty lg:text-display"
                    >
                        {{ props.title }}
                    </h1>
                </div>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-center justify-end gap-2 pb-0.5"
                >
                    <slot name="actions" />
                </div>
                <p
                    v-if="props.lede || $slots.lede"
                    class="col-span-2 text-body text-ink-muted"
                >
                    <slot name="lede">{{ props.lede }}</slot>
                </p>
            </div>
        </div>
    </PageContainer>
</template>
