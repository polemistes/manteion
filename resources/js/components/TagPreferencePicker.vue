<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { update } from '@/routes/contacts/tag-preferences';

interface Tag {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    contactId: number;
    tags: Tag[];
    selectedTagIds: number[];
}>();

const { t } = useI18n();
</script>

<template>
    <Form
        v-bind="update.form(props.contactId)"
        #default="{ processing }"
        class="mt-2 flex flex-wrap items-center gap-2"
    >
        <span class="text-xs text-black/70">{{
            t('contacts.tagPreferences.notifyMeAbout')
        }}</span>
        <label
            v-for="tag in props.tags"
            :key="tag.id"
            class="flex items-center gap-1 text-xs"
        >
            <input
                type="checkbox"
                name="tag_ids[]"
                :value="tag.id"
                :checked="props.selectedTagIds.includes(tag.id)"
                class="border border-black"
            />
            {{ tag.name }}
        </label>
        <span v-if="!props.tags.length" class="text-xs text-black/70">{{
            t('contacts.tagPreferences.noTags')
        }}</span>
        <button
            v-if="props.tags.length"
            type="submit"
            :disabled="processing"
            class="border border-black px-2 py-0.5 text-xs hover:bg-black hover:text-white"
        >
            {{ t('contacts.tagPreferences.save') }}
        </button>
    </Form>
</template>
