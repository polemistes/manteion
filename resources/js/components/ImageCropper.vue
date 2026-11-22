<script setup lang="ts">
import { Crop, ImagePlus, Maximize2, Trash } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import SegmentedControl from '@/components/ui/SegmentedControl.vue';

/**
 * Picks an image and prepares it for upload in the browser (re-drawing it
 * also strips camera metadata). By default it is cropped to `aspectRatio`
 * with drag and zoom. With `allowWhole`, the uploader may instead keep the
 * whole picture ("Show it whole", for posters and flyers): the default
 * follows the picture's shape, and the choice travels as `image_fit`.
 * The prepared file is put in a hidden file input named `name`. `preview`
 * reports the picture shown and its fit as they change.
 *
 * What the crop frame shows is what is sent: the picture is prepared as
 * soon as it is chosen and again after every drag or zoom, so a form sent
 * while the crop is still open carries it. "Use this photo" only closes the
 * crop; Cancel puts back whatever was there before it opened.
 *
 * `initialFile` is a picture chosen for the uploader beforehand (an
 * imported event's cover): it is prepared at once, whole or cropped around
 * its middle as its shape says, so the form can be sent without touching
 * it.
 */
const props = withDefaults(
    defineProps<{
        name: string;
        aspectRatio: number;
        outputWidth: number;
        outputHeight: number;
        existingUrl?: string | null;
        initialFile?: Blob | null;
        allowWhole?: boolean;
        initialFit?: 'cover' | 'contain' | null;
        round?: boolean;
        removable?: boolean;
    }>(),
    {
        existingUrl: null,
        initialFile: null,
        allowWhole: false,
        initialFit: null,
        round: false,
        removable: false,
    },
);

const emit = defineEmits<{
    remove: [];
    preview: [value: { url: string | null; fit: 'cover' | 'contain' }];
}>();

const { t } = useI18n();

/** Pictures at least this much wider than tall are photos (fill the frame). */
const PHOTO_MIN_ASPECT = 1.25;
/** Whole pictures are scaled to fit this on their longer side. */
const WHOLE_MAX_EDGE = 1600;
const VIEWPORT_WIDTH = 320;
const MAX_ZOOM_MULTIPLIER = 3;

const viewportHeight = computed(() =>
    Math.round(VIEWPORT_WIDTH / props.aspectRatio),
);

const pickerInput = ref<HTMLInputElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const viewport = ref<HTMLDivElement | null>(null);
const imageEl = ref<HTMLImageElement | null>(null);

const rawObjectUrl = ref<string | null>(null);
const preparedPreviewUrl = ref<string | null>(null);
const removed = ref(false);
const isCropping = ref(false);
// Set when a new picture is chosen: its shape picks the fit once.
let choosingFit = false;
// Set while the fit is changed by the component itself (a picture taken in,
// a crop cancelled), so the switch below does not answer it.
let settingFit = false;
// The prepared picture, kept so a cancelled crop can put it back.
let preparedBlob: Blob | null = null;
// What was there when the crop opened.
let before: {
    rawUrl: string | null;
    blob: Blob | null;
    fit: 'cover' | 'contain';
    removed: boolean;
} | null = null;

const fit = ref<'cover' | 'contain'>(props.initialFit ?? 'cover');

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
    // The image sits inside an absolutely positioned viewport; without
    // overriding max-width its width would stop at the viewport's while its
    // height keeps growing, stretching it.
    maxWidth: 'none',
    width: `${naturalWidth.value * scale.value}px`,
    height: `${naturalHeight.value * scale.value}px`,
    transform: `translate(calc(-50% + ${offsetX.value}px), calc(-50% + ${offsetY.value}px))`,
}));

const previewUrl = computed(() =>
    removed.value ? null : (preparedPreviewUrl.value ?? props.existingUrl),
);

const hasImage = computed(() => previewUrl.value !== null);

watch([previewUrl, fit], ([url, nextFit]) =>
    emit('preview', { url, fit: nextFit }),
);

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function applyOffset(x: number, y: number): void {
    const maxOffsetX = Math.max(
        0,
        (naturalWidth.value * scale.value - VIEWPORT_WIDTH) / 2,
    );
    const maxOffsetY = Math.max(
        0,
        (naturalHeight.value * scale.value - viewportHeight.value) / 2,
    );

    offsetX.value = clamp(x, -maxOffsetX, maxOffsetX);
    offsetY.value = clamp(y, -maxOffsetY, maxOffsetY);
}

