<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import EmojiPicker from '@/components/EmojiPicker.vue';
import type { ReactableType, ReactionSummary } from '@/reactions';
import { store as reactionsStore } from '@/routes/reactions';

const props = defineProps<{
    reactableType: ReactableType;
    reactableId: number;
    reactions: ReactionSummary[];
}>();

const { t } = useI18n();

// A local copy that flips at once on click, so the chip responds before the
// round trip; the next page refresh replaces it with the server's view.
const reactions = ref<ReactionSummary[]>(
    props.reactions.map((r) => ({ ...r })),
);
const pickerOpen = ref(false);

watch(
    () => props.reactions,
    (next) => {
        reactions.value = next.map((r) => ({ ...r }));
    },
);

function toggle(emoji: string): void {
    const existing = reactions.value.find((r) => r.emoji === emoji);

    if (existing) {
        existing.reacted = !existing.reacted;
        existing.count += existing.reacted ? 1 : -1;
        reactions.value = reactions.value.filter((r) => r.count > 0);
    } else {
        reactions.value = [
            ...reactions.value,
            { emoji, count: 1, reacted: true },
        ];
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
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <button
            v-for="reaction in reactions"
            :key="reaction.emoji"
            type="button"
            :aria-pressed="reaction.reacted"
            :title="
                reaction.reacted
                    ? t('reactions.remove', { emoji: reaction.emoji })
                    : t('reactions.add', { emoji: reaction.emoji })
            "
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
