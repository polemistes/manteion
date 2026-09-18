<script setup lang="ts">
import { Form, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FieldError from '@/components/FieldError.vue';
import PersonSummary from '@/components/PersonSummary.vue';
import ReactionBar from '@/components/ReactionBar.vue';
import ReportButton from '@/components/ReportButton.vue';
import { confirmAction } from '@/composables/useConfirm';
import type { LeafletResponseSummary, LeafletSummary } from '@/leaflets';
import {
    destroy as destroyLeaflet,
    edit as editLeaflet,
} from '@/routes/leaflets';
import * as leafletResponses from '@/routes/leaflets/responses';

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

const responseBody = ref('');

async function removeResponse(response: LeafletResponseSummary): Promise<void> {
    const confirmed = await confirmAction({
        message: t('leaflets.responses.confirmDelete'),
    });

    if (confirmed) {
        router.delete(
            leafletResponses.destroy({
                leaflet: props.leaflet.id,
                response: response.id,
            }).url,
            { preserveScroll: true },
        );
    }
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

        <p
            class="rounded-card bg-surface-inset px-4 py-3 font-sans text-lg whitespace-pre-line"
        >
            {{ props.leaflet.body }}
        </p>

        <img
            v-if="props.leaflet.image_url"
            :src="props.leaflet.image_url"
            alt=""
            class="mt-3 block max-h-96 max-w-full border border-edge"
        />

        <a
            v-else-if="props.leaflet.link"
            :href="props.leaflet.link.url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-3 block w-fit max-w-full border border-edge hover:bg-surface-inset sm:max-w-md"
        >
            <!-- Shown at natural size, capped, so a small logo is not blown up. -->
            <img
                v-if="props.leaflet.link.image_url"
                :src="props.leaflet.link.image_url"
                alt=""
                class="block max-h-64 max-w-full border-b border-edge"
            />
            <!-- No image from the site: a band with its name stands in. -->
            <span
                v-else
                class="flex h-24 items-center justify-center gap-2 border-b border-edge bg-surface px-3 text-lg font-bold"
            >
                <span aria-hidden="true">🔗</span>
                <span class="truncate">{{
                    props.leaflet.link.host ?? props.leaflet.link.url
                }}</span>
            </span>
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

        <div class="mt-3">
            <ReactionBar
                reactable-type="leaflet"
                :reactable-id="props.leaflet.id"
                :reactions="props.leaflet.reactions"
            />
        </div>

        <section class="mt-4 border-t border-edge pt-3">
            <h3 class="mb-2 text-xs font-bold uppercase">
                {{ t('leaflets.responses.heading') }}
            </h3>

            <ul class="mb-3 flex flex-col gap-3">
                <li
                    v-for="response in props.leaflet.responses"
                    :key="response.id"
                    class="border-l-2 border-edge pl-3"
                >
                    <div
                        class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                    >
                        <PersonSummary v-bind="response.user" />
                        <span class="text-xs text-ink/70">{{
                            formatTime(response.created_at)
                        }}</span>
                        <button
                            v-if="response.can_delete"
                            type="button"
                            class="text-xs text-ink/50 hover:underline"
                            @click="removeResponse(response)"
                        >
                            {{ t('leaflets.responses.delete') }}
                        </button>
                        <ReportButton
                            v-if="response.can_report"
                            reportable-type="leaflet_response"
                            :reportable-id="response.id"
                        />
                    </div>
                    <p class="mt-1 font-sans text-sm whitespace-pre-line">
                        {{ response.body }}
                    </p>
                    <div class="mt-1">
                        <ReactionBar
                            reactable-type="leaflet_response"
                            :reactable-id="response.id"
                            :reactions="response.reactions"
                        />
                    </div>
                </li>
            </ul>

            <Form
                v-bind="leafletResponses.store.form(props.leaflet.id)"
                #default="{ errors, processing }"
                class="flex flex-col gap-2"
                @success="responseBody = ''"
            >
                <label
                    :for="`leaflet-response-${props.leaflet.id}`"
                    class="sr-only"
                    >{{ t('leaflets.responses.placeholder') }}</label
                >
                <textarea
                    :id="`leaflet-response-${props.leaflet.id}`"
                    v-model="responseBody"
                    name="body"
                    required
                    rows="2"
                    maxlength="2000"
                    :placeholder="t('leaflets.responses.placeholder')"
                    class="w-full border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                ></textarea>
                <FieldError :message="errors.body" />
                <button
                    type="submit"
                    :disabled="processing"
                    class="w-fit border border-edge px-3 py-1 text-xs hover:bg-accent hover:text-on-accent disabled:opacity-50"
                >
                    {{
                        processing
                            ? t('leaflets.responses.sending')
                            : t('leaflets.responses.send')
                    }}
                </button>
            </Form>
        </section>

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
