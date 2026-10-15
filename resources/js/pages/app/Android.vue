<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BellRing, Download, Reply, SlidersHorizontal } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { latestAndroidRelease } from '@/native/release';
import { home } from '@/routes';

/**
 * The Android app: what it does better than the site on the home screen,
 * the download (the newest signed build on GitHub) and the three steps
 * Android asks for when an app comes from outside Google Play.
 */
defineOptions({ layout: GuestLayout });

const { t } = useI18n();
const page = usePage();

const version = ref<string | null>(null);

onMounted(async () => {
    version.value =
        (await latestAndroidRelease(page.props.androidApp.latestReleaseApi))
            ?.version ?? null;
});

const points = [
    { icon: BellRing, key: 'point1' },
    { icon: Reply, key: 'point2' },
    { icon: SlidersHorizontal, key: 'point3' },
];
</script>

<template>
    <Head :title="t('app.download.title')" />

    <h1 class="font-display text-title-1">{{ t('app.download.title') }}</h1>
    <p class="mt-1 text-body text-ink-muted">{{ t('app.download.lede') }}</p>

    <ul class="mt-5 flex flex-col gap-3">
        <li v-for="point in points" :key="point.key" class="flex gap-3">
            <span
                class="grid size-9 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                aria-hidden="true"
            >
                <component :is="point.icon" :size="18" :stroke-width="1.75" />
            </span>
            <span class="self-center text-meta">{{
                t(`app.download.${point.key}`)
            }}</span>
        </li>
    </ul>

    <div class="mt-6 flex flex-col items-start gap-1.5">
        <Button
            as="a"
            :href="page.props.androidApp.downloadUrl"
            variant="primary"
            size="lg"
            :icon="Download"
        >
            {{ t('app.download.button') }}
        </Button>
        <p v-if="version" class="text-meta text-ink-muted">
            {{ t('app.download.version', { version }) }}
        </p>
    </div>

    <h2 class="mt-7 font-display text-title-3">
        {{ t('app.download.stepsTitle') }}
    </h2>
    <ol class="mt-2 flex list-decimal flex-col gap-2 pl-5 text-meta">
        <li>{{ t('app.download.step1') }}</li>
        <li>{{ t('app.download.step2') }}</li>
        <li>{{ t('app.download.step3') }}</li>
    </ol>
    <p class="mt-3 text-meta text-ink-muted">
        {{ t('app.download.updates') }}
    </p>
    <p class="mt-3 text-meta text-ink-muted">
        {{ t('app.download.iphone') }}
    </p>

    <Link
        :href="home().url"
        class="mt-6 inline-block text-meta font-semibold underline underline-offset-4"
    >
        {{ t('app.download.back') }}
    </Link>
</template>
