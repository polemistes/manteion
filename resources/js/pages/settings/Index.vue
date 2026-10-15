<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Download, Smartphone } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { sections } from '@/components/settings/sections';
import SettingsGroup from '@/components/settings/SettingsGroup.vue';
import SettingsNav from '@/components/settings/SettingsNav.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import ListRow from '@/components/ui/ListRow.vue';
import { useInstallPrompt } from '@/pwa/install';
import { isNativeShell } from '@/pwa/platform';
import { android as androidApp } from '@/routes/app';

/**
 * Settings, the Me tab. On a phone and a small tablet it is the list of
 * panels; from 768 the nav sits beside the panels with what each holds.
 */
const { t } = useI18n();

const { canInstall, promptInstall } = useInstallPrompt();

// On an Android phone's browser, the way to the app (whose notifications
// come from Manteion rather than the browser). Known only once mounted.
const offerAndroidApp = ref(false);

onMounted(() => {
    offerAndroidApp.value =
        /Android/.test(navigator.userAgent) && !isNativeShell();
});
</script>

<template>
    <PageHeader :title="t('settings.title')" width="wide" root />

    <PageContainer width="wide" class="pt-4 pb-16 sm:pt-0">
        <div class="md:hidden">
            <SettingsNav variant="list" />

            <SettingsGroup v-if="offerAndroidApp" class="mt-6" :flush="false">
                <p class="font-semibold text-ink">
                    {{ t('app.get.title') }}
                </p>
                <p class="mt-1 text-meta text-ink-muted">
                    {{ t('app.get.body') }}
                </p>
                <Button
                    :as="Link"
                    :href="androidApp().url"
                    size="sm"
                    :icon="Smartphone"
                    class="mt-3"
                >
                    {{ t('app.get.action') }}
                </Button>
            </SettingsGroup>

            <SettingsGroup v-if="canInstall" class="mt-6" :flush="false">
                <p class="font-semibold text-ink">
                    {{ t('settings.install.title') }}
                </p>
                <p class="mt-1 text-meta text-ink-muted">
                    {{ t('settings.install.body') }}
                </p>
                <Button
                    size="sm"
                    :icon="Download"
                    class="mt-3"
                    @click="promptInstall"
                >
                    {{ t('settings.install.action') }}
                </Button>
            </SettingsGroup>
        </div>

        <div
            class="hidden md:grid md:grid-cols-[220px_minmax(0,40rem)] md:gap-12 lg:grid-cols-[240px_minmax(0,40rem)]"
        >
            <div>
                <div class="sticky top-6">
                    <SettingsNav />
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-7">
                <Card>
                    <ListRow
                        v-for="section in sections"
                        :key="section.key"
                        :as="Link"
                        :href="section.href()"
                        interactive
                        :title="t(`settings.sections.${section.key}`)"
                        :inset="70"
                    >
                        <template #leading>
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                                aria-hidden="true"
                            >
                                <component
                                    :is="section.icon"
                                    :size="20"
                                    :stroke-width="1.75"
                                />
                            </span>
                        </template>
                        <span class="mt-0.5 block text-meta text-ink-muted">{{
                            t(`settings.overview.hints.${section.key}`)
                        }}</span>
                        <template #trailing>
                            <ChevronRight
                                :size="18"
                                :stroke-width="1.9"
                                class="shrink-0 text-ink-subtle"
                                aria-hidden="true"
                            />
                        </template>
                    </ListRow>
                </Card>

                <SettingsGroup v-if="canInstall" :flush="false">
                    <p class="font-semibold text-ink">
                        {{ t('settings.install.title') }}
                    </p>
                    <p class="mt-1 text-meta text-ink-muted">
                        {{ t('settings.install.body') }}
                    </p>
                    <Button
                        size="sm"
                        :icon="Download"
                        class="mt-3"
                        @click="promptInstall"
                    >
                        {{ t('settings.install.action') }}
                    </Button>
                </SettingsGroup>
            </div>
        </div>
    </PageContainer>
</template>
