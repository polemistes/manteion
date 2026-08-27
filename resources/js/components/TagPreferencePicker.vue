<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { update } from '@/routes/contacts/tag-preferences';

interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    children: { id: number; name: string; slug: string }[];
}

const props = defineProps<{
    contactId: number;
    categories: CategoryOption[];
    selectedTagIds: number[];
}>();

const { t, te } = useI18n();

const selectedIds = ref<Set<number>>(new Set(props.selectedTagIds));
const activeCategoryId = ref<number | null>(null);

const tagsById = computed(() => {
    const map = new Map<number, { name: string; slug: string }>();

    props.categories.forEach((category) => {
        map.set(category.id, category);
        category.children.forEach((child) => map.set(child.id, child));
    });

    return map;
});

const activeCategory = computed(() =>
    props.categories.find((category) => category.id === activeCategoryId.value),
);

function categoryLabel(tag: { slug: string; name: string }): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function toggle(id: number, checked: boolean): void {
    if (checked) {
        selectedIds.value.add(id);
    } else {
        selectedIds.value.delete(id);
    }
}

function remove(id: number): void {
    selectedIds.value.delete(id);
}

function onCategorySelect(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    activeCategoryId.value = value ? Number(value) : null;
}
</script>

<template>
    <Form
        v-bind="update.form(props.contactId)"
        #default="{ processing }"
        class="mt-2 flex flex-col gap-2"
    >
        <input
            v-for="id in selectedIds"
            :key="id"
            type="hidden"
            name="tag_ids[]"
            :value="id"
        />

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-black/70">{{
                t('contacts.tagPreferences.notifyMeAbout')
            }}</span>
            <span
                v-for="id in selectedIds"
                :key="id"
                class="flex items-center gap-1 border border-black px-2 py-0.5 text-xs"
            >
                {{ tagsById.get(id) ? categoryLabel(tagsById.get(id)!) : id }}
                <button type="button" class="font-bold" @click="remove(id)">
                    ×
                </button>
            </span>
            <span
                v-if="!props.categories.length"
                class="text-xs text-black/70"
                >{{ t('contacts.tagPreferences.noTags') }}</span
            >
        </div>

        <div
            v-if="props.categories.length"
            class="flex flex-wrap items-center gap-2"
        >
            <select
                :value="activeCategoryId ?? ''"
                class="border border-black bg-white px-2 py-1 text-xs"
                @change="onCategorySelect"
            >
                <option value="">
                    {{ t('contacts.tagPreferences.chooseCategory') }}
                </option>
                <option
                    v-for="category in props.categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ categoryLabel(category) }}
                </option>
            </select>

            <button
                type="submit"
                :disabled="processing"
                class="border border-black px-2 py-0.5 text-xs hover:bg-black hover:text-white"
            >
                {{ t('contacts.tagPreferences.save') }}
            </button>
        </div>

        <div
            v-if="activeCategory"
            class="flex flex-wrap items-center gap-3 border border-black p-2"
        >
            <label class="flex items-center gap-1 text-xs font-bold">
                <input
                    type="checkbox"
                    :checked="selectedIds.has(activeCategory.id)"
                    class="border border-black"
                    @change="
                        toggle(
                            activeCategory.id,
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
                {{ categoryLabel(activeCategory) }}
            </label>
            <label
                v-for="child in activeCategory.children"
                :key="child.id"
                class="flex items-center gap-1 text-xs"
            >
                <input
                    type="checkbox"
                    :checked="selectedIds.has(child.id)"
                    class="border border-black"
                    @change="
                        toggle(
                            child.id,
                            ($event.target as HTMLInputElement).checked,
                        )
                    "
                />
                {{ categoryLabel(child) }}
            </label>
        </div>
    </Form>
</template>
