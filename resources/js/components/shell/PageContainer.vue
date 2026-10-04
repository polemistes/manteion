<script setup lang="ts">
/**
 * The content column. `feed` (720 + gutters) for single-column pages,
 * `wide` (1104 + gutters from xl) for pages with an aside, `panel` (640)
 * for forms. Phones get 16px gutters, tablets 32, desktops 40.
 */
const props = withDefaults(
    defineProps<{ width?: 'feed' | 'wide' | 'panel' }>(),
    { width: 'feed' },
);
</script>

<template>
    <div
        class="mx-auto w-full px-4 sm:px-8 lg:px-10"
        :class="{
            'sm:max-w-[calc(720px+64px)] lg:max-w-[calc(720px+80px)]':
                props.width === 'feed',
            'sm:max-w-[calc(720px+64px)] lg:max-w-[calc(720px+80px)] xl:max-w-[calc(1104px+80px)]':
                props.width === 'wide',
            'sm:max-w-[calc(640px+64px)] lg:max-w-[calc(640px+80px)]':
                props.width === 'panel',
        }"
    >
        <slot />
    </div>
</template>
