<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { FlaskConical, X } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';
import { alphaNoticeDismissed as dismissed } from './alphaNoticeState';

/**
 * The alpha-test notice: a strip under the top bar on phones and tablets, a
 * card in the sidebar on desktop. Dismissing it sets a cookie, so the server
 * renders the next page without it (no flash on load).
 */
const props = withDefaults(defineProps<{ variant?: 'strip' | 'card' }>(), {
    variant: 'strip',
});

const { t } = useI18n();
const page = usePage();

const visible = computed(
    () => !page.props.alphaNoticeDismissed && !dismissed.value,
);

function dismiss(): void {
    dismissed.value = true;
    document.cookie =
        'alpha_notice_dismissed=1; max-age=31536000; path=/; samesite=lax';
}
</script>

<template>
    <div
        v-if="visible && props.variant === 'strip'"
        class="flex min-h-11 items-center gap-2.5 rounded-md bg-surface-2 py-1 pr-1 pl-3.5 text-meta text-ink-muted"
    >
        <FlaskConical :size="16" :stroke-width="2" aria-hidden="true" />
        <p class="min-w-0 flex-1">
            <strong class="font-semibold text-ink">{{
                t('ui.alphaTitle')
            }}</strong>
            {{ t('ui.alphaShort') }}
        </p>
        <IconButton
            :icon="X"
            :label="t('ui.dismiss')"
            size="sm"
            @click="dismiss"
        />
    </div>
    <div
        v-else-if="visible"
        class="relative rounded-md bg-surface-2 p-3 pr-10 text-meta text-ink-muted"
    >
        <p class="flex gap-2">
            <FlaskConical
                :size="16"
                :stroke-width="2"
                class="mt-px shrink-0"
                aria-hidden="true"
            />
            <span>
                <strong class="font-semibold text-ink">{{
                    t('ui.alphaTitle')
                }}</strong>
                {{ t('ui.alphaLong') }}
            </span>
        </p>
        <IconButton
            :icon="X"
            :label="t('ui.dismiss')"
            size="sm"
            class="absolute top-1 right-1"
            @click="dismiss"
        />
    </div>
</template>