function setFile(blob: Blob): void {
    if (!fileInput.value) {
        return;
    }

    const file = new File([blob], 'image.jpg', { type: 'image/jpeg' });
    const transfer = new DataTransfer();

    transfer.items.add(file);
    fileInput.value.files = transfer.files;

    if (preparedPreviewUrl.value) {
        URL.revokeObjectURL(preparedPreviewUrl.value);
    }

    preparedBlob = blob;
    preparedPreviewUrl.value = URL.createObjectURL(blob);
    removed.value = false;
}

function clearFile(): void {
    if (fileInput.value) {
        fileInput.value.value = '';
    }

    if (preparedPreviewUrl.value) {
        URL.revokeObjectURL(preparedPreviewUrl.value);
    }

    preparedBlob = null;
    preparedPreviewUrl.value = null;
}

/** Sets the fit without the switch below reacting to it. */
async function setFitQuietly(next: 'cover' | 'contain'): Promise<void> {
    settingFit = true;
    fit.value = next;
    await nextTick();
    settingFit = false;
}

/** Remembers what is there now, for Cancel, as a crop opens. */
function rememberBefore(
    rawUrl: string | null,
    fitBefore: 'cover' | 'contain' = fit.value,
): void {
    before = {
        rawUrl,
        blob: preparedBlob,
        fit: fitBefore,
        removed: removed.value,
    };
}

function forgetBefore(): void {
    if (before?.rawUrl && before.rawUrl !== rawObjectUrl.value) {
        URL.revokeObjectURL(before.rawUrl);
    }

    before = null;
}

function canvasBlob(canvas: HTMLCanvasElement): Promise<Blob | null> {
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
}

/** The whole picture, scaled to fit WHOLE_MAX_EDGE. */
async function prepareWhole(): Promise<void> {
    if (!rawObjectUrl.value) {
        return;
    }

    const image = new Image();

    image.src = rawObjectUrl.value;

    try {
        await image.decode();
    } catch {
        return;
    }

    const ratio = Math.min(
        1,
        WHOLE_MAX_EDGE / Math.max(image.naturalWidth, image.naturalHeight),
    );
    const canvas = document.createElement('canvas');

    canvas.width = Math.round(image.naturalWidth * ratio);
    canvas.height = Math.round(image.naturalHeight * ratio);
    canvas
        .getContext('2d')
        ?.drawImage(image, 0, 0, canvas.width, canvas.height);

    const blob = await canvasBlob(canvas);

    if (blob) {
        setFile(blob);
    }
}

/** The middle of the picture at `aspectRatio`, no larger than the output. */
async function prepareCentred(image: HTMLImageElement): Promise<void> {
    const cropWidth = Math.min(
        image.naturalWidth,
        image.naturalHeight * props.aspectRatio,
    );
    const cropHeight = cropWidth / props.aspectRatio;
    const ratio = Math.min(1, props.outputWidth / cropWidth);
    const canvas = document.createElement('canvas');

    canvas.width = Math.round(cropWidth * ratio);
    canvas.height = Math.round(cropHeight * ratio);
    canvas
        .getContext('2d')
        ?.drawImage(
            image,
            (image.naturalWidth - cropWidth) / 2,
            (image.naturalHeight - cropHeight) / 2,
            cropWidth,
            cropHeight,
            0,
            0,
            canvas.width,
            canvas.height,
        );

    const blob = await canvasBlob(canvas);

    if (blob) {
        setFile(blob);
    }
}

/** Takes in `initialFile` as if it had been picked and confirmed. */
async function adopt(file: Blob): Promise<void> {
    const url = URL.createObjectURL(file);
    const image = new Image();

    image.src = url;

    try {
        await image.decode();
    } catch {
        URL.revokeObjectURL(url);

        return;
    }

    rawObjectUrl.value = url;

    const whole =
        props.allowWhole &&
        image.naturalWidth / image.naturalHeight < PHOTO_MIN_ASPECT;

    await setFitQuietly(whole ? 'contain' : 'cover');

    if (whole) {
        await prepareWhole();
    } else {
        await prepareCentred(image);
    }
}

onMounted(() => {
    if (props.initialFile) {
        void adopt(props.initialFile);
    }
});

