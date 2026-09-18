<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface EmojiData {
    groups: string[];
    emoji: [number, string, string][];
}

const props = defineProps<{ open: boolean }>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    select: [emoji: string];
}>();

const { t } = useI18n();

// The full Unicode list (about 1900 entries) is loaded the first time the
// picker opens, not with the page.
const data = ref<EmojiData | null>(null);
const group = ref(0);
const query = ref('');

async function load(): Promise<void> {
    if (data.value === null) {
        data.value = (await import('@/emoji-data.json')).default as EmojiData;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            void load();
            query.value = '';
        }
    },
    { immediate: true },
);

const visible = computed(() => {
    if (data.value === null) {
        return [];
    }

    const term = query.value.trim().toLowerCase();

    if (term !== '') {
        return data.value.emoji.filter(([, , name]) => name.includes(term));
    }

    return data.value.emoji.filter(([g]) => g === group.value);
});

// The first emoji of each group stands in as its tab icon.
const groupIcons = computed(() =>
    data.value === null
        ? []
        : data.value.groups.map(
              (_, index) =>
                  data.value!.emoji.find(([g]) => g === index)?.[1] ?? '',
          ),
);

function close(): void {
    emit('update:open', false);
}

function choose(emoji: string): void {
    emit('select', emoji);
    close();
}

function onKeydown(event: KeyboardEvent): void {
    if (props.open && event.key === 'Escape') {
        close();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="props.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4"
        @click.self="close"
    >
        <div
            class="flex max-h-[80vh] w-full max-w-lg flex-col border-frame border-edge bg-surface-raised p-4"
            role="dialog"
            :aria-label="t('reactions.pickerTitle')"
        >
            <div class="mb-3 flex items-center gap-2">
                <input
                    v-model="query"
                    type="search"
                    :placeholder="t('reactions.search')"
                    :aria-label="t('reactions.search')"
                    class="min-w-0 flex-1 border border-edge bg-surface-raised px-3 py-2 font-sans text-sm focus:ring-1 focus:ring-accent focus:outline-none"
                />
                <button
                    type="button"
                    class="shrink-0 border border-edge px-2 py-1 text-xs hover:bg-accent hover:text-on-accent"
                    @click="close"
                >
                    {{ t('reactions.close') }}
                </button>
            </div>

            <div
                v-if="data && !query.trim()"
                class="mb-3 flex flex-wrap gap-1"
                role="tablist"
            >
                <button
                    v-for="(icon, index) in groupIcons"
                    :key="index"
                    type="button"
                    role="tab"
                    :aria-selected="group === index"
                    :title="t(`reactions.groups.${index}`)"
                    class="border border-edge px-2 py-1 text-lg leading-none"
                    :class="group === index ? 'bg-accent' : 'hover:bg-surface'"
                    @click="group = index"
                >
                    {{ icon }}
                </button>
            </div>

            <p v-if="!data" class="text-sm text-ink/70">
                {{ t('reactions.loading') }}
            </p>

            <div v-else class="min-h-0 flex-1 overflow-y-auto">
                <p v-if="!visible.length" class="text-sm text-ink/70">
                    {{ t('reactions.noMatches') }}
                </p>
                <div class="grid grid-cols-8 gap-1 sm:grid-cols-10">
                    <button
                        v-for="[, emoji, name] in visible"
                        :key="emoji"
                        type="button"
                        :title="name"
                        :aria-label="name"
                        class="aspect-square text-2xl leading-none hover:bg-surface"
                        @click="choose(emoji)"
                    >
                        {{ emoji }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
