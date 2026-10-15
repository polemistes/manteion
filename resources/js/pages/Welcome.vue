<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, MessageCircle, Newspaper, Smartphone } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import AlphaNotice from '@/components/shell/AlphaNotice.vue';
import Button from '@/components/ui/Button.vue';
import Mark from '@/components/ui/Mark.vue';
import { isNativeShell } from '@/pwa/platform';
import { login, register } from '@/routes';
import { android as androidApp } from '@/routes/app';

/** The front door for someone not signed in. */
const { t } = useI18n();

// The way to the Android app, except inside it. Known only once mounted,
// so the server render and hydration agree.
const offerApp = ref(false);

onMounted(() => {
    offerApp.value = !isNativeShell();
});

const features = computed(() => [
    {
        icon: Calendar,
        title: t('welcome.events'),
        body: t('welcome.eventsBody'),
    },
    {
        icon: MessageCircle,
        title: t('welcome.messages'),
        body: t('welcome.messagesBody'),
    },
    {
        icon: Newspaper,
        title: t('welcome.leaflets'),
        body: t('welcome.leafletsBody'),
    },
]);
</script>

<template>
    <Head :title="t('welcome.title')" />

    <div class="min-h-dvh bg-canvas text-ink">
        <div
            class="mx-auto flex min-h-dvh max-w-[1104px] flex-col px-4 pt-[calc(12px+env(safe-area-inset-top))] pb-[calc(24px+env(safe-area-inset-bottom))] sm:px-8"
        >
            <header class="flex items-center justify-between gap-3 py-2">
                <span class="flex shrink-0 items-center gap-2.5">
                    <Mark class="h-[30px] w-5 text-mark" />
                    <span
                        class="font-display text-title-2 font-medium whitespace-nowrap"
                        >Manteion</span
                    >
                </span>
                <LanguageSwitcher />
            </header>

            <!-- Under the header, as on the sign-in pages and in the app. -->
            <AlphaNotice class="mt-2 sm:max-w-[34rem]" />

            <main
                class="flex flex-1 flex-col justify-center py-10 lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:items-center lg:gap-16"
            >
                <div>
                    <h1
                        class="font-display text-[2.5rem] leading-[1.08] font-medium tracking-[-0.015em] text-balance sm:text-[3.25rem]"
                    >
                        {{ t('welcome.headline') }}
                    </h1>
                    <p
                        class="mt-4 max-w-[34rem] font-display text-reading text-ink-muted"
                    >
                        {{ t('welcome.tagline') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Button
                            :as="Link"
                            :href="register().url"
                            variant="primary"
                            size="lg"
                        >
                            {{ t('welcome.register') }}
                        </Button>
                        <Button :as="Link" :href="login().url" size="lg">
                            {{ t('welcome.logIn') }}
                        </Button>
                    </div>
                    <Link
                        v-if="offerApp"
                        :href="androidApp().url"
                        class="mt-5 inline-flex items-center gap-2 text-meta font-semibold text-ink-muted underline underline-offset-4"
                    >
                        <Smartphone :size="16" :stroke-width="1.75" />
                        {{ t('app.get.title') }}
                    </Link>
                </div>

                <ul class="mt-12 flex flex-col gap-3 lg:mt-0">
                    <li
                        v-for="feature in features"
                        :key="feature.title"
                        class="flex gap-4 rounded-lg border border-line bg-surface p-5"
                    >
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                            aria-hidden="true"
                        >
                            <component
                                :is="feature.icon"
                                :size="22"
                                :stroke-width="1.75"
                            />
                        </span>
                        <div class="min-w-0">
                            <h2 class="font-display text-title-3">
                                {{ feature.title }}
                            </h2>
                            <p class="mt-1 text-meta text-ink-muted">
                                {{ feature.body }}
                            </p>
                        </div>
                    </li>
                </ul>
            </main>
        </div>
    </div>
</template>
