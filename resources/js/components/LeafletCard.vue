<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import PersonSummary from '@/components/PersonSummary.vue';
import ReportButton from '@/components/ReportButton.vue';
import { confirmAction } from '@/composables/useConfirm';
import type { LeafletSummary } from '@/leaflets';
import {
    destroy as destroyLeaflet,
    edit as editLeaflet,
} from '@/routes/leaflets';

const props = defineProps<{ leaflet: LeafletSummary }>();

const { t, locale } = useI18n();

const dateFnsLocales: Record<string, string> = {
    en: 'en-GB',
    nb: 'nb-NO',
};

function formatTime(value: string): string {
    return new Date(value).toLocaleString(
        dateFnsLocales[locale.value] ?? locale.value,
        { dateStyle: 'medium', timeStyle: 'short' },
    );
}

async function remove(): Promise<void> {
    const confirmed = await confirmAction({
        message: t('leaflets.card.confirmDelete'),
    });

    if (confirmed) {
        router.delete(destroyLeaflet(props.leaflet.id).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <article class="border-frame border-edge p-4">
        <header
            class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
        >
            <PersonSummary v-bind="props.leaflet.owner" />
            <span class="text-xs text-ink/70">
                {{ formatTime(props.leaflet.created_at) }}
                <template v-if="props.leaflet.is_edited">
                    · {{ t('leaflets.card.edited') }}
                </template>
            </span>
            <span class="border border-edge px-2 py-0.5 text-xs">
                {{ t(`leaflets.visibility.${props.leaflet.visibility}`) }}
            </span>
        </header>

        <p class="font-sans text-sm whitespace-pre-line">
            {{ props.leaflet.body }}
        </p>

        <img
            v-if="props.leaflet.image_url"
            :src="props.leaflet.image_url"
            alt=""
            class="mt-3 max-h-96 w-full border border-edge object-cover"
        />

        <a
            v-else-if="props.leaflet.link"
            :href="props.leaflet.link.url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-3 block border border-edge hover:bg-surface-raised"
        >
            <!-- Shown at natural size, capped, so a small logo is not blown up. -->
            <img
                v-if="props.leaflet.link.image_url"
                :src="props.leaflet.link.image_url"
                alt=""
                class="mx-auto block max-h-64 max-w-full border-b border-edge"
            />
            <span class="block p-3">
                <span class="block text-xs text-ink/70">{{
                    props.leaflet.link.host ?? props.leaflet.link.url
                }}</span>
                <span class="block text-sm font-bold">{{
                    props.leaflet.link.title ?? props.leaflet.link.url
                }}</span>
                <span
                    v-if="props.leaflet.link.description"
                    class="mt-1 line-clamp-3 block font-sans text-xs text-ink/70"
                >
                    {{ props.leaflet.link.description }}
                </span>
            </span>
        </a>

        <footer
            v-if="
                props.leaflet.can_edit ||
                props.leaflet.can_delete ||
                props.leaflet.can_report
            "
            class="mt-3 flex flex-wrap items-center gap-3"
        >
            <Link
                v-if="props.leaflet.can_edit"
                :href="editLeaflet(props.leaflet.id).url"
                class="text-xs underline"
            >
                {{ t('leaflets.card.edit') }}
            </Link>
            <button
                v-if="props.leaflet.can_delete"
                type="button"
                class="text-xs text-ink/50 hover:underline"
                @click="remove"
            >
                {{ t('leaflets.card.delete') }}
            </button>
            <ReportButton
                v-if="props.leaflet.can_report"
                reportable-type="leaflet"
                :reportable-id="props.leaflet.id"
            />
        </footer>
    </article>
</template>
