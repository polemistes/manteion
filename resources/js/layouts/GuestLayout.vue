<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-white px-4 py-12 font-mono text-black"
    >
        <div class="mb-8 flex w-full max-w-sm items-center justify-between">
            <Link href="/" class="text-xl font-bold tracking-tight">
                Manteion
            </Link>
            <LanguageSwitcher />
        </div>

        <div class="w-full max-w-sm border-[3px] border-black p-6">
            <slot />
        </div>
    </div>
</template>
