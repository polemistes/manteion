<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';

export interface PaginationSummary {
    current_page: number;
    last_page: number;
    total: number;
    previous_url: string | null;
    next_url: string | null;
}

const props = defineProps<{
    pagination: PaginationSummary;
    only?: string[];
}>();

const { t } = useI18n();
</script>

<template>
    <nav
        v-if="props.pagination.last_page > 1"
        class="mt-8 flex items-center justify-between gap-3"
        :aria-label="t('pagination.label')"
    >
        <Button
            v-if="props.pagination.previous_url"
            :as="Link"
            :href="props.pagination.previous_url"
            :only="props.only"
            preserve-scroll
            :icon="ChevronLeft"
            size="sm"
        >
            {{ t('pagination.previous') }}
        </Button>
        <span v-else />

        <span class="text-meta text-ink-muted tabular-nums">
            {{
                t('pagination.pageOf', {
                    page: props.pagination.current_page,
                    total: props.pagination.last_page,
                })
            }}
        </span>

        <Button
            v-if="props.pagination.next_url"
            :as="Link"
            :href="props.pagination.next_url"
            :only="props.only"
            preserve-scroll
            :icon-end="ChevronRight"
            size="sm"
        >
            {{ t('pagination.next') }}
        </Button>
        <span v-else />
    </nav>
</template>
