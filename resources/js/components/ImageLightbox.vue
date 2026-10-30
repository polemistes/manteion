<script setup lang="ts">
import { X } from '@lucide/vue';
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
 * A picture on its own, as large as the screen allows, on a dark ground.
 * A tap anywhere, the close button or Escape puts it away. Bind the picture
 * to show with `v-model:image`; null shows nothing.
 */
const image = defineModel<{
    url: string;
    width: number | null;
    height: number | null;
} | null>('image', { default: null });

const { t } = useI18n();

const open = computed({
    get: () => image.value !== null,
    set: (value: boolean) => {
        if (!value) {
            image.value = null;
        }
    },
});
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-[rgb(10_7_5/0.92)] data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[fade-in_200ms_var(--ease-enter)]"
            />
            <DialogContent
                class="fixed inset-0 z-50 flex items-center justify-center p-3 pt-[calc(12px+env(safe-area-inset-top))] pb-[calc(12px+env(safe-area-inset-bottom))] focus:outline-none data-[state=closed]:animate-[fade-out_200ms_var(--ease-exit)] data-[state=open]:animate-[fade-in_200ms_var(--ease-enter)]"
                :aria-describedby="undefined"
                @click="open = false"
            >
                <DialogTitle class="sr-only">
                    {{ t('messages.show.photo') }}
                </DialogTitle>
                <img
                    v-if="image"
                    :src="image.url"
                    :width="image.width ?? undefined"
                    :height="image.height ?? undefined"
                    alt=""
                    class="max-h-full max-w-full rounded-sm object-contain"
                />
                <DialogClose as-child>
                    <IconButton
                        :label="t('messages.show.closePhoto')"
                        :icon="X"
                        variant="floating"
                        class="absolute top-[calc(12px+env(safe-area-inset-top))] right-3"
                    />
                </DialogClose>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
