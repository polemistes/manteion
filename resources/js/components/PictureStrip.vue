<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

/**
 * The pictures about to be sent, or already on what is being edited, as a
 * row of small tiles, each with a button to take it off; the row scrolls
 * sideways when they do not fit. With `addable`, a last tile adds more.
 */
const props = withDefaults(
    defineProps<{
        pictures: { key: string | number; url: string }[];
        addable?: boolean;
        disabled?: boolean;
    }>(),
    { addable: false, disabled: false },
);

const emit = defineEmits<{ remove: [index: number]; add: [] }>();

const { t } = useI18n();
</script>

<template>
    <ul
        class="-mx-1 scrollbar-none flex gap-2.5 overflow-x-auto px-1 pt-2 pb-1"
    >
        <li
            v-for="(picture, index) in props.pictures"
            :key="picture.key"
            class="relative shrink-0"
        >
            <img
                :src="picture.url"
                alt=""
                class="block size-20 rounded-md object-cover ring-1 ring-line"
            />
            <button
                type="button"
                :aria-label="t('pictures.remove', { n: index + 1 })"
                :disabled="props.disabled"
                class="absolute -top-2 -right-2 grid size-7 place-items-center rounded-full bg-ink text-on-ink shadow-sm transition-transform duration-120 after:absolute after:-inset-1.5 active:scale-[.98] disabled:opacity-45"
                @click="emit('remove', index)"
            >
                <X :size="14" :stroke-width="2.25" aria-hidden="true" />
            </button>
        </li>
        <li v-if="props.addable" class="shrink-0">
            <button
                type="button"
                :aria-label="t('pictures.addMore')"
                :disabled="props.disabled"
                class="grid size-20 place-items-center rounded-md border-2 border-dashed border-line-strong text-ink-muted transition-colors duration-120 hover:bg-surface-2 hover:text-ink disabled:opacity-45"
                @click="emit('add')"
            >
                <ImagePlus :size="22" :stroke-width="1.75" aria-hidden="true" />
            </button>
        </li>
    </ul>
</template>
