<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDialog from '@/components/ui/AppDialog.vue';
import Button from '@/components/ui/Button.vue';
import FilterChip from '@/components/ui/FilterChip.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import type { CategoryOption, TagOption } from '@/events';
import { toastError } from '@/lib/toast';
import { update as updateTagPreferences } from '@/routes/contacts/tag-preferences';
import type { Person } from './people';

/**
 * Which of someone's events stay out of your feed: all of them, or some
 * kinds. A main category hides everything under it; picking some of its
 * subcategories narrows it to those. Invitations always show. Each change
 * saves at once.
 */
const props = defineProps<{
    person: Person | null;
    categories: CategoryOption[];
    tagIds: number[];
    muteAll: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const { t, te } = useI18n();

const muted = ref(new Set<number>(props.tagIds));
const all = ref(props.muteAll);

watch(open, (isOpen) => {
    if (isOpen) {
        muted.value = new Set(props.tagIds);
        all.value = props.muteAll;
    }
});

function label(tag: TagOption): string {
    const key = `categories.${tag.slug}`;

    return te(key) ? t(key) : tag.name;
}

function save(): void {
    if (!props.person) {
        return;
    }

    router.put(
        updateTagPreferences(props.person.id).url,
        { tag_ids: [...muted.value], mute_all: all.value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['friends', 'following'],
            onError: () => toastError(t('settings.saveFailed')),
        },
    );
}

function setAll(on: boolean): void {
    all.value = on;
    muted.value = new Set();
    save();
}

function narrowed(category: CategoryOption): number[] {
    return category.children
        .filter((child) => muted.value.has(child.id))
        .map((child) => child.id);
}

function isMuted(category: CategoryOption): boolean {
    return muted.value.has(category.id) || narrowed(category).length > 0;
}

function without(category: CategoryOption): Set<number> {
    const next = new Set(muted.value);

    next.delete(category.id);
    category.children.forEach((child) => next.delete(child.id));

    return next;
}

function toggleCategory(category: CategoryOption, on: boolean): void {
    const next = without(category);

    if (on) {
        next.add(category.id);
    }

    muted.value = next;
    save();
}

function toggleChild(
    category: CategoryOption,
    child: TagOption,
    on: boolean,
): void {
    const children = new Set(narrowed(category));

    if (on) {
        children.add(child.id);
    } else {
        children.delete(child.id);
    }

    const next = without(category);

    // Back out of the last subcategory: the whole category again.
    if (children.size === 0) {
        next.add(category.id);
    } else {
        children.forEach((id) => next.add(id));
    }

    muted.value = next;
    save();
}

const mutedCategories = computed(() => props.categories.filter(isMuted));
</script>

<template>
    <AppDialog
        v-model:open="open"
        :title="t('people.mute.title', { name: props.person?.name ?? '' })"
        :description="t('people.mute.description')"
        size="lg"
    >
        <div
            class="-mx-4 overflow-hidden border-y border-line sm:mx-0 sm:rounded-lg sm:border"
        >
            <SwitchRow
                :model-value="all"
                :title="
                    t('people.mute.all', { name: props.person?.name ?? '' })
                "
                :description="t('people.mute.allHint')"
                @update:model-value="setAll"
            />
        </div>

        <section v-if="!all" class="mt-5">
            <h3 class="mb-2.5 text-label font-semibold text-ink">
                {{ t('people.mute.someKinds') }}
            </h3>
            <div class="flex flex-wrap gap-2">
                <FilterChip
                    v-for="category in props.categories"
                    :key="category.id"
                    small
                    :pressed="isMuted(category)"
                    @update:pressed="toggleCategory(category, $event)"
                >
                    {{ label(category) }}
                </FilterChip>
            </div>

            <div
                v-for="category in mutedCategories"
                v-show="category.children.length"
                :key="category.id"
                class="mt-3 rounded-md bg-surface-2 px-3.5 py-3"
            >
                <p class="mb-2 text-meta font-semibold text-ink-muted">
                    {{
                        t('people.mute.onlySome', { category: label(category) })
                    }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <FilterChip
                        v-for="child in category.children"
                        :key="child.id"
                        small
                        :pressed="muted.has(child.id)"
                        @update:pressed="toggleChild(category, child, $event)"
                    >
                        {{ label(child) }}
                    </FilterChip>
                </div>
            </div>
        </section>

        <template #actions>
            <Button variant="primary" @click="open = false">
                {{ t('people.done') }}
            </Button>
        </template>
    </AppDialog>
</template>
