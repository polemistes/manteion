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

const props = defineProps<{
    categories: CategoryOption[];
    modelValue: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const { t, te } = useI18n();

const isOpen = ref(false);
const root = ref<HTMLDivElement | null>(null);

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function isSelected(slug: string): boolean {
    return props.modelValue.includes(slug);
}

const summaryLabel = computed(() => {
    if (props.modelValue.length === 0) {
        return t('events.index.anyCategory');
    }

    if (props.modelValue.length === 1) {
        const tag = tagBySlug(props.modelValue[0]);

        return tag ? categoryLabel(tag) : props.modelValue[0];
    }

    return t('categoryPicker.selectedCount', {
        count: props.modelValue.length,
    });
});

function tagBySlug(slug: string): TagOption | undefined {
    for (const category of props.categories) {
        if (category.slug === slug) {
            return category;
        }

        const child = category.children.find((c) => c.slug === slug);

        if (child) {
            return child;
        }
    }

    return undefined;
}

// A category has two effective states: broadly picked (its own slug alone
// in modelValue — matches any event under it, subcategory or not) or
// narrowed to specific subcategories (their slugs, category slug removed —
// matches only those, even if that happens to be every subcategory). The
// category checkbox reflects/controls only the "broad" state, so it's
// unchecked and disabled while narrowed — picking any one subcategory (the
// common case) shouldn't require first unchecking every other one. Backing
// out of the last checked subcategory falls back to the broad state rather
// than leaving nothing selected for this category.
function checkedChildSlugs(category: CategoryOption): Set<string> {
    return new Set(
        category.children
            .filter((child) => isSelected(child.slug))
            .map((child) => child.slug),
    );
}

function isCategoryDisabled(category: CategoryOption): boolean {
    return checkedChildSlugs(category).size > 0;
}

function isExpanded(category: CategoryOption): boolean {
    return isSelected(category.slug) || checkedChildSlugs(category).size > 0;
}

function withoutCategory(category: CategoryOption): string[] {
    return props.modelValue.filter(
        (slug) =>
            slug !== category.slug &&
            !category.children.some((child) => child.slug === slug),
    );
}

function toggleCategory(category: CategoryOption, checked: boolean): void {
    emit(
        'update:modelValue',
        checked
            ? [...withoutCategory(category), category.slug]
            : withoutCategory(category),
    );
}

function toggleChild(
    category: CategoryOption,
    child: TagOption,
    checked: boolean,
): void {
    const next = checkedChildSlugs(category);

    if (checked) {
        next.add(child.slug);
    } else {
        next.delete(child.slug);
    }

    const base = withoutCategory(category);

    if (next.size === 0) {
        emit('update:modelValue', [...base, category.slug]);
    } else {
        emit('update:modelValue', [...base, ...next]);
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
        <p class="mb-1 text-xs font-bold">{{ t('events.index.category') }}</p>
        <button
            type="button"
            class="border border-black bg-white px-2 py-1 text-left text-sm"
            @click="toggleOpen"
        >
            {{ summaryLabel }}
        </button>

        <div
            v-if="isOpen"
            class="absolute z-20 mt-1 max-h-96 w-64 overflow-y-auto border border-black bg-white p-2"
        >
            <div
                v-for="category in props.categories"
                :key="category.id"
                class="mb-2"
            >
                <label
                    class="flex items-center gap-2 text-sm"
                    :class="{ 'opacity-50': isCategoryDisabled(category) }"
                >
                    <input
                        type="checkbox"
                        :checked="isSelected(category.slug)"
                        :disabled="isCategoryDisabled(category)"
                        class="border border-black"
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
                    v-if="isExpanded(category) && category.children.length"
                    class="mt-1 flex flex-col gap-1 pl-6"
                >
                    <label
                        v-for="child in category.children"
                        :key="child.id"
                        class="flex items-center gap-2 text-xs"
                    >
                        <input
                            type="checkbox"
                            :checked="isSelected(child.slug)"
                            class="border border-black"
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
                class="mt-1 w-full border border-black bg-black px-2 py-1 text-xs font-bold text-white hover:bg-white hover:text-black"
                @click="close"
            >
                {{ t('categoryPicker.done') }}
            </button>
        </div>
    </div>
</template>
