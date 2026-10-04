<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from '@lucide/vue';
import {
    ToastAction,
    ToastClose,
    ToastDescription,
    ToastProvider,
    ToastRoot,
    ToastTitle,
    ToastViewport,
} from 'reka-ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { dismissBanner, dismissToast, toastState } from '@/lib/toast';
import type { MessageBanner } from '@/lib/toast';
import Avatar from './Avatar.vue';
import Button from './Button.vue';
import IconButton from './IconButton.vue';

/**
 * Hosts status toasts (one at a time, bottom of the screen) and message
 * banners (top on phones, stacked above the toasts from 640). The shell
 * sets `--toast-bottom` so toasts clear whatever bar the page has.
 */
const { t } = useI18n();

const current = computed(() => toastState.toasts[0] ?? null);
const open = ref(true);

watch(
    () => current.value?.id,
    () => {
        open.value = true;
    },
);

function onOpenChange(value: boolean): void {
    open.value = value;

    if (!value && current.value) {
        const id = current.value.id;

        setTimeout(() => dismissToast(id), 200);
    }
}

const BANNER_MS = 6000;
const timers = new Map<number, ReturnType<typeof setTimeout>>();

function startBannerTimer(banner: MessageBanner): void {
    stopBannerTimer(banner.id);
    timers.set(
        banner.id,
        setTimeout(() => dismissBanner(banner.id), BANNER_MS),
    );
}

function stopBannerTimer(id: number): void {
    const timer = timers.get(id);

    if (timer) {
        clearTimeout(timer);
        timers.delete(id);
    }
}

watch(
    () => toastState.banners.map((banner) => banner.id),
    (ids) => {
        for (const banner of toastState.banners) {
            if (!timers.has(banner.id)) {
                startBannerTimer(banner);
            }
        }

        for (const id of [...timers.keys()]) {
            if (!ids.includes(id)) {
                stopBannerTimer(id);
            }
        }
    },
);

onBeforeUnmount(() => {
    for (const id of [...timers.keys()]) {
        stopBannerTimer(id);
    }
});
</script>

<template>
    <ToastProvider :duration="current?.duration ?? 5000" swipe-direction="down">
        <ToastRoot
            v-if="current"
            :key="current.id"
            :open="open"
            :type="current.tone === 'error' ? 'foreground' : 'background'"
            :role="current.tone === 'error' ? 'alert' : 'status'"
            class="flex min-h-12 items-center gap-3 rounded-md bg-inverse py-1.5 pr-1.5 pl-4 text-label text-on-inverse shadow-lg data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[toast-in_280ms_var(--ease-enter)]"
            @update:open="onOpenChange"
        >
            <component
                :is="current.icon ?? (current.tone === 'error' ? CircleAlert : CircleCheck)"
                :size="18"
                :stroke-width="1.9"
                class="shrink-0 text-inverse-accent"
                aria-hidden="true"
            />
            <div class="min-w-0 flex-1 py-1.5">
                <ToastTitle class="font-semibold">{{ current.title }}</ToastTitle>
                <ToastDescription
                    v-if="current.description"
                    class="text-meta opacity-78"
                >
                    {{ current.description }}
                </ToastDescription>
            </div>
            <ToastAction
                v-if="current.action"
                :alt-text="current.action.label"
                as-child
            >
                <button
                    type="button"
                    class="h-10 shrink-0 rounded-full px-3 font-semibold text-inverse-accent hover:bg-on-inverse/10"
                    @click="current.action.onClick()"
                >
                    {{ current.action.label }}
                </button>
            </ToastAction>
            <ToastClose
                v-else
                :aria-label="t('ui.dismiss')"
                class="grid size-10 shrink-0 place-items-center rounded-full text-on-inverse/70 hover:bg-on-inverse/10 hover:text-on-inverse"
            >
                <X :size="18" :stroke-width="1.9" aria-hidden="true" />
            </ToastClose>
        </ToastRoot>

        <ToastViewport
            class="fixed right-4 bottom-[var(--toast-bottom,calc(16px+env(safe-area-inset-bottom)))] left-4 z-[60] flex flex-col gap-2 outline-none sm:right-auto sm:bottom-6 sm:left-[calc(var(--spacing-rail)+24px)] sm:w-90 lg:left-[calc(var(--spacing-sidebar)+24px)]"
        />
    </ToastProvider>

    <div
        class="pointer-events-none fixed inset-x-2 top-[calc(8px+env(safe-area-inset-top))] z-[60] flex flex-col gap-2 sm:top-auto sm:right-auto sm:bottom-[calc(24px+64px)] sm:left-[calc(var(--spacing-rail)+24px)] sm:w-90 lg:left-[calc(var(--spacing-sidebar)+24px)]"
        aria-live="polite"
    >
        <div
            v-for="banner in toastState.banners"
            :key="banner.id"
            class="pointer-events-auto relative flex items-center gap-3 rounded-lg bg-surface py-2.5 pr-1.5 pl-3 shadow-lg ring-1 ring-line animate-[banner-in_280ms_var(--ease-enter)] sm:animate-[toast-in_280ms_var(--ease-enter)]"
            @mouseenter="stopBannerTimer(banner.id)"
            @mouseleave="startBannerTimer(banner)"
            @focusin="stopBannerTimer(banner.id)"
        >
            <Avatar :name="banner.name" :src="banner.avatarUrl" :size="40" />
            <Link
                :href="banner.href"
                class="min-w-0 flex-1 after:absolute after:inset-0 focus-visible:outline-none"
                @click="dismissBanner(banner.id)"
            >
                <span class="flex items-baseline gap-1.5">
                    <span class="truncate font-semibold text-ink">{{
                        banner.name
                    }}</span>
                    <span class="shrink-0 text-meta text-ink-muted"
                        >· {{ t('ui.now') }}</span
                    >
                </span>
                <span class="block truncate text-label text-ink-muted">{{
                    banner.preview
                }}</span>
            </Link>
            <Button
                :as="Link"
                :href="banner.href"
                size="sm"
                class="relative z-10"
                @click="dismissBanner(banner.id)"
            >
                {{ t('ui.reply') }}
            </Button>
            <IconButton
                :icon="X"
                :label="t('ui.dismiss')"
                size="sm"
                class="relative z-10"
                @click="dismissBanner(banner.id)"
            />
        </div>
    </div>
</template>
