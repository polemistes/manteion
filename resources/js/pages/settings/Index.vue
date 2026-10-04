<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsGroup from '@/components/settings/SettingsGroup.vue';
import SettingsNav from '@/components/settings/SettingsNav.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import { useInstallPrompt } from '@/pwa/install';
import { account } from '@/routes/settings';
import { show as usersShow } from '@/routes/users';

/**
 * Settings, the Me tab. On a phone and a small tablet it is the list of
 * panels; from 768 the nav sits beside an overview of the account.
 */
const { t } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth.user);
const { canInstall, promptInstall } = useInstallPrompt();
</script>

<template>
    <PageHeader :title="t('settings.title')" width="wide" root />

    <PageContainer width="wide" class="pt-4 pb-16 sm:pt-0">
        <div class="md:hidden">
            <SettingsNav variant="list" />

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
                <h2 class="font-display text-title-2">
                    {{ t('settings.overview.title') }}
                </h2>
                <div
                    class="flex items-center gap-4 rounded-lg border border-line bg-surface p-5"
                >
                    <Avatar
                        :name="user.name"
                        :src="user.avatar_url"
                        :size="64"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-display text-title-3 text-ink">
                            {{ user.name }}
                        </p>
                        <p class="truncate text-meta text-ink-muted">
                            @{{ user.username }} · {{ user.email }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button :as="Link" :href="account().url" size="sm">
                                {{ t('settings.overview.editAccount') }}
                            </Button>
                            <Button
                                :as="Link"
                                :href="usersShow(user.id).url"
                                variant="ghost"
                                size="sm"
                            >
                                {{ t('settings.overview.seeProfile') }}
                            </Button>
                        </div>
                    </div>
                </div>

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
