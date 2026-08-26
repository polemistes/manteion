<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
    url: string | null;
    alt: string;
}>();

const fit = ref<'cover' | 'contain'>('cover');

function onLoad(event: Event): void {
    const img = event.target as HTMLImageElement;

    // 3:2 = 1.5. Anything at least as wide gets cropped (cover, edges cut);
    // anything narrower/taller gets letterboxed instead (contain, the gray
    // background below shows through as padding) rather than cropped top
    // and bottom.
    fit.value =
        img.naturalWidth / img.naturalHeight >= 3 / 2 ? 'cover' : 'contain';
}
</script>

<template>
    <div class="aspect-[3/2] w-full overflow-hidden bg-gray-200">
        <img
            v-if="url"
            :src="url"
            :alt="alt"
            class="h-full w-full"
            :class="fit === 'cover' ? 'object-cover' : 'object-contain'"
            @load="onLoad"
        />
    </div>
</template>
