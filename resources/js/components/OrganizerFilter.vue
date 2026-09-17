<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface ContactOption {
    id: number;
    name: string;
    username: string;
    avatar_url?: string | null;
}

const props = defineProps<{
    friends: ContactOption[];
    following: ContactOption[];
    modelValue: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const { t } = useI18n();

const isOpen = ref(false);
const root = ref<HTMLDivElement | null>(null);

interface Group {
    token: 'friends' | 'following';
    label: string;
    members: ContactOption[];
}

const groups = computed<Group[]>(() => [
    {
        token: 'friends',
        label: t('events.index.friendsGroupLabel'),
        members: props.friends,
    },
    {
        token: 'following',
        label: t('events.index.followingGroupLabel'),
        members: props.following,
    },
]);

function isSelected(token: string): boolean {
    return props.modelValue.includes(token);
}

function memberTokens(group: Group): string[] {
    return group.members.map((member) => String(member.id));
}

function checkedMemberTokens(group: Group): string[] {
    return memberTokens(group).filter((token) => isSelected(token));
}

function withoutGroup(group: Group): string[] {
    const memberTokenSet = new Set(memberTokens(group));

    return props.modelValue.filter(
        (token) => token !== group.token && !memberTokenSet.has(token),
    );
}

function labelFor(token: string): string {
    for (const group of groups.value) {
        if (token === group.token) {
            return group.label;
        }

        const member = group.members.find((m) => String(m.id) === token);

        if (member) {
            return member.name;
        }
    }

    return token;
}

const summaryLabel = computed(() => {
    if (props.modelValue.length === 0) {
        return t('events.index.anyone');
    }

    if (props.modelValue.length === 1) {
        return labelFor(props.modelValue[0]);
    }

    return t('categoryPicker.selectedCount', {
        count: props.modelValue.length,
    });
});

function toggleGroup(group: Group, checked: boolean): void {
    emit(
        'update:modelValue',
        checked ? [...withoutGroup(group), group.token] : withoutGroup(group),
    );
}

function toggleMember(
    group: Group,
    memberToken: string,
    checked: boolean,
): void {
    const next = new Set(checkedMemberTokens(group));

    if (checked) {
        next.add(memberToken);
    } else {
        next.delete(memberToken);
    }

    const base = withoutGroup(group);

    emit(
        'update:modelValue',
        next.size === 0 ? [...base, group.token] : [...base, ...next],
    );
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
        <p class="mb-1 text-xs font-bold">{{ t('events.index.organizer') }}</p>
        <button
            type="button"
            class="border border-edge bg-surface-raised px-2 py-1 text-left text-sm"
            @click="toggleOpen"
        >
            {{ summaryLabel }}
        </button>

        <div
            v-if="isOpen"
            class="absolute z-20 mt-1 max-h-96 w-64 overflow-y-auto border border-edge bg-surface-raised p-2"
        >
            <div v-for="group in groups" :key="group.token" class="mb-2">
                <label class="flex items-center gap-2 text-sm font-bold">
                    <input
                        type="checkbox"
                        :checked="isSelected(group.token)"
                        class="border border-edge"
                        @change="
                            toggleGroup(
                                group,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ group.label }}
                </label>
                <div
                    v-if="group.members.length"
                    class="mt-1 flex flex-col gap-1 pl-6"
                >
                    <label
                        v-for="member in group.members"
                        :key="member.id"
                        class="flex items-center gap-2 text-xs"
                    >
                        <input
                            type="checkbox"
                            :checked="isSelected(String(member.id))"
                            class="border border-edge"
                            @change="
                                toggleMember(
                                    group,
                                    String(member.id),
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        {{ member.name }}
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