function onFileSelected(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    // A second pick while the crop is open replaces the first: Cancel
    // still goes back to what was there before either.
    if (before) {
        if (rawObjectUrl.value && rawObjectUrl.value !== before.rawUrl) {
            URL.revokeObjectURL(rawObjectUrl.value);
        }
    } else {
        rememberBefore(rawObjectUrl.value);
    }

    rawObjectUrl.value = URL.createObjectURL(file);
    choosingFit = true;
    isCropping.value = true;
}

async function onImageLoad(): Promise<void> {
    if (!imageEl.value) {
        return;
    }

    naturalWidth.value = imageEl.value.naturalWidth;
    naturalHeight.value = imageEl.value.naturalHeight;
    scale.value = coverScale.value;
    offsetX.value = 0;
    offsetY.value = 0;

    if (choosingFit) {
        choosingFit = false;

        // A poster-shaped picture is shown whole unless the uploader says
        // not: it is prepared at once, with no crop to confirm.
        if (
            props.allowWhole &&
            naturalWidth.value / naturalHeight.value < PHOTO_MIN_ASPECT
        ) {
            await setFitQuietly('contain');
            isCropping.value = false;
            forgetBefore();
            resetPicker();
            await prepareWhole();

            return;
        }

        await setFitQuietly('cover');
    }

    // The frame as it opens is already a choice: prepare it.
    await commitCrop();
}

let commitTimer: ReturnType<typeof setTimeout> | undefined;

/** Prepares the frame again shortly after it stops moving. */
function commitSoon(): void {
    clearTimeout(commitTimer);
    commitTimer = setTimeout(() => void commitCrop(), 150);
}

function onZoomInput(event: Event): void {
    scale.value = Number((event.target as HTMLInputElement).value);
    applyOffset(offsetX.value, offsetY.value);
    commitSoon();
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
    if (dragStart) {
        applyOffset(
            dragStart.offsetX + (event.clientX - dragStart.x),
            dragStart.offsetY + (event.clientY - dragStart.y),
        );
    }
}

function onPointerUp(): void {
    if (dragStart) {
        dragStart = null;
        commitSoon();
    }
}

/** So the same file can be picked again. */
function resetPicker(): void {
    if (pickerInput.value) {
        pickerInput.value.value = '';
    }
}

async function cancelCropping(): Promise<void> {
    clearTimeout(commitTimer);
    commitTicket += 1;
    isCropping.value = false;
    resetPicker();

    if (!before) {
        return;
    }

    const previous = before;

    before = null;

    if (rawObjectUrl.value && rawObjectUrl.value !== previous.rawUrl) {
        URL.revokeObjectURL(rawObjectUrl.value);
    }

    rawObjectUrl.value = previous.rawUrl;
    await setFitQuietly(previous.fit);

    if (previous.blob) {
        setFile(previous.blob);
    } else {
        clearFile();
    }

    removed.value = previous.removed;
}

async function confirmCrop(): Promise<void> {
    clearTimeout(commitTimer);
    await commitCrop();
    isCropping.value = false;
    forgetBefore();
    resetPicker();
}

let commitTicket = 0;

/** Prepares what the frame shows now; only the newest run counts. */
async function commitCrop(): Promise<void> {
    if (!imageEl.value || !isCropping.value || !naturalWidth.value) {
        return;
    }

    const ticket = ++commitTicket;

    const cropWidth = VIEWPORT_WIDTH / scale.value;
    const cropHeight = viewportHeight.value / scale.value;
    const cropX =
        naturalWidth.value / 2 - offsetX.value / scale.value - cropWidth / 2;
    const cropY =
        naturalHeight.value / 2 - offsetY.value / scale.value - cropHeight / 2;

    const canvas = document.createElement('canvas');

    canvas.width = props.outputWidth;
    canvas.height = props.outputHeight;

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    context.fillStyle = '#efe8de';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(
        imageEl.value,
        cropX,
        cropY,
        cropWidth,
        cropHeight,
        0,
        0,
        canvas.width,
        canvas.height,
    );

    const blob = await canvasBlob(canvas);

    if (blob && ticket === commitTicket && isCropping.value) {
        setFile(blob);
    }
}

// Switching between "fill" and "whole" after choosing a picture: whole
// is prepared at once, fill opens the crop.
watch(fit, (next, previous) => {
    if (next === previous || !rawObjectUrl.value || settingFit) {
        return;
    }

    if (next === 'contain') {
        clearTimeout(commitTimer);
        commitTicket += 1;
        isCropping.value = false;
        forgetBefore();
        void prepareWhole();
    } else {
        rememberBefore(rawObjectUrl.value, previous);
        isCropping.value = true;
    }
});

