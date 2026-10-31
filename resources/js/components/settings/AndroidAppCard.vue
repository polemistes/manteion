<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import SettingsGroup from '@/components/settings/SettingsGroup.vue';
import Button from '@/components/ui/Button.vue';
import { useAndroidAppUpdate } from '@/native/update';
import { android as androidPage } from '@/routes/app';

/**
 * Inside the Android app: which version this is and, when GitHub has a
 * newer one, the download and the install steps. Nothing on the web.
 */
const { t } = useI18n();
const { inApp, installed, latest, checked, updateAvailable, download } =
    useAndroidAppUpdate();
</script>

<template>
    <SettingsGroup v-if="inApp" :flush="false">
        <p class="font-semibold text-ink">{{ t('app.download.title') }}</p>
        <p v-if="installed" class="mt-1 text-meta text-ink-muted">
            {{ t('app.version.installed', { version: installed }) }}
        </p>
        <p v-if="updateAvailable" class="mt-1 text-meta text-ink">
            {{ t('app.version.ready', { version: latest }) }}
        </p>
        <p v-else-if="checked && latest" class="mt-1 text-meta text-ink-muted">
            {{ t('app.version.upToDate') }}
        </p>
        <p v-else-if="checked" class="mt-1 text-meta text-ink-muted">
            {{ t('app.version.unknown') }}
        </p>
        <p v-else class="mt-1 text-meta text-ink-muted">
            {{ t('app.version.checking') }}
        </p>
        <div v-if="updateAvailable" class="mt-3 flex flex-wrap gap-2">
            <Button
                variant="primary"
                size="sm"
                :icon="Download"
                @click="download"
            >
                {{ t('app.version.download', { version: latest }) }}
            </Button>
            <Button :as="Link" :href="androidPage().url" size="sm">
                {{ t('app.version.howTo') }}
            </Button>
        </div>
    </SettingsGroup>
</template>
