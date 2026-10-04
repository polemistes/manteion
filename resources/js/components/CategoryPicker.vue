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

// The chosen categories by name. A parent whose subcategories are chosen
// is implied by them and left out, so picking "Day trip" reads as just
// that rather than "Travel, Day trip".
const summaryLabel = computed(() => {
    const names: string[] = [];

    for (const category of props.categories) {
        const children = category.children.filter((child) =>
            selectedIds.value.has(child.id),
        );

        if (children.length > 0) {
            names.push(...children.map(categoryLabel));
        } else if (selectedIds.value.has(category.id)) {
            names.push(categoryLabel(category));
        }
    }

    return names.length
        ? names.join(', ')
        : t('categoryPicker.chooseCategories');
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
            class="border-edge bg-surface-raised text-sm w-full border px-3 py-2 text-left font-sans focus:ring-1 focus:ring-accent focus:outline-none"
            @click="toggleOpen"
        >
            {{ summaryLabel }}
        </button>

        <div
            v-if="isOpen"
            class="border-edge bg-surface-raised absolute z-20 mt-1 max-h-96 w-full overflow-y-auto border p-2"
        >
            <div
                v-for="category in props.categories"
                :key="category.id"
                class="mb-2"
            >
                <label class="text-sm flex items-center gap-2 font-bold">
                    <input
                        type="checkbox"
                        :checked="selectedIds.has(category.id)"
                        class="border-edge border"
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
                        class="text-xs flex items-center gap-2"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedIds.has(child.id)"
                            class="border-edge border"
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
                class="border-edge text-xs hover:bg-surface-raised mt-1 w-full border bg-accent px-2 py-1 font-bold text-on-accent hover:text-ink"
                @click="close"
            >
                {{ t('categoryPicker.done') }}
            </button>
        </div>
    </div>
</template>
