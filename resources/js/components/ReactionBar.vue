<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EmojiPicker from '@/components/EmojiPicker.vue';
import { capitalize, loadEmojiNames } from '@/emojiNames';
import type { ReactableType, ReactionSummary } from '@/reactions';
import { store as reactionsStore } from '@/routes/reactions';

const props = defineProps<{
    reactableType: ReactableType;
    reactableId: number;
    reactions: ReactionSummary[];
}>();

const { t } = useI18n();

// A local copy that changes at once on click, so the chips respond before
// the round trip; the next page refresh replaces it with the server's view.
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

function nameOf(emoji: string): string {
    const name = names.value?.get(emoji);

    return name ? capitalize(name) : emoji;
}

// Names are fetched the first time the pointer reaches the bar, so the
// tooltip is ready by the time a chip is hovered.
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

// One reaction per person: the same emoji again removes it, another emoji
// replaces it. The server applies the same rule.
function toggle(emoji: string): void {
    const current = mine.value;

    if (current) {
        adjust(current.emoji, -1);
    }

    if (!current || current.emoji !== emoji) {
        adjust(emoji, 1);
    }

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

function removeMine(): void {
    if (mine.value) {
        toggle(mine.value.emoji);
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1" @pointerenter="ensureNames">
        <button
            v-for="reaction in reactions"
            :key="reaction.emoji"
            type="button"
            :aria-pressed="reaction.reacted"
            :title="nameOf(reaction.emoji)"
            class="border px-2 py-0.5 text-xs"
            :class="
                reaction.reacted
                    ? 'border-accent bg-accent text-on-accent'
                    : 'border-edge hover:bg-surface'
            "
            @click="toggle(reaction.emoji)"
        >
            {{ reaction.emoji }} {{ reaction.count }}
        </button>
        <button
            v-if="mine"
            type="button"
            :title="t('reactions.removeMine', { name: nameOf(mine.emoji) })"
            :aria-label="
                t('reactions.removeMine', { name: nameOf(mine.emoji) })
            "
            class="border border-edge px-2 py-0.5 text-xs text-ink/70 hover:bg-accent hover:text-on-accent"
            @click="removeMine"
        >
            −
        </button>
        <button
            v-else
            type="button"
            :title="t('reactions.pick')"
            :aria-label="t('reactions.pick')"
            class="border border-edge px-2 py-0.5 text-xs text-ink/70 hover:bg-accent hover:text-on-accent"
            @click="pickerOpen = true"
        >
            +
        </button>

        <EmojiPicker
            :open="pickerOpen"
            @update:open="pickerOpen = $event"
            @select="toggle"
        />
    </div>
</template>
