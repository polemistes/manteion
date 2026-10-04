<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import AlphaNotice from '@/components/shell/AlphaNotice.vue';
import Mark from '@/components/ui/Mark.vue';
import Toaster from '@/components/ui/Toaster.vue';
import { home } from '@/routes';

/** Sign-in, registration and password pages: one calm card on paper. */
</script>

<template>
    <div
        class="flex min-h-dvh flex-col items-center bg-canvas px-4 pt-[calc(24px+env(safe-area-inset-top))] pb-[calc(32px+env(safe-area-inset-bottom))] text-ink sm:justify-center sm:py-12"
    >
        <div class="flex w-full max-w-[400px] flex-col gap-5">
            <div class="flex items-center justify-between gap-3">
                <Link
                    :href="home().url"
                    class="flex shrink-0 items-center gap-2.5"
                >
                    <Mark class="h-[30px] w-5 text-mark" />
                    <span
                        class="font-display text-title-2 font-medium whitespace-nowrap"
                        >Manteion</span
                    >
                </Link>
                <LanguageSwitcher />
            </div>

            <AlphaNotice />

            <div class="rounded-xl border border-line bg-surface p-5 sm:p-7">
                <slot />
            </div>
        </div>
        <Toaster />
    </div>
</template>
