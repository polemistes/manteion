<script setup lang="ts">
/**
 * The content column. `feed` (720 + gutters) for single-column pages,
 * `wide` (1104 + gutters from xl) for pages with an aside, `panel` (640)
 * for forms, `compose` (a 640 form, plus a 320 aside from xl). Phones get
 * 16px gutters, tablets 32, desktops 40 (56 on the left from 1536).
 *
 * Centred on phones and tablets; beside the desktop sidebar it starts at
 * the left, so a wide screen puts its spare room after the content rather
 * than as a void between the navigation and the page.
 */
const props = withDefaults(
    defineProps<{ width?: 'feed' | 'wide' | 'panel' | 'compose' }>(),
    { width: 'feed' },
);
</script>

<template>
    <div
        class="mx-auto w-full px-4 sm:px-8 lg:ml-0 lg:px-10 2xl:pl-14"
        :class="{
            'sm:max-w-[calc(720px+64px)] lg:max-w-[calc(720px+80px)] 2xl:max-w-[calc(720px+96px)]':
                props.width === 'feed',
            'sm:max-w-[calc(720px+64px)] lg:max-w-[calc(720px+80px)] xl:max-w-[calc(1104px+80px)] 2xl:max-w-[calc(1104px+96px)]':
                props.width === 'wide',
            'sm:max-w-[calc(640px+64px)] lg:max-w-[calc(640px+80px)] 2xl:max-w-[calc(640px+96px)]':
                props.width === 'panel',
            'sm:max-w-[calc(640px+64px)] lg:max-w-[calc(640px+80px)] xl:max-w-[calc(1008px+80px)] 2xl:max-w-[calc(1008px+96px)]':
                props.width === 'compose',
        }"
    >
        <slot />
    </div>
</template>
