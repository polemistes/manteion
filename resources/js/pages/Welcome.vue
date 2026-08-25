<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('welcome.title')" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-white px-4 font-mono text-black"
    >
        <div class="mb-6">
            <LanguageSwitcher />
        </div>

        <h1 class="mb-2 text-2xl font-bold tracking-tight">Manteion</h1>
        <p class="mb-8 max-w-sm text-center text-sm text-black/70">
            {{ t('welcome.tagline') }}
        </p>

        <div class="flex gap-3">
            <Link
                href="/login"
                class="border border-black px-4 py-2 text-sm hover:bg-black hover:text-white"
            >
                {{ t('welcome.logIn') }}
            </Link>
            <Link
                href="/register"
                class="border border-black bg-black px-4 py-2 text-sm text-white hover:bg-white hover:text-black"
            >
                {{ t('welcome.register') }}
            </Link>
        </div>
    </div>
</template>
