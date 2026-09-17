<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

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
        class="mt-6 flex items-center justify-between text-sm"
        :aria-label="t('pagination.label')"
    >
        <Link
            v-if="props.pagination.previous_url"
            :href="props.pagination.previous_url"
            :only="props.only"
            preserve-scroll
            class="border border-black px-3 py-2 hover:bg-black hover:text-white"
        >
            {{ t('pagination.previous') }}
        </Link>
        <span v-else />

        <span class="text-xs text-black/70">
            {{
                t('pagination.pageOf', {
                    page: props.pagination.current_page,
                    total: props.pagination.last_page,
                })
            }}
        </span>

        <Link
            v-if="props.pagination.next_url"
            :href="props.pagination.next_url"
            :only="props.only"
            preserve-scroll
            class="border border-black px-3 py-2 hover:bg-black hover:text-white"
        >
            {{ t('pagination.next') }}
        </Link>
        <span v-else />
    </nav>
</template>
