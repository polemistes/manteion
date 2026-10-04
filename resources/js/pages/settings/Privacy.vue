<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsGroup from '@/components/settings/SettingsGroup.vue';
import SettingsLayout from '@/components/settings/SettingsLayout.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import { toastError } from '@/lib/toast';
import { update as updatePrivacy } from '@/routes/settings/privacy';

type PrivacyKey =
    | 'allow_public_follow'
    | 'show_friends_list'
    | 'appear_in_friends_lists'
    | 'searchable';

/** Who may follow you, find you and see your friends. Each switch saves at once. */
const props = defineProps<{
    privacy: Record<PrivacyKey, boolean>;
}>();

const { t } = useI18n();

const values = reactive({ ...props.privacy });

watch(
    () => props.privacy,
    (next) => Object.assign(values, next),
);

function save(key: PrivacyKey, value: boolean): void {
    const previous = values[key];

    values[key] = value;
    router.put(
        updatePrivacy().url,
        { [key]: value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['privacy'],
            onError: () => {
                values[key] = previous;
                toastError(t('settings.saveFailed'));
            },
        },
    );
}

const groups: { label: string; keys: PrivacyKey[] }[] = [
    {
        label: 'settings.privacy.followers',
        keys: ['allow_public_follow'],
    },
    {
        label: 'settings.privacy.findingYou',
        keys: ['searchable', 'show_friends_list', 'appear_in_friends_lists'],
    },
];
</script>

<template>
    <SettingsLayout
        section="privacy"
        :title="t('settings.sections.privacy')"
        :lede="t('settings.privacy.lede')"
    >
        <SettingsGroup
            v-for="group in groups"
            :key="group.label"
            :label="t(group.label)"
        >
            <SwitchRow
                v-for="key in group.keys"
                :key="key"
                :model-value="values[key]"
                :title="t(`settings.privacy.${key}.title`)"
                :description="t(`settings.privacy.${key}.description`)"
                @update:model-value="save(key, $event)"
            />
        </SettingsGroup>
    </SettingsLayout>
</template>
