<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import SearchField from '@/components/ui/SearchField.vue';

interface EmojiData {
    groups: string[];
    emoji: [number, string, string][];
}

/**
 * Every emoji, by group or by name, in the sheet/dialog. The list (about
 * 1900 entries) loads the first time the picker opens, not with the page.
 */
const emit = defineEmits<{ select: [emoji: string] }>();

const open = defineModel<boolean>('open', { default: false });

const { t } = useI18n();

const data = ref<EmojiData | null>(null);
const group = ref(0);
const query = ref('');

async function load(): Promise<void> {
    if (data.value === null) {
        data.value = (await import('@/emoji-data.json')).default as EmojiData;
    }
}

watch(
    open,
    (isOpen) => {
        if (isOpen) {
            void load();
            query.value = '';
        }
    },
    { immediate: true },
);

const visible = computed(() => {
    if (data.value === null) {
        return [];
    }

    const term = query.value.trim().toLowerCase();

    if (term !== '') {
        return data.value.emoji.filter(([, , name]) => name.includes(term));
    }

    return data.value.emoji.filter(([g]) => g === group.value);
});

// The first emoji of each group stands in as its tab icon.
const groupIcons = computed(() =>
    data.value === null
        ? []
        : data.value.groups.map(
              (_, index) =>
                  data.value!.emoji.find(([g]) => g === index)?.[1] ?? '',
          ),
);

function choose(emoji: string): void {
    emit('select', emoji);
    open.value = false;
}
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('reactions.pickerTitle')"
        size="lg"
    >
        <SearchField v-model="query" :label="t('reactions.search')" />

        <div
            v-if="data && !query.trim()"
            class="mt-3 scrollbar-none flex gap-1 overflow-x-auto"
            role="tablist"
            :aria-label="t('reactions.pickerTitle')"
        >
            <button
                v-for="(icon, index) in groupIcons"
                :key="index"
                type="button"
                role="tab"
                :aria-selected="group === index"
                :aria-label="t(`reactions.groups.${index}`)"
                :title="t(`reactions.groups.${index}`)"
                class="grid size-11 shrink-0 place-items-center rounded-md text-[22px] leading-none transition-colors duration-120"
                :class="group === index ? 'bg-surface-2' : 'hover:bg-surface-2'"
                @click="group = index"
            >
                {{ icon }}
            </button>
        </div>

        <p v-if="!data" class="mt-4 text-ink-muted">
            {{ t('reactions.loading') }}
        </p>

        <div
            v-else
            class="mt-3 h-[min(46dvh,360px)] overflow-y-auto overscroll-contain"
        >
            <p v-if="!visible.length" class="py-6 text-center text-ink-muted">
                {{ t('reactions.noMatches') }}
            </p>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(44px,1fr))]">
                <button
                    v-for="[, emoji, name] in visible"
                    :key="emoji"
                    type="button"
                    :title="name"
                    :aria-label="name"
                    class="grid aspect-square place-items-center rounded-md text-[26px] leading-none hover:bg-surface-2"
                    @click="choose(emoji)"
                >
                    {{ emoji }}
                </button>
            </div>
        </div>
    </AppDialog>
</template>
