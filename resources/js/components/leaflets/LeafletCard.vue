<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Ellipsis,
    Flag,
    Link2,
    MessageSquare,
    Pencil,
    Trash,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ReactionBar from '@/components/ReactionBar.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Menu from '@/components/ui/Menu.vue';
import MenuItem from '@/components/ui/MenuItem.vue';
import Tag from '@/components/ui/Tag.vue';
import { confirmAction } from '@/composables/useConfirm';
import { report } from '@/composables/useReport';
import type { LeafletSummary } from '@/leaflets';
import { useDates } from '@/lib/dates';
import { linkify } from '@/lib/text';
import {
    destroy as destroyLeaflet,
    edit as editLeaflet,
    show as showLeaflet,
} from '@/routes/leaflets';
import { show as usersShow } from '@/routes/users';

/**
 * A leaflet: who wrote it and for whom, the note itself (in the reading
 * serif once it runs long), a picture or a link preview, one reaction pill
 * and the way to the responses. In the feed a long note is cut short, and
 * the card opens the leaflet.
 */
const props = withDefaults(
    defineProps<{
        leaflet: LeafletSummary;
        /** On the leaflet's own page: the whole text, no link to itself. */
        full?: boolean;
    }>(),
    { full: false },
);

const { t } = useI18n();
const dates = useDates();

const long = computed(() => props.leaflet.body.length > 280);
const cut = computed(() => !props.full && props.leaflet.body.length > 700);

const showUrl = computed(() => showLeaflet(props.leaflet.id).url);

async function remove(): Promise<void> {
    const confirmed = await confirmAction({
        title: t('leaflets.card.confirmDeleteTitle'),
        message: t('leaflets.card.confirmDelete'),
        confirmLabel: t('leaflets.card.delete'),
    });

    if (confirmed) {
        router.delete(destroyLeaflet(props.leaflet.id).url, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <article class="rounded-lg border border-line bg-surface">
        <header class="flex items-center gap-3 px-4 pt-4 sm:px-5 sm:pt-5">
            <Link
                :href="usersShow(props.leaflet.owner.id).url"
                class="shrink-0"
            >
                <Avatar
                    :name="props.leaflet.owner.name"
                    :src="props.leaflet.owner.avatar_url"
                    :size="40"
                />
            </Link>
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <Link
                        :href="usersShow(props.leaflet.owner.id).url"
                        class="relative min-w-0 font-semibold text-ink after:absolute after:-inset-x-1 after:-top-3 after:bottom-0 hover:underline"
                        ><span class="block truncate">{{
                            props.leaflet.owner.name
                        }}</span></Link
                    >
                    <Tag tone="host">{{
                        t(`leaflets.visibility.${props.leaflet.visibility}`)
                    }}</Tag>
                </p>
                <p class="text-meta text-ink-muted">
                    <Link
                        v-if="!props.full"
                        :href="showUrl"
                        class="relative after:absolute after:-inset-x-1 after:top-0 after:-bottom-3 hover:underline"
                        ><time :datetime="props.leaflet.created_at">{{
                            dates.ago(props.leaflet.created_at)
                        }}</time></Link
                    >
                    <time v-else :datetime="props.leaflet.created_at">{{
                        dates.dateTime(props.leaflet.created_at)
                    }}</time>
                    <template v-if="props.leaflet.is_edited">
                        · {{ t('leaflets.card.edited') }}</template
                    >
                </p>
            </div>
            <Menu
                v-if="
                    props.leaflet.can_edit ||
                    props.leaflet.can_delete ||
                    props.leaflet.can_report
                "
            >
                <template #trigger>
                    <button
                        type="button"
                        :aria-label="t('leaflets.card.actions')"
                        class="grid size-11 shrink-0 place-items-center rounded-full text-ink-muted hover:bg-surface-2 hover:text-ink"
                    >
                        <Ellipsis
                            :size="20"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                    </button>
                </template>
                <MenuItem
                    v-if="props.leaflet.can_edit"
                    :icon="Pencil"
                    @select="router.visit(editLeaflet(props.leaflet.id).url)"
                >
                    {{ t('leaflets.card.edit') }}
                </MenuItem>
                <MenuItem
                    v-if="props.leaflet.can_report"
                    :icon="Flag"
                    @select="report('leaflet', props.leaflet.id)"
                >
                    {{ t('leaflets.card.report') }}
                </MenuItem>
                <MenuItem
                    v-if="props.leaflet.can_delete"
                    :icon="Trash"
                    danger
                    @select="remove"
                >
                    {{ t('leaflets.card.delete') }}
                </MenuItem>
            </Menu>
        </header>

        <div class="px-4 pt-3 sm:px-5">
            <p
                class="whitespace-pre-line text-ink"
                :class="[
                    long ? 'font-display text-reading' : 'text-body',
                    cut ? 'line-clamp-[12]' : '',
                ]"
            >
                <template
                    v-for="(segment, index) in linkify(props.leaflet.body)"
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
            <Link
                v-if="cut"
                :href="showUrl"
                class="relative mt-1 inline-block text-label font-semibold text-ink underline underline-offset-2 after:absolute after:-inset-x-1 after:-inset-y-3"
            >
                {{ t('leaflets.card.readMore') }}
            </Link>
        </div>

        <div
            v-if="props.leaflet.image_url"
            class="mx-4 mt-3 overflow-hidden rounded-md bg-surface-2 sm:mx-5"
        >
            <img
                :src="props.leaflet.image_url"
                alt=""
                class="mx-auto block max-h-[30rem] w-auto max-w-full"
                loading="lazy"
            />
        </div>

        <a
            v-else-if="props.leaflet.link"
            :href="props.leaflet.link.url"
            target="_blank"
            rel="noopener noreferrer"
            class="mx-4 mt-3 flex items-center gap-3 rounded-md border border-line bg-canvas p-2.5 pr-3.5 transition-colors duration-120 hover:bg-surface-2 sm:mx-5"
        >
            <img
                v-if="props.leaflet.link.image_url"
                :src="props.leaflet.link.image_url"
                alt=""
                class="size-16 shrink-0 rounded-sm bg-surface-2 object-cover"
                loading="lazy"
            />
            <span
                v-else
                class="grid size-16 shrink-0 place-items-center rounded-sm bg-surface-2 text-ink-muted"
                aria-hidden="true"
            >
                <Link2 :size="22" :stroke-width="1.75" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="line-clamp-2 font-semibold text-ink">{{
                    props.leaflet.link.title ?? props.leaflet.link.url
                }}</span>
                <span class="mt-0.5 block truncate text-meta text-ink-muted">{{
                    props.leaflet.link.host ?? props.leaflet.link.url
                }}</span>
            </span>
        </a>

        <footer
            class="mt-2 flex flex-wrap items-center gap-2 px-3 pb-3 sm:px-4 sm:pb-4"
        >
            <ReactionBar
                reactable-type="leaflet"
                :reactable-id="props.leaflet.id"
                :reactions="props.leaflet.reactions"
                :compact="!props.full"
            />
            <Link
                v-if="!props.full"
                :href="`${showUrl}#responses`"
                class="relative ml-auto inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-label font-semibold text-ink-muted after:absolute after:inset-x-0 after:-inset-y-1 hover:bg-surface-2 hover:text-ink"
            >
                <MessageSquare
                    :size="16"
                    :stroke-width="2"
                    aria-hidden="true"
                />
                {{
                    props.leaflet.responses_count
                        ? t(
                              'leaflets.card.responses',
                              props.leaflet.responses_count,
                          )
                        : t('leaflets.card.respond')
                }}
            </Link>
        </footer>
    </article>
</template>
