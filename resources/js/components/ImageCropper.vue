<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        name: string;
        aspectRatio: number;
        outputWidth: number;
        outputHeight: number;
        existingUrl?: string | null;
    }>(),
    { existingUrl: null },
);

const { t } = useI18n();

const VIEWPORT_WIDTH = 320;
const MAX_ZOOM_MULTIPLIER = 3;
const LETTERBOX_COLOR = '#e5e7eb'; // Tailwind gray-200, matching EventThumbnail's letterbox.

const viewportHeight = computed(() =>
    Math.round(VIEWPORT_WIDTH / props.aspectRatio),
);

const pickerInput = ref<HTMLInputElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const viewport = ref<HTMLDivElement | null>(null);
const imageEl = ref<HTMLImageElement | null>(null);

const rawObjectUrl = ref<string | null>(null);
const confirmedPreviewUrl = ref<string | null>(null);
const isCropping = ref(false);

const naturalWidth = ref(0);
const naturalHeight = ref(0);
const scale = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);

const containScale = computed(() =>
    naturalWidth.value && naturalHeight.value
        ? Math.min(
              VIEWPORT_WIDTH / naturalWidth.value,
              viewportHeight.value / naturalHeight.value,
          )
        : 1,
);

const coverScale = computed(() =>
    naturalWidth.value && naturalHeight.value
        ? Math.max(
              VIEWPORT_WIDTH / naturalWidth.value,
              viewportHeight.value / naturalHeight.value,
          )
        : 1,
);

const maxScale = computed(() => coverScale.value * MAX_ZOOM_MULTIPLIER);

const imageStyle = computed(() => ({
    position: 'absolute' as const,
    left: '50%',
    top: '50%',
    // Tailwind's Preflight resets img to max-width:100%; height:auto, which
    // (since this image sits inside the absolutely-positioned viewport) caps
    // its rendered width at the viewport's width once zoomed past it, while
    // this explicit height keeps growing — stretching the image. Overriding
    // both max-width and height here keeps the two dimensions in lockstep.
    maxWidth: 'none',
    width: `${naturalWidth.value * scale.value}px`,
    height: `${naturalHeight.value * scale.value}px`,
    transform: `translate(calc(-50% + ${offsetX.value}px), calc(-50% + ${offsetY.value}px))`,
}));

const previewUrl = computed(
    () => confirmedPreviewUrl.value ?? props.existingUrl,
);

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function applyOffset(x: number, y: number): void {
    const displayedWidth = naturalWidth.value * scale.value;
    const displayedHeight = naturalHeight.value * scale.value;
    const maxOffsetX = Math.max(0, (displayedWidth - VIEWPORT_WIDTH) / 2);
    const maxOffsetY = Math.max(
        0,
        (displayedHeight - viewportHeight.value) / 2,
    );

    offsetX.value = clamp(x, -maxOffsetX, maxOffsetX);
    offsetY.value = clamp(y, -maxOffsetY, maxOffsetY);
}

function onFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    if (rawObjectUrl.value) {
        URL.revokeObjectURL(rawObjectUrl.value);
    }

    rawObjectUrl.value = URL.createObjectURL(file);
    isCropping.value = true;
}

function onImageLoad(): void {
    if (!imageEl.value) {
        return;
    }

    naturalWidth.value = imageEl.value.naturalWidth;
    naturalHeight.value = imageEl.value.naturalHeight;
    scale.value = containScale.value;
    offsetX.value = 0;
    offsetY.value = 0;
}

function onZoomInput(event: Event): void {
    scale.value = Number((event.target as HTMLInputElement).value);
    applyOffset(offsetX.value, offsetY.value);
}

let dragStart: {
    x: number;
    y: number;
    offsetX: number;
    offsetY: number;
} | null = null;

function onPointerDown(event: PointerEvent): void {
    viewport.value?.setPointerCapture(event.pointerId);
    dragStart = {
        x: event.clientX,
        y: event.clientY,
        offsetX: offsetX.value,
        offsetY: offsetY.value,
    };
}

