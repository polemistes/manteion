<script setup lang="ts">
import { Flag } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import { isReported, report } from '@/composables/useReport';
import type { ReportableType } from '@/composables/useReport';

/** A quiet "Report" button that opens the shell's report dialog. */
const props = defineProps<{
    reportableType: ReportableType;
    reportableId: number;
}>();

const { t } = useI18n();

const done = computed(() =>
    isReported(props.reportableType, props.reportableId),
);
</script>

<template>
    <span v-if="done" class="text-meta text-ink-subtle">{{
        t('moderation.reported')
    }}</span>
    <Button
        v-else
        variant="ghost"
        size="sm"
        :icon="Flag"
        @click="report(props.reportableType, props.reportableId)"
    >
        {{ t('moderation.report') }}
    </Button>
</template>
