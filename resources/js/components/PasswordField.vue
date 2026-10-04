<script setup lang="ts">
import { CircleAlert, Eye, EyeOff } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import IconButton from '@/components/ui/IconButton.vue';

defineOptions({ inheritAttrs: false });

/**
 * A password input with a show/hide toggle; other attributes go to the
 * input. Given a label it brings its own label, hint and error, as
 * TextField does.
 */
const props = withDefaults(
    defineProps<{
        id?: string;
        name: string;
        invalid?: boolean;
        label?: string;
        hint?: string;
        error?: string;
    }>(),
    {
        id: undefined,
        invalid: false,
        label: undefined,
        hint: undefined,
        error: undefined,
    },
);

const { t } = useI18n();

const autoId = useId();
const inputId = computed(() => props.id ?? autoId);
const visible = ref(false);

const describedBy = computed(
    () =>
        [
            props.hint ? `${inputId.value}-hint` : null,
            props.error ? `${inputId.value}-error` : null,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label
            v-if="props.label"
            :for="inputId"
            class="text-label font-semibold text-ink"
            >{{ props.label }}</label
        >
        <div class="relative">
            <input
                :id="inputId"
                :name="props.name"
                :type="visible ? 'text' : 'password'"
                :aria-invalid="
                    props.invalid || props.error ? 'true' : undefined
                "
                :aria-describedby="describedBy"
                v-bind="$attrs"
                class="h-12 w-full rounded-md bg-surface pr-12 pl-3.5 text-input text-ink ring-1 ring-control transition-shadow duration-120 ring-inset hover:ring-ink-subtle focus:ring-2 focus:ring-ink focus:outline-none"
                :class="
                    props.invalid || props.error ? 'ring-2 ring-danger' : ''
                "
            />
            <IconButton
                :icon="visible ? EyeOff : Eye"
                :label="
                    visible
                        ? t('common.hidePassword')
                        : t('common.showPassword')
                "
                :pressed="visible"
                size="sm"
                class="absolute top-1/2 right-1.5 -translate-y-1/2"
                @click="visible = !visible"
            />
        </div>
        <p
            v-if="props.hint"
            :id="`${inputId}-hint`"
            class="text-meta text-ink-muted"
        >
            {{ props.hint }}
        </p>
        <p
            v-if="props.error"
            :id="`${inputId}-error`"
            class="flex items-center gap-1.5 text-meta font-medium text-danger"
        >
            <CircleAlert :size="16" :stroke-width="2" aria-hidden="true" />
            {{ props.error }}
        </p>
    </div>
</template>
