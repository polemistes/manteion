<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <div
        class="w-full bg-black px-4 py-2 text-center text-xs font-bold text-white"
    >
        {{ t('common.alphaNotice') }}
    </div>
</template>
