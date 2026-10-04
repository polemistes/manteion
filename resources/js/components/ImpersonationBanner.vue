<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import { exit as exitImpersonation } from '@/routes/admin/impersonate';

/** Shown above everything while an administrator sees the app as someone else. */
const { t } = useI18n();

const page = usePage();
</script>

<template>
    <div
        class="flex items-center justify-between gap-3 bg-warning-soft px-4 py-2 pt-[calc(8px+env(safe-area-inset-top))] text-label text-warning"
    >
        <span class="min-w-0">{{
            t('moderation.viewingAs', { name: page.props.auth.user.name })
        }}</span>
        <Button
            :as="Link"
            :href="exitImpersonation().url"
            method="post"
            size="sm"
        >
            {{ t('moderation.returnToAdmin') }}
        </Button>
    </div>
</template>