function onPointerMove(event: PointerEvent): void {
    if (!dragStart) {
        return;
    }

    applyOffset(
        dragStart.offsetX + (event.clientX - dragStart.x),
        dragStart.offsetY + (event.clientY - dragStart.y),
    );
}

function onPointerUp(): void {
    dragStart = null;
}

function cancelCropping(): void {
    isCropping.value = false;

    if (rawObjectUrl.value) {
        URL.revokeObjectURL(rawObjectUrl.value);
        rawObjectUrl.value = null;
    }

    if (pickerInput.value) {
        pickerInput.value.value = '';
    }
}

async function confirmCrop(): Promise<void> {
    if (!imageEl.value) {
        return;
    }

    const cropWidthNatural = VIEWPORT_WIDTH / scale.value;
    const cropHeightNatural = viewportHeight.value / scale.value;
    const cropXNatural =
        naturalWidth.value / 2 -
        offsetX.value / scale.value -
        cropWidthNatural / 2;
    const cropYNatural =
        naturalHeight.value / 2 -
        offsetY.value / scale.value -
        cropHeightNatural / 2;

    const canvas = document.createElement('canvas');
    canvas.width = props.outputWidth;
    canvas.height = props.outputHeight;

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return;
    }

    ctx.fillStyle = LETTERBOX_COLOR;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(
        imageEl.value,
        cropXNatural,
        cropYNatural,
        cropWidthNatural,
        cropHeightNatural,
        0,
        0,
        canvas.width,
        canvas.height,
    );

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.9),
    );

    if (!blob || !fileInput.value) {
        return;
    }

    const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    fileInput.value.files = dataTransfer.files;

    if (confirmedPreviewUrl.value) {
        URL.revokeObjectURL(confirmedPreviewUrl.value);
    }

    confirmedPreviewUrl.value = URL.createObjectURL(blob);

    cancelCropping();
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <input ref="fileInput" type="file" :name="props.name" class="hidden" />

        <div
            v-if="isCropping"
            class="flex flex-col gap-2"
            :style="{ width: VIEWPORT_WIDTH + 'px' }"
        >
            <div
                ref="viewport"
                class="border-edge bg-gray-200 relative touch-none overflow-hidden border"
                :style="{
                    width: VIEWPORT_WIDTH + 'px',
                    height: viewportHeight + 'px',
                }"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
            >
                <img
                    ref="imageEl"
                    :src="rawObjectUrl ?? undefined"
                    :style="imageStyle"
                    class="pointer-events-none select-none"
                    draggable="false"
                    alt=""
                    @load="onImageLoad"
                />
            </div>

            <input
                type="range"
                :min="containScale"
                :max="maxScale"
                step="0.01"
                :value="scale"
                :aria-label="t('imageCropper.zoom')"
                class="w-full"
                @input="onZoomInput"
            />

            <div class="flex gap-2">
                <button
                    type="button"
                    class="border-edge text-xs hover:bg-surface-raised border bg-accent px-3 py-1.5 font-bold text-on-accent hover:text-ink"
                    @click="confirmCrop"
                >
                    {{ t('imageCropper.usePhoto') }}
                </button>
                <button
                    type="button"
                    class="border-edge text-xs border px-3 py-1.5 hover:bg-accent hover:text-on-accent"
                    @click="cancelCropping"
                >
                    {{ t('common.cancel') }}
                </button>
            </div>
        </div>

        <div v-else class="flex items-center gap-4">
            <img
                v-if="previewUrl"
                :src="previewUrl"
                alt=""
                class="border-edge h-24 border object-cover"
                :style="{ aspectRatio: String(props.aspectRatio) }"
            />
            <div
                v-else
                class="border-edge bg-gray-200 h-24 border"
                :style="{ aspectRatio: String(props.aspectRatio) }"
            ></div>

            <input
                ref="pickerInput"
                type="file"
                accept="image/*"
                class="text-sm"
                @change="onFileSelected"
            />
        </div>
    </div>
</template>
