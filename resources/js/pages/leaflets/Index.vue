<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LeafletCard from '@/components/LeafletCard.vue';
import LeafletForm from '@/components/LeafletForm.vue';
import Pagination from '@/components/Pagination.vue';
import type { PaginationSummary } from '@/components/Pagination.vue';
import { leafletVisibilities } from '@/leaflets';
import type { LeafletSummary } from '@/leaflets';
import type { LeafletVisibility } from '@/leaflets';
import { index as leafletsIndex, store } from '@/routes/leaflets';

const { t } = useI18n();

const props = defineProps<{
    leaflets: LeafletSummary[];
    pagination: PaginationSummary;
    scope: LeafletVisibility | null;
}>();

usePoll(20000, { only: ['leaflets', 'pagination'] });

const scopes: (LeafletVisibility | null)[] = [null, ...leafletVisibilities];

function scopeUrl(scope: LeafletVisibility | null): string {
    return scope === null
        ? leafletsIndex().url
        : leafletsIndex({ query: { scope } }).url;
}
</script>

<template>
    <Head :title="t('leaflets.index.title')" />

    <h1 class="text-xl mb-6 font-bold">{{ t('leaflets.index.title') }}</h1>

    <section class="border-frame border-edge mb-8 p-4">
        <h2 class="text-sm mb-3 font-bold uppercase">
            {{ t('leaflets.index.compose') }}
        </h2>
        <LeafletForm
            :action="store.form()"
            :submit-label="t('leaflets.form.send')"
            :submitting-label="t('leaflets.form.sending')"
            reset-on-success
        />
    </section>

    <nav
        class="mb-4 flex flex-wrap gap-2"
        :aria-label="t('leaflets.index.scope')"
    >
        <Link
            v-for="scope in scopes"
            :key="scope ?? 'all'"
            :href="scopeUrl(scope)"
            :only="['leaflets', 'pagination', 'scope']"
            preserve-scroll
            class="border-edge text-xs border px-2 py-1"
            :class="
                scope === props.scope
                    ? 'bg-accent text-on-accent'
                    : 'hover:bg-accent hover:text-on-accent'
            "
        >
            {{
                scope === null
                    ? t('leaflets.index.scopeAll')
                    : t(`leaflets.visibility.${scope}`)
            }}
        </Link>
    </nav>

    <p v-if="!props.leaflets.length" class="text-sm text-ink/70">
        {{ t('leaflets.index.empty') }}
    </p>

    <ul class="flex flex-col gap-4">
        <li v-for="leaflet in props.leaflets" :key="leaflet.id">
            <LeafletCard :leaflet="leaflet" />
        </li>
    </ul>

    <Pagination
        :pagination="props.pagination"
        :only="['leaflets', 'pagination']"
    />
</template>
