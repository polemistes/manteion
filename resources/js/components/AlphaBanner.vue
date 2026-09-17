<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div
        class="w-full bg-accent px-4 py-2 text-center text-xs font-bold text-on-accent"
    >
        {{ t('common.alphaNotice') }}
    </div>
</template>
