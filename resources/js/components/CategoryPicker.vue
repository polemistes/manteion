<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FilterChip from '@/components/ui/FilterChip.vue';
import type { CategoryOption, TagOption } from '@/events';

/**
 * Categories as chips: the main ones first, and under each one picked its
 * subcategories, for anyone who wants to be more precise. The chosen ones
 * submit as `tag_ids[]`; a subcategory's parent is stored with it anyway
 * (EventController::syncTags).
 */
const props = withDefaults(
    defineProps<{
        categories: CategoryOption[];
        initialSelectedIds?: number[];
    }>(),
    { initialSelectedIds: () => [] },
);

const { t, te } = useI18n();

const selected = ref<Set<number>>(new Set(props.initialSelectedIds));

function label(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function isPicked(category: CategoryOption): boolean {
    return (
        selected.value.has(category.id) ||
        category.children.some((child) => selected.value.has(child.id))
    );
}

const pickedCategories = computed(() =>
    props.categories.filter(
        (category) => isPicked(category) && category.children.length,
    ),
);

function toggleCategory(category: CategoryOption, on: boolean): void {
    const next = new Set(selected.value);

    if (on) {
        next.add(category.id);
    } else {
        next.delete(category.id);

        for (const child of category.children) {
            next.delete(child.id);
        }
    }

    selected.value = next;
}

function toggleChild(child: TagOption, on: boolean): void {
    const next = new Set(selected.value);

    if (on) {
        next.add(child.id);
    } else {
        next.delete(child.id);
    }

    selected.value = next;
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <input
            v-for="id in [...selected]"
            :key="id"
            type="hidden"
            name="tag_ids[]"
            :value="id"
        />
        <div class="flex flex-wrap gap-2">
            <FilterChip
                v-for="category in props.categories"
                :key="category.id"
                :pressed="isPicked(category)"
                @update:pressed="toggleCategory(category, $event)"
            >
                {{ label(category) }}
            </FilterChip>
        </div>
        <div
            v-for="category in pickedCategories"
            :key="category.id"
            class="rounded-md bg-surface-2 px-3.5 py-3"
        >
            <p class="mb-2 text-meta font-semibold text-ink-muted">
                {{
                    t('events.form.subcategories', {
                        category: label(category),
                    })
                }}
            </p>
            <div class="flex flex-wrap gap-2">
                <FilterChip
                    v-for="child in category.children"
                    :key="child.id"
                    small
                    :pressed="selected.has(child.id)"
                    @update:pressed="toggleChild(child, $event)"
                >
                    {{ label(child) }}
                </FilterChip>
            </div>
        </div>
    </div>
</template>
