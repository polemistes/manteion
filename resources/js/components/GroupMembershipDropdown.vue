<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { store as addGroupMember } from '@/routes/contact-groups/members';

interface GroupOption {
    id: number;
    name: string;
}

const props = defineProps<{
    personId: number;
    groups: GroupOption[];
}>();

const { t } = useI18n();

const selectedGroupId = ref('');

function onChange(): void {
    if (selectedGroupId.value === '') {
        return;
    }

    router.post(
        addGroupMember(Number(selectedGroupId.value)).url,
        { member_user_id: props.personId },
        { preserveScroll: true },
    );

    selectedGroupId.value = '';
}
</script>

<template>
    <select
        v-if="props.groups.length"
        v-model="selectedGroupId"
        class="border border-black bg-white px-2 py-1 text-xs"
        @change="onChange"
    >
        <option value="">{{ t('contacts.index.addToGroup') }}</option>
        <option v-for="group in props.groups" :key="group.id" :value="group.id">
            {{ group.name }}
        </option>
    </select>
    <span v-else class="border border-black px-2 py-1 text-xs text-black/50">
        {{ t('contacts.index.noMoreGroups') }}
    </span>
</template>