function remove(): void {
    clearTimeout(commitTimer);
    commitTicket += 1;
    removed.value = true;
    isCropping.value = false;
    forgetBefore();
    clearFile();

    emit('remove');
}

onBeforeUnmount(() => {
    clearTimeout(commitTimer);

    for (const url of new Set([
        rawObjectUrl.value,
        preparedPreviewUrl.value,
        before?.rawUrl ?? null,
    ])) {
        if (url) {
            URL.revokeObjectURL(url);
        }
    }
});

const fitSegments = computed(() => [
    { value: 'cover', label: t('imageCropper.fillFrame'), icon: Crop },
    { value: 'contain', label: t('imageCropper.showWhole'), icon: Maximize2 },
]);
</script>

<template>
    <div class="flex flex-col gap-3">
        <input ref="fileInput" type="file" :name="props.name" class="hidden" />
        <input
            v-if="props.allowWhole && hasImage"
            type="hidden"
            name="image_fit"
            :value="fit"
        />
        <input
            ref="pickerInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="onFileSelected"
        />

        <!-- Cropping: drag and zoom inside the frame. -->
        <div v-if="isCropping" class="flex w-full max-w-[320px] flex-col gap-3">
            <div
                ref="viewport"
                class="relative touch-none overflow-hidden bg-surface-2 ring-1 ring-line"
                :class="props.round ? 'rounded-full' : 'rounded-md'"
                :style="{
                    width: `${VIEWPORT_WIDTH}px`,
                    height: `${viewportHeight}px`,
                    maxWidth: '100%',
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
                class="w-full accent-[var(--color-ink)]"
                @input="onZoomInput"
            />
            <div class="flex gap-2">
                <Button size="sm" variant="primary" @click="confirmCrop">
                    {{ t('imageCropper.usePhoto') }}
                </Button>
                <Button size="sm" variant="ghost" @click="cancelCropping">
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </div>

        <!-- The prepared or current image, or a place to pick one. -->
        <template v-else>
            <div
                v-if="hasImage"
                class="relative overflow-hidden bg-surface-2"
                :class="[
                    props.round ? 'size-28 rounded-full' : 'rounded-lg',
                    !props.round && fit === 'contain'
                        ? 'flex justify-center p-4'
                        : '',
                ]"
                :style="
                    !props.round && fit === 'cover'
                        ? { aspectRatio: String(props.aspectRatio) }
                        : undefined
                "
            >
                <img
                    :src="previewUrl!"
                    alt=""
                    :class="
                        props.round
                            ? 'size-full object-cover'
                            : fit === 'contain'
                              ? 'max-h-[320px] w-auto max-w-full rounded-xs object-contain shadow-sm'
                              : 'size-full object-cover'
                    "
                />
            </div>
            <button
                v-else
                type="button"
                class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-line-strong bg-surface text-ink-muted transition-colors duration-120 hover:bg-surface-2 hover:text-ink"
                :class="
                    props.round
                        ? 'size-28 rounded-full'
                        : 'aspect-(--cover-ratio) rounded-lg py-8 sm:aspect-auto sm:h-44'
                "
                :style="
                    props.round
                        ? undefined
                        : { '--cover-ratio': String(props.aspectRatio) }
                "
                @click="pickerInput?.click()"
            >
                <ImagePlus :size="24" :stroke-width="1.75" aria-hidden="true" />
                <span v-if="!props.round" class="text-label font-semibold">{{
                    t('imageCropper.choose')
                }}</span>
                <span v-else class="sr-only">{{
                    t('imageCropper.choose')
                }}</span>
            </button>

            <div v-if="hasImage" class="flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    :icon="ImagePlus"
                    @click="pickerInput?.click()"
                >
                    {{ t('imageCropper.replace') }}
                </Button>
                <Button
                    v-if="props.removable || preparedPreviewUrl"
                    size="sm"
                    variant="ghost"
                    :icon="Trash"
                    @click="remove"
                >
                    {{ t('imageCropper.remove') }}
                </Button>
            </div>

            <div
                v-if="props.allowWhole && hasImage"
                class="flex flex-col gap-1.5"
            >
                <SegmentedControl
                    v-model="fit"
                    :segments="fitSegments"
                    :label="t('imageCropper.fitLabel')"
                    class="self-start"
                />
                <p class="text-meta text-ink-muted">
                    {{ t('imageCropper.fitHint') }}
                </p>
            </div>
        </template>
    </div>
</template>
