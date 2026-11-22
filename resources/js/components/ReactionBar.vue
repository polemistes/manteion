<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { SmilePlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EmojiPicker from '@/components/EmojiPicker.vue';
import IconButton from '@/components/ui/IconButton.vue';
import { capitalize, loadEmojiNames } from '@/emojiNames';
import type { ReactableType, ReactionSummary } from '@/reactions';
import { store as reactionsStore } from '@/routes/reactions';

/**
 * Emoji reactions as small pills, plus a button to add one. One reaction
 * per person: tapping your own pill takes it back, another emoji replaces
 * it. The pills change at once; the server applies the same rule.
 */
const props = withDefaults(
    defineProps<{
        reactableType: ReactableType;
        reactableId: number;
        reactions: ReactionSummary[];
        addable?: boolean;
        /**
         * One pill for all of them (the three most used and the total),
         * opening the picker: for cards in a feed.
         */
        compact?: boolean;
    }>(),
    { addable: true, compact: false },
);

const emit = defineEmits<{ changed: [reactions: ReactionSummary[]] }>();

const { t } = useI18n();

const reactions = ref<ReactionSummary[]>(
    props.reactions.map((r) => ({ ...r })),
);
const pickerOpen = ref(false);
const names = ref<Map<string, string> | null>(null);

watch(
    () => props.reactions,
    (next) => {
        reactions.value = next.map((r) => ({ ...r }));
    },
);

const mine = computed(() => reactions.value.find((r) => r.reacted) ?? null);

const total = computed(() =>
    reactions.value.reduce((sum, reaction) => sum + reaction.count, 0),
);

const topEmojis = computed(() =>
    [...reactions.value]
        .sort((a, b) => b.count - a.count)
        .slice(0, 3)
        .map((reaction) => reaction.emoji),
);

const summaryLabel = computed(() =>
    t('reactions.summary', {
        list: reactions.value
            .map((reaction) => `${nameOf(reaction.emoji)} ${reaction.count}`)
            .join(', '),
    }),
);

function nameOf(emoji: string): string {
    const name = names.value?.get(emoji);

    return name ? capitalize(name) : emoji;
}

// Names are fetched the first time the pointer reaches the bar, so the
// tooltip is ready by the time a pill is hovered.
async function ensureNames(): Promise<void> {
    if (names.value === null) {
        names.value = await loadEmojiNames();
    }
}

function adjust(emoji: string, delta: number): void {
    const existing = reactions.value.find((r) => r.emoji === emoji);

    if (existing) {
        existing.count += delta;
        existing.reacted = delta > 0;
    } else if (delta > 0) {
        reactions.value = [
            ...reactions.value,
            { emoji, count: 1, reacted: true },
        ];
    }

    reactions.value = reactions.value.filter((r) => r.count > 0);
}

function toggle(emoji: string): void {
    const current = mine.value;

    if (current) {
        adjust(current.emoji, -1);
    }

    if (!current || current.emoji !== emoji) {
        adjust(emoji, 1);
    }

    emit('changed', reactions.value);

    router.post(
        reactionsStore().url,
        {
            reactable_type: props.reactableType,
            reactable_id: props.reactableId,
            emoji,
        },
        { preserveScroll: true, preserveState: true },
    );
}

defineExpose({ toggle });
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-1.5"
        @pointerenter="ensureNames"
    >
        <button
            v-if="props.compact && reactions.length"
            type="button"
            :aria-label="summaryLabel"
            :title="summaryLabel"
            class="relative inline-flex h-8 items-center gap-1.5 rounded-full px-2.5 text-caption font-semibold tabular-nums ring-1 transition-colors duration-120 ring-inset after:absolute after:inset-x-0 after:-inset-y-1.5"
            :class="
                mine
                    ? 'bg-accent-soft text-accent-ink ring-accent-line'
                    : 'bg-surface text-ink-muted ring-line hover:bg-surface-2'
            "
            @click="pickerOpen = true"
        >
            <span class="flex text-[15px] leading-none">
                <span v-for="emoji in topEmojis" :key="emoji">{{ emoji }}</span>
            </span>
            {{ total }}
        </button>
        <button
            v-for="reaction in props.compact ? [] : reactions"
            :key="reaction.emoji"
            type="button"
            :aria-pressed="reaction.reacted"
            :aria-label="
                reaction.reacted
                    ? t('reactions.removeMine', {
                          name: nameOf(reaction.emoji),
                      })
                    : t('reactions.add', { emoji: nameOf(reaction.emoji) })
            "
            :title="nameOf(reaction.emoji)"
            class="relative inline-flex h-8 animate-[pop-in_160ms_var(--ease-enter)] items-center gap-1 rounded-full px-2.5 text-caption font-semibold tabular-nums ring-1 transition-colors duration-120 ring-inset after:absolute after:inset-x-0 after:-inset-y-1.5"
            :class="
                reaction.reacted
                    ? 'bg-accent-soft text-accent-ink ring-accent-line'
                    : 'bg-surface text-ink-muted ring-line hover:bg-surface-2'
            "
            @click="toggle(reaction.emoji)"
        >
            <span class="text-[15px] leading-none">{{ reaction.emoji }}</span>
            {{ reaction.count }}
        </button>
        <IconButton
            v-if="props.addable && !(props.compact && reactions.length)"
            :icon="SmilePlus"
            :label="t('reactions.pick')"
            size="sm"
            @click="pickerOpen = true"
        />

        <EmojiPicker v-model:open="pickerOpen" @select="toggle" />
    </div>
</template>
