<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AlphaBanner from '@/components/AlphaBanner.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
</script>

<template>
    <div>
        <AlphaBanner />

        <div
            class="flex min-h-screen flex-col items-center justify-center bg-surface px-4 py-12 font-body text-ink"
        >
            <div class="mb-8 flex w-full max-w-sm items-center justify-between">
                <Link href="/" class="text-xl font-bold tracking-tight">
                    Manteion
                </Link>
                <LanguageSwitcher />
            </div>

            <div class="w-full max-w-sm border-frame border-edge p-6">
                <slot />
            </div>
        </div>
    </div>
</template>
