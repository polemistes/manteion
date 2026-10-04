<script setup lang="ts">
import { ArrowUp, Smile } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import EmojiPicker from '@/components/EmojiPicker.vue';
import TextArea from '@/components/ui/TextArea.vue';

/**
 * The message field pinned under a thread. It grows to six lines, then
 * scrolls. With a hardware keyboard Enter sends and Shift+Enter breaks the
 * line; on touch screens Enter is a line break and the arrow sends.
 */
const props = withDefaults(defineProps<{ disabled?: boolean }>(), {
    disabled: false,
});

const emit = defineEmits<{ send: [body: string]; typing: [] }>();

const { t } = useI18n();

const text = ref('');
const pickerOpen = ref(false);
const field = ref<InstanceType<typeof TextArea> | null>(null);

const canSend = computed(() => text.value.trim() !== '' && !props.disabled);

function hasHardwareKeyboard(): boolean {
    return window.matchMedia('(hover: hover) and (pointer: fine)').matches;
}

function submit(): void {
    if (!canSend.value) {
        return;
    }

    emit('send', text.value.trim());
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

defineExpose({ focus: () => field.value?.focus() });
</script>

<template>
    <div
        class="flex-none border-t border-line bg-canvas p-2 pb-[calc(8px+env(safe-area-inset-bottom))] md:px-4 md:pt-3"
    >
        <form
            class="mx-auto flex max-w-thread items-end gap-2"
            @submit.prevent="submit"
        >
            <div
                class="flex min-h-11 min-w-0 flex-1 items-end rounded-xl bg-surface pr-0.5 pl-4 ring-1 ring-line-strong ring-inset focus-within:ring-2 focus-within:ring-ink md:min-h-12"
            >
                <div class="min-w-0 flex-1">
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
        <p
            class="mx-auto mt-1.5 hidden max-w-thread px-4 text-caption text-ink-subtle pointer-fine:md:block"
        >
            {{ t('messages.show.enterHint') }}
        </p>
        <EmojiPicker v-model:open="pickerOpen" @select="insertEmoji" />
    </div>
</template>
