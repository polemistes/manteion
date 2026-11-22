<script setup lang="ts">
import markDarkUrl from '@/../images/mark-dark.webp';
import markUrl from '@/../images/mark.webp';

/**
 * The tripod mark: the owner's drawing (a bowl with three ring handles on
 * three legs), cut out of its paper. It is a picture in its own colours,
 * the same in every skin, a little over twice as tall as it is wide. Give
 * it a height (`h-14 w-auto`); the width follows from its shape.
 *
 * In dark mode the copy with a light outline is shown: the black ink
 * would vanish against a dark page.
 *
 * It is an inline block with nothing but blocks inside, so in a line of
 * text its bottom edge stands on the baseline (the front page uses that
 * to make it the M of "Manteion").
 */
</script>

<template>
    <span class="mark inline-block aspect-[233/480]" aria-hidden="true">
        <img
            :src="markUrl"
            width="233"
            height="480"
            alt=""
            draggable="false"
            class="mark-light block size-full select-none"
        />
        <img
            :src="markDarkUrl"
            width="233"
            height="480"
            alt=""
            draggable="false"
            class="mark-dark block size-full select-none"
        />
    </span>
</template>

<style scoped>
.mark-dark,
html[data-mode='dark'] .mark-light {
    display: none;
}

html[data-mode='dark'] .mark-dark {
    display: block;
}
</style>
