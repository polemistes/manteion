<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { exit as exitImpersonation } from '@/routes/admin/impersonate';

const { t } = useI18n();

const page = usePage();
</script>

<template>
    <div
        class="flex items-center justify-between gap-4 border-b-frame border-edge bg-yellow-200 px-4 py-2 text-sm"
    >
        <span>{{
            t('moderation.viewingAs', { name: page.props.auth.user.name })
        }}</span>
        <Link
            :href="exitImpersonation().url"
            method="post"
            as="button"
            class="border border-edge bg-surface-raised px-3 py-1 text-xs font-bold hover:bg-accent hover:text-on-accent"
        >
            {{ t('moderation.returnToAdmin') }}
        </Link>
    </div>
</template>
