<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface TagOption {
    id: number;
    name: string;
    slug: string;
}

interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    children: TagOption[];
}

const props = withDefaults(
    defineProps<{
        categories: CategoryOption[];
        initialSelectedIds?: number[];
    }>(),
    { initialSelectedIds: () => [] },
);

const { t, te } = useI18n();

const selectedIds = ref<Set<number>>(new Set(props.initialSelectedIds));
const isOpen = ref(false);
const root = ref<HTMLDivElement | null>(null);

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function tagById(id: number): TagOption | undefined {
    for (const category of props.categories) {
        if (category.id === id) {
            return category;
        }

        const child = category.children.find((c) => c.id === id);

        if (child) {
            return child;
        }
    }

    return undefined;
}

const summaryLabel = computed(() => {
    if (selectedIds.value.size === 0) {
        return t('categoryPicker.chooseCategories');
    }

    if (selectedIds.value.size === 1) {
        const [id] = selectedIds.value;
        const tag = tagById(id);

        return tag ? categoryLabel(tag) : String(id);
    }

    return t('categoryPicker.selectedCount', { count: selectedIds.value.size });
});

// Checking a subcategory also checks its parent category, and the parent
// checkbox stays checked as long as any subcategory is — unlike the events
// list filter, where the two are mutually exclusive. Unchecking the parent
// clears its subcategories too. This doesn't change what actually gets
// saved: EventController::syncTags() already expands whatever tag_ids are
// submitted to include each tag's parent, so a plain ["day-trip"] payload
// and a ["day-trip", "travel"] payload end up storing the exact same tags
// on the event either way — this is purely about the checkbox not looking
// like it cleared itself when the viewer picks a subcategory.
function toggleCategory(category: CategoryOption, checked: boolean): void {
    if (checked) {
        selectedIds.value.add(category.id);
    } else {
        selectedIds.value.delete(category.id);
        category.children.forEach((child) =>
            selectedIds.value.delete(child.id),
        );
    }
}

function toggleChild(
    category: CategoryOption,
    child: TagOption,
    checked: boolean,
): void {
    if (checked) {
        selectedIds.value.add(child.id);
        selectedIds.value.add(category.id);
    } else {
        selectedIds.value.delete(child.id);
    }
}

function toggleOpen(): void {
    isOpen.value = !isOpen.value;
}

function close(): void {
    isOpen.value = false;
}

function onDocumentClick(event: MouseEvent): void {
    if (
        isOpen.value &&
        root.value &&
        !root.value.contains(event.target as Node)
    ) {
        isOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <input
            v-for="id in selectedIds"
            :key="id"
            type="hidden"
            name="tag_ids[]"
            :value="id"
        />

        <button
            type="button"
            class="w-full border border-edge bg-surface-raised px-3 py-2 text-left font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
            @click="toggleOpen"
        >
            {{ summaryLabel }}
        </button>

        <div
            v-if="isOpen"
            class="absolute z-20 mt-1 max-h-96 w-full overflow-y-auto border border-edge bg-surface-raised p-2"
        >
            <div
                v-for="category in props.categories"
                :key="category.id"
                class="mb-2"
            >
                <label class="flex items-center gap-2 text-sm font-bold">
                    <input
                        type="checkbox"
                        :checked="selectedIds.has(category.id)"
                        class="border border-edge"
                        @change="
                            toggleCategory(
                                category,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ categoryLabel(category) }}
                </label>
                <div
                    v-if="
                        selectedIds.has(category.id) && category.children.length
                    "
                    class="mt-1 flex flex-col gap-1 pl-6"
                >
                    <label
                        v-for="child in category.children"
                        :key="child.id"
                        class="flex items-center gap-2 text-xs"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedIds.has(child.id)"
                            class="border border-edge"
                            @change="
                                toggleChild(
                                    category,
                                    child,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        {{ categoryLabel(child) }}
                    </label>
                </div>
            </div>

            <button
                type="button"
                class="mt-1 w-full border border-edge bg-accent px-2 py-1 text-xs font-bold text-on-accent hover:bg-surface-raised hover:text-ink"
                @click="close"
            >
                {{ t('categoryPicker.done') }}
            </button>
        </div>
    </div>
</template>
