<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Earth, Lock, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Avatar from '@/components/ui/Avatar.vue';
import Tag from '@/components/ui/Tag.vue';
import { useDates } from '@/lib/dates';

/**
 * The event as it will show up in guests' feeds, drawn from the form while
 * the host fills it in: the cover, when, the name, where and who hosts.
 */
const props = defineProps<{
    name: string;
    startsAt: string | null;
    endsAt: string | null;
    place: string | null;
    cover: { url: string; fit: 'cover' | 'contain' } | null;
    visibility: 'private' | 'public';
    isNew: boolean;
}>();

const { t } = useI18n();
const dates = useDates();
const page = usePage();

const host = computed(() => page.props.auth.user);

const when = computed(() =>
    props.startsAt
        ? dates.span(props.startsAt, props.endsAt)
        : t('events.form.preview.noDate'),
);
</script>

<template>
    <article
        class="overflow-hidden rounded-lg border border-line bg-surface [--avatar-ring:var(--color-surface)]"
    >
        <img
            v-if="props.cover?.fit === 'cover'"
            :src="props.cover.url"
            alt=""
            class="aspect-[3/2] w-full bg-surface-2 object-cover"
        />
        <div
            v-else-if="props.cover"
            class="flex justify-center bg-surface-2 p-4"
        >
            <img
                :src="props.cover.url"
                alt=""
                class="max-h-[200px] w-auto max-w-full rounded-xs object-contain shadow-sm"
            />
        </div>

        <div class="px-4 pt-3.5">
            <p class="text-meta font-semibold text-ink-muted">{{ when }}</p>
            <h3
                class="mt-1 mb-2 line-clamp-3 font-display text-title-3 text-pretty [overflow-wrap:anywhere]"
                :class="props.name.trim() ? 'text-ink' : 'text-ink-subtle'"
            >
                {{ props.name.trim() || t('events.form.preview.untitled') }}
            </h3>
            <ul class="flex flex-col gap-1 text-label text-ink-muted">
                <li v-if="props.place" class="flex min-w-0 items-center gap-2">
                    <MapPin
                        :size="16"
                        :stroke-width="2"
                        class="shrink-0 text-ink-subtle"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ props.place }}</span>
                </li>
                <li class="flex min-w-0 items-center gap-2">
                    <Avatar
                        :name="host.name"
                        :src="host.avatar_url"
                        :size="20"
                        decorative
                    />
                    <span class="truncate">{{ host.name }}</span>
                </li>
            </ul>
        </div>

        <div class="flex items-center gap-3 px-4 pt-3 pb-4">
            <span v-if="props.isNew" class="text-meta text-ink-muted">{{
                t('events.form.preview.noGuests')
            }}</span>
            <Tag
                :icon="props.visibility === 'private' ? Lock : Earth"
                class="ml-auto"
            >
                {{
                    props.visibility === 'private'
                        ? t('events.form.private')
                        : t('events.form.public')
                }}
            </Tag>
        </div>
    </article>
</template>
