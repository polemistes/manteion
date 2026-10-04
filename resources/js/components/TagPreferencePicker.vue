<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { update } from '@/routes/contacts/tag-preferences';

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
    contactId: number;
    categories: CategoryOption[];
    excludedTagIds: number[];
    muteAll: boolean;
}>();

const { t, te } = useI18n();

const excludedIds = ref<Set<number>>(new Set(props.excludedTagIds));
const muteAll = ref(props.muteAll);
const isOpen = ref(false);
const root = ref<HTMLDivElement | null>(null);

const tagsById = computed(() => {
    const map = new Map<number, TagOption>();

    props.categories.forEach((category) => {
        map.set(category.id, category);
        category.children.forEach((child) => map.set(child.id, child));
    });

    return map;
});

function categoryLabel(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

const summaryLabel = computed(() => {
    if (muteAll.value) {
        return t('contacts.tagPreferences.notifyNone');
    }

    if (excludedIds.value.size === 0) {
        return t('contacts.tagPreferences.notifyAll');
    }

    if (excludedIds.value.size === 1) {
        const [id] = excludedIds.value;
        const tag = tagsById.value.get(id);

        return t('contacts.tagPreferences.notifyAllExceptOne', {
            category: tag ? categoryLabel(tag) : String(id),
        });
    }

    return t('contacts.tagPreferences.notifyAllExceptCount', {
        count: excludedIds.value.size,
    });
});

// A category has two effective states: broadly excluded (its own id alone
// in excludedIds — mutes any event under it, subcategory or not) or
// narrowed to specific subcategories (their ids, category id removed —
// mutes only those, even if that happens to be every subcategory). The
// category checkbox reflects/controls only the "broad" state, so it's
// unchecked and disabled while narrowed — muting one subcategory (the
// common case) shouldn't require first unchecking every other one. Backing
// out of the last checked subcategory falls back to the broad state rather
// than leaving nothing muted for this category. Mirrors the events list
// filter's CategoryFilterList.vue.
function checkedChildIds(category: CategoryOption): Set<number> {
    return new Set(
        category.children
            .filter((child) => excludedIds.value.has(child.id))
            .map((child) => child.id),
    );
}

function isCategoryDisabled(category: CategoryOption): boolean {
    return checkedChildIds(category).size > 0;
}

function isExpanded(category: CategoryOption): boolean {
    return (
        excludedIds.value.has(category.id) || checkedChildIds(category).size > 0
    );
}

function withoutCategory(category: CategoryOption): Set<number> {
    const next = new Set(excludedIds.value);
    next.delete(category.id);
    category.children.forEach((child) => next.delete(child.id));

    return next;
}

function save(next: Set<number>): void {
    excludedIds.value = next;

    router.put(
        update(props.contactId).url,
        { tag_ids: [...next], mute_all: muteAll.value },
        { preserveScroll: true, preserveState: true },
    );
}

function toggleMuteAll(checked: boolean): void {
    muteAll.value = checked;
    save(new Set());
}

function toggleCategory(category: CategoryOption, checked: boolean): void {
    const next = withoutCategory(category);

    if (checked) {
        next.add(category.id);
    }

    save(next);
}

function toggleChild(
    category: CategoryOption,
    child: TagOption,
    checked: boolean,
): void {
    const childIds = checkedChildIds(category);

    if (checked) {
        childIds.add(child.id);
    } else {
        childIds.delete(child.id);
    }

    const next = withoutCategory(category);

    if (childIds.size === 0) {
        next.add(category.id);
    } else {
        childIds.forEach((id) => next.add(id));
    }

    save(next);
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
    <div v-if="props.categories.length" ref="root" class="relative">
        <button
            type="button"
            class="border-edge bg-surface-raised text-xs border px-2 py-1 text-left"
            @click="toggleOpen"
        >
            {{ summaryLabel }}
        </button>

        <div
            v-if="isOpen"
            class="border-edge bg-surface-raised absolute z-20 mt-1 max-h-96 w-64 overflow-y-auto border p-2"
        >
            <p class="text-xs mb-2 font-bold uppercase">
                {{ t('contacts.tagPreferences.excludeHeading') }}
            </p>
            <div
                v-for="category in props.categories"
                :key="category.id"
                class="mb-2"
            >
                <label
                    class="text-sm flex items-center gap-2"
                    :class="{
                        'opacity-50': isCategoryDisabled(category) || muteAll,
                    }"
                >
                    <input
                        type="checkbox"
                        :checked="isExpanded(category)"
                        :disabled="isCategoryDisabled(category) || muteAll"
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
                    v-if="isExpanded(category) && category.children.length"
                    class="mt-1 flex flex-col gap-1 pl-6"
                >
                    <label
                        v-for="child in category.children"
                        :key="child.id"
                        class="text-xs flex items-center gap-2"
                    >
                        <input
                            type="checkbox"
                            :checked="excludedIds.has(child.id)"
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

            <div class="border-edge mb-2 border-t pt-2">
                <label class="text-sm flex items-center gap-2 font-bold">
                    <input
                        type="checkbox"
                        :checked="muteAll"
                        class="border-edge border"
                        @change="
                            toggleMuteAll(
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ t('contacts.tagPreferences.everything') }}
                </label>
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
