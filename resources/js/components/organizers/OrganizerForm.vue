<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import TextArea from '@/components/ui/TextArea.vue';
import TextField from '@/components/ui/TextField.vue';

export interface OrganizerFormValues {
    name: string;
    handle: string;
    description: string | null;
}

/**
 * Name, handle and a few lines about the organizer, for creating and
 * editing one.
 */
const props = defineProps<{
    form: {
        action: string;
        method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    };
    values: OrganizerFormValues;
    submitLabel: string;
    processingLabel: string;
    note?: string;
}>();

const { t } = useI18n();

// Text fields keep their own state (see .ai/rules/js.md).
const name = ref(props.values.name);
const handle = ref(props.values.handle);
const description = ref(props.values.description ?? '');
</script>

<template>
    <Form
        v-bind="props.form"
        #default="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <TextField
            v-model="name"
            name="name"
            :label="t('organizers.form.name')"
            :placeholder="t('organizers.form.namePlaceholder')"
            :error="errors.name"
            headline
            required
            maxlength="80"
        />

        <TextField
            v-model="handle"
            name="handle"
            :label="t('organizers.form.handle')"
            :hint="t('organizers.form.handleHint')"
            :error="errors.handle"
            required
            maxlength="40"
        />

        <TextArea
            v-model="description"
            name="description"
            :label="t('organizers.form.description')"
            :placeholder="t('organizers.form.descriptionPlaceholder')"
            :error="errors.description"
            optional
            maxlength="2000"
        />

        <p v-if="props.note" class="text-meta text-ink-muted">
            {{ props.note }}
        </p>

        <div
            class="flex items-center justify-end gap-2 border-t border-line pt-5"
        >
            <slot name="cancel" />
            <Button type="submit" variant="primary" :disabled="processing">
                {{ processing ? props.processingLabel : props.submitLabel }}
            </Button>
        </div>
    </Form>
</template>
