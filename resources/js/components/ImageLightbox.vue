<script setup lang="ts">
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';

/**
 * Pictures on their own, one at a time, as large as the screen allows, on a
 * dark ground. `v-model:index` is the one shown; null shows nothing. With
 * several, the arrow keys, a swipe sideways or the buttons at the sides
 * move on to the next and back, and a count says where one is. A tap
 * beside the picture, the close button or Escape puts it away.
 */
const props = defineProps<{
    pictures: { url: string; width: number | null; height: number | null }[];
}>();

const index = defineModel<number | null>('index', { default: null });

const { t } = useI18n();

const current = computed(() =>
    index.value === null ? null : (props.pictures[index.value] ?? null),
);

const open = computed({
    get: () => current.value !== null,
    set: (value: boolean) => {
        if (!value) {
            index.value = null;
        }
    },
});

const several = computed(() => props.pictures.length > 1);

function step(by: number): void {
    if (index.value === null || !several.value) {
        return;
    }

    index.value =
        (index.value + by + props.pictures.length) % props.pictures.length;
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowRight') {
        step(1);
    } else if (event.key === 'ArrowLeft') {
        step(-1);
    }
}

// A swipe sideways: further across than down, and far enough to be meant.
let swipeFrom: { x: number; y: number } | null = null;

function onPointerDown(event: PointerEvent): void {
    swipeFrom =
        event.pointerType === 'touch'
            ? { x: event.clientX, y: event.clientY }
            : null;
}

function onPointerUp(event: PointerEvent): void {
    if (!swipeFrom) {
        return;
    }

    const across = event.clientX - swipeFrom.x;
    const down = event.clientY - swipeFrom.y;

    swipeFrom = null;

    if (Math.abs(across) > 48 && Math.abs(across) > Math.abs(down) * 1.5) {
        swiped = true;
        step(across < 0 ? 1 : -1);
    }
}

// The tap that ends a swipe must not close the viewer.
let swiped = false;

function onBackdropClick(): void {
    if (swiped) {
        swiped = false;

        return;
    }

    open.value = false;
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-[rgb(10_7_5/0.92)] data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[fade-in_200ms_var(--ease-enter)]"
            />
            <DialogContent
                class="fixed inset-0 z-50 flex touch-pan-y items-center justify-center p-3 pt-[calc(12px+env(safe-area-inset-top))] pb-[calc(12px+env(safe-area-inset-bottom))] focus:outline-none data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[fade-in_200ms_var(--ease-enter)]"
                :aria-describedby="undefined"
                @click="onBackdropClick"
                @keydown="onKeydown"
                @pointerdown="onPointerDown"
                @pointerup="onPointerUp"
            >
                <DialogTitle class="sr-only">
                    {{ t('pictures.viewer') }}
                </DialogTitle>
                <img
                    v-if="current"
                    :key="current.url"
                    :src="current.url"
                    :width="current.width ?? undefined"
                    :height="current.height ?? undefined"
                    alt=""
                    draggable="false"
                    class="max-h-full max-w-full rounded-sm object-contain select-none"
                    @click.stop
                />
                <template v-if="several && index !== null">
                    <IconButton
                        :label="t('pictures.previous')"
                        :icon="ChevronLeft"
                        variant="floating"
                        class="absolute top-1/2 left-3 -translate-y-1/2 pointer-coarse:hidden"
                        @click.stop="step(-1)"
                    />
                    <IconButton
                        :label="t('pictures.next')"
                        :icon="ChevronRight"
                        variant="floating"
                        class="absolute top-1/2 right-3 -translate-y-1/2 pointer-coarse:hidden"
                        @click.stop="step(1)"
                    />
                    <p
                        class="absolute bottom-[calc(16px+env(safe-area-inset-bottom))] left-1/2 -translate-x-1/2 rounded-full bg-[rgb(10_7_5/0.6)] px-3 py-1 text-meta font-medium text-[#fff] tabular-nums"
                        aria-live="polite"
                    >
                        {{
                            t('pictures.position', {
                                n: index + 1,
                                total: props.pictures.length,
                            })
                        }}
                    </p>
                </template>
                <DialogClose as-child>
                    <IconButton
                        :label="t('pictures.close')"
                        :icon="X"
                        variant="floating"
                        class="absolute top-[calc(12px+env(safe-area-inset-top))] right-3"
                    />
                </DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
