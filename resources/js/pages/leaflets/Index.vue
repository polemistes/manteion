<script setup lang="ts">
import { router, usePage, usePoll } from '@inertiajs/vue3';
import { Newspaper, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LeafletCard from '@/components/leaflets/LeafletCard.vue';
import LeafletForm from '@/components/leaflets/LeafletForm.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import PageContainer from '@/components/shell/PageContainer.vue';
import PageHeader from '@/components/shell/PageHeader.vue';
import AppDialog from '@/components/ui/AppDialog.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import IconButton from '@/components/ui/IconButton.vue';
import { leafletVisibilities } from '@/leaflets';
import type { LeafletSummary, LeafletVisibility } from '@/leaflets';
import { index as leafletsIndex, store } from '@/routes/leaflets';

/**
 * Leaflets: short notes to friends and followers (a question, a tip,
 * something to lend), newest first, narrowed by who they were for.
 */
const props = defineProps<{
    leaflets: LeafletSummary[];
    pagination: PaginationSummary;
    scope: LeafletVisibility | null;
}>();

const { t } = useI18n();
const page = usePage();

usePoll(60_000, { only: ['leaflets', 'pagination'] });

const scopes = computed(() => [
    { value: null, label: t('leaflets.index.scopeAll') },
    ...leafletVisibilities.map((value) => ({
        value,
        label: t(`leaflets.visibility.${value}`),
    })),
]);

function setScope(scope: LeafletVisibility | null): void {
    router.get(leafletsIndex().url, scope ? { scope } : {}, {
        preserveState: true,
        replace: true,
        only: ['leaflets', 'pagination', 'scope'],
    });
}

const composeOpen = ref(false);
</script>

<template>
    <PageHeader
        :title="t('leaflets.index.title')"
        :lede="t('leaflets.index.lede')"
        root
    >
        <template #phone-actions>
            <IconButton
                :icon="Plus"
                :label="t('leaflets.index.compose')"
                variant="create"
                @click="composeOpen = true"
            />
        </template>
        <template #actions>
            <Button :icon="Plus" variant="primary" @click="composeOpen = true">
                {{ t('leaflets.index.compose') }}
            </Button>
        </template>
    </PageHeader>

    <PageContainer class="pt-3 pb-16 sm:pt-0">
        <button
            type="button"
            class="flex w-full items-center gap-3 rounded-lg border border-line bg-surface p-3 text-left transition-colors duration-120 hover:bg-surface-2/55 sm:p-4"
            @click="composeOpen = true"
        >
            <Avatar
                :name="page.props.auth.user.name"
                :src="page.props.auth.user.avatar_url"
                :size="40"
                decorative
            />
            <span
                class="flex h-11 min-w-0 flex-1 items-center rounded-full bg-canvas px-4 text-input text-ink-subtle ring-1 ring-line ring-inset"
                >{{ t('leaflets.index.prompt') }}</span
            >
        </button>

        <div
            class="-mx-4 mt-4 scrollbar-none flex gap-2 overflow-x-auto fade-end px-4 pb-1 sm:mx-0 sm:flex-wrap sm:[mask-image:none] sm:px-0"
            role="group"
            :aria-label="t('leaflets.index.scope')"
        >
            <FilterChip
                v-for="option in scopes"
                :key="option.value ?? 'all'"
                :pressed="props.scope === option.value"
                @update:pressed="setScope(option.value)"
            >
                {{ option.label }}
            </FilterChip>
        </div>

        <ul v-if="props.leaflets.length" class="mt-5 flex flex-col gap-4">
            <li v-for="leaflet in props.leaflets" :key="leaflet.id">
                <LeafletCard :leaflet="leaflet" />
            </li>
        </ul>
        <EmptyState
            v-else
            :icon="Newspaper"
            :title="t('leaflets.index.emptyTitle')"
            :body="t('leaflets.index.empty')"
        >
            <Button :icon="Plus" variant="primary" @click="composeOpen = true">
                {{ t('leaflets.index.compose') }}
            </Button>
        </EmptyState>

        <Pagination
            :pagination="props.pagination"
            :only="['leaflets', 'pagination']"
        />
    </PageContainer>

    <AppDialog
        v-model:open="composeOpen"
        :title="t('leaflets.index.compose')"
        size="lg"
    >
        <LeafletForm
            :form="store.form()"
            :submit-label="t('leaflets.form.send')"
            :submitting-label="t('leaflets.form.sending')"
            reset-on-success
            @saved="composeOpen = false"
        >
            <template #cancel>
                <Button variant="ghost" @click="composeOpen = false">
                    {{ t('ui.cancel') }}
                </Button>
            </template>
        </LeafletForm>
    </AppDialog>
</template>
