<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AlphaBanner from '@/components/AlphaBanner.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';

const { t } = useI18n();
</script>

<template>
    <Head :title="t('welcome.title')" />

    <div
        class="font-body relative flex min-h-screen flex-col items-center justify-center bg-surface px-4 text-ink"
    >
        <div class="absolute inset-x-0 top-0">
            <AlphaBanner />
        </div>

        <div class="mb-6">
            <LanguageSwitcher />
        </div>

        <h1 class="text-2xl mb-2 font-bold tracking-tight">Manteion</h1>
        <p class="text-sm mb-8 max-w-sm text-center text-ink/70">
            {{ t('welcome.tagline') }}
        </p>

        <div class="flex gap-3">
            <Link
                href="/login"
                class="border-edge text-sm border px-4 py-2 hover:bg-accent hover:text-on-accent"
            >
                {{ t('welcome.logIn') }}
            </Link>
            <Link
                href="/register"
                class="border-edge text-sm hover:bg-surface-raised border bg-accent px-4 py-2 text-on-accent hover:text-ink"
            >
                {{ t('welcome.register') }}
            </Link>
        </div>
    </div>
</template>
