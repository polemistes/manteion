<script setup lang="ts">
import { ArrowUp, Camera, ImagePlus, Smile } from '@lucide/vue';
import { computed, nextTick, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmojiPicker from '@/components/EmojiPicker.vue';
import PictureStrip from '@/components/PictureStrip.vue';
import TextArea from '@/components/ui/TextArea.vue';
import { usePictures } from '@/composables/usePictures';
import { canTakePhotos } from '@/native/shell';
import type { MessageDraft } from './chat';

/**
 * The message field pinned under a thread. It grows to six lines, then
 * scrolls. With a hardware keyboard Enter sends and Shift+Enter breaks the
 * line; on touch screens Enter is a line break and the arrow sends.
 * Pictures can be attached, several at once: they are shown above the
 * field until sent or taken off, and go out as one message with the
 * words, if any. On touch screens a second button adds one from the
 * camera instead: the
 * browser, or the Android app (Capacitor opens the camera app for a
 * capture request), goes straight to the back camera. Desktop browsers
 * ignore capture, so the button is not offered there, nor in apps that
 * can't open the camera (see canTakePhotos).
 */
const props = withDefaults(defineProps<{ disabled?: boolean }>(), {
    disabled: false,
});

const emit = defineEmits<{ send: [draft: MessageDraft]; typing: [] }>();

const { t } = useI18n();

const text = ref('');
const pickerOpen = ref(false);
const field = ref<InstanceType<typeof TextArea> | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
// Shown until the app says otherwise, so the server and the browser draw
// the same field.
const cameraAvailable = ref(true);
const { pictures, preparing, add, remove, take } = usePictures();

const canSend = computed(
    () =>
        (text.value.trim() !== '' || pictures.value.length > 0) &&
        !props.disabled &&
        !preparing.value,
);

function hasHardwareKeyboard(): boolean {
    return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
}

function submit(): void {
    if (!canSend.value) {
        return;
    }

    // The thread takes the pictures (and their previews) over from here.
    emit('send', { body: text.value.trim(), pictures: take() });
    text.value = '';
    nextTick(() => field.value?.focus());
}

function onKeydown(event: KeyboardEvent): void {
    if (
        event.key === 'Enter' &&
        !event.shiftKey &&
        !event.isComposing &&
        hasHardwareKeyboard()
    ) {
        event.preventDefault();
        submit();
    }
}

function onInput(): void {
    if (text.value !== '') {
        emit('typing');
    }
}

function insertEmoji(emoji: string): void {
    const element = field.value?.element ?? null;

    if (!element) {
        text.value += emoji;

        return;
    }

    const start = element.selectionStart ?? text.value.length;
    const end = element.selectionEnd ?? text.value.length;

    text.value = text.value.slice(0, start) + emoji + text.value.slice(end);
    nextTick(() => {
        element.focus();
        element.setSelectionRange(start + emoji.length, start + emoji.length);
    });
}

function pickImage(): void {
    fileInput.value?.click();
}

function takePhoto(): void {
    cameraInput.value?.click();
}

// The picked files are scaled down and stripped of their metadata (see
// usePictures), so phone photos go out small; they join any already there.
async function onImagesChosen(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const files = [...(input.files ?? [])];

    // So the same file can be picked again later.
    input.value = '';

    await add(files);
    nextTick(() => field.value?.focus());
}

onMounted(async () => {
    cameraAvailable.value = await canTakePhotos();
});

defineExpose({ focus: () => field.value?.focus() });
</script>

<template>
    <div
        class="flex-none border-t border-line bg-canvas p-2 pb-[calc(8px+env(safe-area-inset-bottom))] md:px-4 md:pt-3"
    >
        <div v-if="pictures.length" class="mx-auto mb-1.5 max-w-thread px-1">
            <PictureStrip
                :pictures="
                    pictures.map((picture) => ({
                        key: picture.previewUrl,
                        url: picture.previewUrl,
                    }))
                "
                @remove="remove"
            />
        </div>
        <form
            class="mx-auto flex max-w-thread items-end gap-2"
            @submit.prevent="submit"
        >
            <div
                class="flex min-h-11 min-w-0 flex-1 items-end rounded-xl bg-surface px-0.5 ring-1 ring-line-strong ring-inset focus-within:ring-2 focus-within:ring-ink md:min-h-12"
            >
                <button
                    type="button"
                    :aria-label="t('messages.show.attachPhoto')"
                    :disabled="props.disabled || preparing"
                    class="mb-0.5 grid size-10 shrink-0 place-items-center rounded-full text-ink-muted hover:text-ink disabled:pointer-events-none disabled:opacity-45 md:mb-1"
                    @click="pickImage"
                >
                    <ImagePlus
                        :size="20"
                        :stroke-width="1.8"
                        aria-hidden="true"
                    />
                </button>
                <button
                    v-if="cameraAvailable"
                    type="button"
                    :aria-label="t('messages.show.takePhoto')"
                    :disabled="props.disabled || preparing"
                    class="mb-0.5 hidden size-10 shrink-0 place-items-center rounded-full text-ink-muted hover:text-ink disabled:pointer-events-none disabled:opacity-45 md:mb-1 pointer-coarse:grid"
                    @click="takePhoto"
                >
                    <Camera :size="20" :stroke-width="1.8" aria-hidden="true" />
                </button>
                <div class="min-w-0 flex-1 pl-1">
                    <TextArea
                        ref="field"
                        v-model="text"
                        :label="t('messages.show.placeholder')"
                        hide-label
                        bare
                        :max-rows="6"
                        rows="1"
                        :placeholder="t('messages.show.placeholder')"
                        :disabled="props.disabled"
                        enterkeyhint="send"
                        class="py-2.5 md:py-3"
                        @keydown="onKeydown"
                        @input="onInput"
                    />
                </div>
                <button
                    type="button"
                    :aria-label="t('reactions.pick')"
                    class="mb-0.5 grid size-10 shrink-0 place-items-center rounded-full text-ink-muted hover:text-ink md:mb-1"
                    @click="pickerOpen = true"
                >
                    <Smile :size="20" :stroke-width="1.8" aria-hidden="true" />
                </button>
            </div>
            <button
                type="submit"
                :disabled="!canSend"
                :aria-label="t('messages.show.send')"
                class="grid size-11 shrink-0 place-items-center rounded-full bg-accent text-on-accent transition-colors duration-120 hover:bg-accent-hover active:scale-[.98] disabled:bg-surface-2 disabled:text-ink-subtle md:size-12"
            >
                <ArrowUp :size="20" :stroke-width="2.25" aria-hidden="true" />
            </button>
        </form>
        <input
            ref="fileInput"
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            tabindex="-1"
            @change="onImagesChosen"
        />
        <input
            ref="cameraInput"
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            tabindex="-1"
            @change="onImagesChosen"
        />
        <p
            class="mx-auto mt-1.5 hidden max-w-thread px-4 text-caption text-ink-subtle pointer-fine:md:block"
        >
            {{ t('messages.show.enterHint') }}
        </p>
        <EmojiPicker v-model:open="pickerOpen" @select="insertEmoji" />
    </div>
</template>
