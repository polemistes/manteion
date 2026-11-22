<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { fitInFrame } from '@/lib/image';
import type { Picture } from '@/pictures';

/**
 * The pictures on a message, a leaflet or a comment. One is shown whole, in
 * the box its own size asks for (no jump when it loads). Several make a
 * grid of cropped tiles, from their smaller copies: two side by side, three
 * as one tall and two stacked, four as two by two, and beyond four the
 * fourth tile says how many more there are. A tap on any asks for that
 * picture full size (`open`, with its place in the list).
 *
 * `frame` is the widest and tallest a lone picture may be, in CSS pixels
 * (a chat bubble's 320); without it a lone picture takes the width it is
 * given, up to 30rem tall (a leaflet).
 */
const props = withDefaults(
    defineProps<{
        pictures: Picture[];
        frame?: number | null;
    }>(),
    { frame: null },
);

const emit = defineEmits<{ open: [index: number] }>();

const { t } = useI18n();

const SHOWN = 4;

const single = computed(() =>
    props.pictures.length === 1 ? props.pictures[0] : null,
);
const singleBox = computed(() =>
    single.value && props.frame
        ? fitInFrame(single.value.width, single.value.height, props.frame)
        : null,
);
const tiles = computed(() => props.pictures.slice(0, SHOWN));
const hidden = computed(() => Math.max(0, props.pictures.length - SHOWN));
</script>

<template>
    <!-- One picture, whole. -->
    <button
        v-if="single"
        type="button"
        :aria-label="t('pictures.viewOne')"
        class="block max-w-full overflow-hidden"
        :class="
            singleBox ? '' : 'mx-auto w-fit rounded-md bg-surface-2 text-left'
        "
        :style="singleBox ? { width: `${singleBox.width}px` } : undefined"
        @click="emit('open', 0)"
    >
        <img
            :src="single.url"
            :width="singleBox?.width ?? single.width ?? undefined"
            :height="singleBox?.height ?? single.height ?? undefined"
            alt=""
            loading="lazy"
            decoding="async"
            class="block"
            :class="
                singleBox
                    ? 'h-auto w-full'
                    : 'h-auto max-h-[30rem] w-auto max-w-full object-contain'
            "
            :style="
                singleBox
                    ? {
                          aspectRatio: `${singleBox.width} / ${singleBox.height}`,
                      }
                    : undefined
            "
        />
    </button>

    <!-- Several, as tiles. -->
    <ul
        v-else-if="tiles.length"
        class="grid aspect-[4/3] w-full grid-cols-2 gap-0.5 overflow-hidden"
        :class="[
            tiles.length > 2 ? 'grid-rows-2' : 'grid-rows-1',
            props.frame ? '' : 'rounded-md',
        ]"
        :style="props.frame ? { maxWidth: `${props.frame}px` } : undefined"
    >
        <li
            v-for="(picture, index) in tiles"
            :key="picture.id"
            class="relative min-h-0 min-w-0"
            :class="tiles.length === 3 && index === 0 ? 'row-span-2' : ''"
        >
            <button
                type="button"
                :aria-label="
                    t('pictures.view', {
                        n: index + 1,
                        total: props.pictures.length,
                    })
                "
                class="block size-full bg-surface-2"
                @click="emit('open', index)"
            >
                <img
                    :src="picture.thumb_url"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover"
                />
                <span
                    v-if="hidden > 0 && index === SHOWN - 1"
                    class="absolute inset-0 grid place-items-center bg-[rgb(10_7_5/0.55)] font-display text-title-2 text-[#fff]"
                    aria-hidden="true"
                >
                    {{ t('pictures.more', { n: hidden }) }}
                </span>
            </button>
        </li>
    </ul>
</template>
