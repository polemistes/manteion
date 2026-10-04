<script setup lang="ts">
import { Form, Link, router, usePage } from '@inertiajs/vue3';
import { Ellipsis, Flag, SendHorizontal, Trash } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ReactionBar from '@/components/ReactionBar.vue';
import Avatar from '@/components/ui/Avatar.vue';
import IconButton from '@/components/ui/IconButton.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import TextArea from '@/components/ui/TextArea.vue';
import { confirmAction } from '@/composables/useConfirm';
import { report } from '@/composables/useReport';
import type { LeafletResponseSummary } from '@/leaflets';
import { useDates } from '@/lib/dates';
import { linkify } from '@/lib/text';
import {
    destroy as destroyResponse,
    store as storeResponse,
} from '@/routes/leaflets/responses';
import { show as usersShow } from '@/routes/users';

/** The responses to a leaflet, oldest first, and a field to add one. */
const props = defineProps<{
    leafletId: number;
    responses: LeafletResponseSummary[];
}>();

const { t } = useI18n();
const page = usePage();
const dates = useDates();

const text = ref('');

async function remove(response: LeafletResponseSummary): Promise<void> {
    const confirmed = await confirmAction({
        title: t('leaflets.responses.confirmDeleteTitle'),
        message: t('leaflets.responses.confirmDelete'),
        confirmLabel: t('leaflets.responses.delete'),
    });

    if (confirmed) {
        router.delete(
            destroyResponse({
                leaflet: props.leafletId,
                response: response.id,
            }).url,
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <section id="responses" class="scroll-mt-20">
        <h2 class="mb-4 flex items-baseline gap-2 font-display text-title-3">
            {{ t('leaflets.responses.heading') }}
            <span
                v-if="props.responses.length"
                class="font-sans text-meta font-normal text-ink-muted"
                >{{ props.responses.length }}</span
            >
        </h2>

        <p
            v-if="!props.responses.length"
            class="mb-5 font-display text-input text-ink-muted italic"
        >
            {{ t('leaflets.responses.none') }}
        </p>

        <ul class="mb-5 flex flex-col gap-5">
            <li
                v-for="response in props.responses"
                :key="response.id"
                class="flex gap-3"
            >
                <Link :href="usersShow(response.user.id).url" class="shrink-0">
                    <Avatar
                        :name="response.user.name"
                        :src="response.user.avatar_url"
                        :size="36"
                    />
                </Link>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <Link
                            :href="usersShow(response.user.id).url"
                            class="text-label font-semibold text-ink hover:underline"
                            >{{ response.user.name }}</Link
                        >
                        <span class="text-meta text-ink-subtle">{{
                            dates.ago(response.created_at)
                        }}</span>
                    </p>
                    <p class="mt-0.5 whitespace-pre-line text-ink">
                        <template
                            v-for="(segment, index) in linkify(response.body)"
                            :key="index"
                        >
                            <a
                                v-if="segment.kind === 'link'"
                                :href="segment.href"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="underline underline-offset-2"
                                >{{ segment.value }}</a
                            >
                            <template v-else>{{ segment.value }}</template>
                        </template>
                    </p>
                    <div class="mt-1.5 flex items-center gap-1">
                        <ReactionBar
                            reactable-type="leaflet_response"
                            :reactable-id="response.id"
                            :reactions="response.reactions"
                        />
                        <Menu
                            v-if="response.can_delete || response.can_report"
                            align="start"
                        >
                            <template #trigger>
                                <button
                                    type="button"
                                    :aria-label="
                                        t('leaflets.responses.actions')
                                    "
                                    class="grid size-9 place-items-center rounded-full text-ink-subtle hover:bg-surface-2 hover:text-ink"
                                >
                                    <Ellipsis
                                        :size="16"
                                        :stroke-width="2"
                                        aria-hidden="true"
                                    />
                                </button>
                            </template>
                            <MenuItem
                                v-if="response.can_report"
                                :icon="Flag"
                                @select="
                                    report('leaflet_response', response.id)
                                "
                            >
                                {{ t('leaflets.card.report') }}
                            </MenuItem>
                            <MenuItem
                                v-if="response.can_delete"
                                :icon="Trash"
                                danger
                                @select="remove(response)"
                            >
                                {{ t('leaflets.responses.delete') }}
                            </MenuItem>
                        </Menu>
                    </div>
                </div>
            </li>
        </ul>

        <Form
            v-bind="storeResponse.form(props.leafletId)"
            reset-on-success
            :options="{ preserveScroll: true }"
            class="flex items-start gap-3"
            @success="text = ''"
            #default="{ errors, processing }"
        >
            <Avatar
                :name="page.props.auth.user.name"
                :src="page.props.auth.user.avatar_url"
                :size="36"
                decorative
                class="mt-1"
            />
            <div class="min-w-0 flex-1">
                <div
                    class="flex min-h-11 items-end rounded-xl bg-surface pr-1 pl-4 ring-1 ring-line-strong ring-inset focus-within:ring-2 focus-within:ring-ink"
                >
                    <div class="min-w-0 flex-1">
                        <TextArea
                            v-model="text"
                            name="body"
                            :label="t('leaflets.responses.placeholder')"
                            hide-label
                            bare
                            :max-rows="8"
                            rows="1"
                            :placeholder="t('leaflets.responses.placeholder')"
                            required
                            maxlength="2000"
                            class="py-2.5"
                        />
                    </div>
                    <IconButton
                        type="submit"
                        :icon="SendHorizontal"
                        :label="t('leaflets.responses.send')"
                        :disabled="processing || !text.trim()"
                        class="mb-0.5"
                    />
                </div>
                <p
                    v-if="errors.body"
                    class="mt-1.5 text-meta font-medium text-danger"
                >
                    {{ errors.body }}
                </p>
            </div>
        </Form>
    </section>
</template>
