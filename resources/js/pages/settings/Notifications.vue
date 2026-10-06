<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    BellOff,
    BellRing,
    Laptop,
    Send,
    Share,
    Smartphone,
    Tablet,
} from '@lucide/vue';
import type { Component } from 'vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { useI18n } from 'vue-i18n';
import SettingsGroup from '@/components/settings/SettingsGroup.vue';
import SettingsLayout from '@/components/settings/SettingsLayout.vue';
import Button from '@/components/ui/Button.vue';
import ListRow from '@/components/ui/ListRow.vue';
import Switch from '@/components/ui/Switch.vue';
import SwitchRow from '@/components/ui/SwitchRow.vue';
import Tag from '@/components/ui/Tag.vue';
import { confirmAction } from '@/composables/useConfirm';
import { useDates } from '@/lib/dates';
import { toast, toastError } from '@/lib/toast';
import {
    currentEndpoint,
    permission,
    pushSupport,
    subscribe,
    unsubscribe,
} from '@/pwa/push';
import type { PushSupport } from '@/pwa/push';
import { onPushMessage } from '@/pwa/serviceWorker';
import { requestJson } from '@/realtime/http';
import { destroyDevice } from '@/routes/push-subscriptions';
import {
    test as sendTestPush,
    update as updateNotifications,
} from '@/routes/settings/notifications';
import type { AdminAlertKind, NotificationCategory } from '@/types';

/**
 * What reaches the user, and where: this device's push switch (with a
 * test), one switch per kind of notification, message previews, and the
 * devices that get pushes. Everything saves as it changes.
 */
const props = defineProps<{
    notificationSettings: {
        push: Record<NotificationCategory, boolean>;
        message_previews: boolean;
        admin_email: Record<AdminAlertKind, boolean>;
    };
    categories: { value: NotificationCategory; push_default: boolean }[];
    devices: {
        id: number;
        label: string;
        created_at: string;
        endpoint_hash: string;
    }[];
}>();

const { t } = useI18n();
const dates = useDates();
const page = usePage();

// This device. Push is a browser matter, so it is only known once mounted.
type DeviceSupport = PushSupport | 'unconfigured' | 'checking';

const support = ref<DeviceSupport>('checking');
const blocked = ref(false);
const thisEndpointHash = ref<string | null>(null);
const switching = ref(false);

async function sha256(text: string): Promise<string> {
    const digest = await crypto.subtle.digest(
        'SHA-256',
        new TextEncoder().encode(text),
    );

    return [...new Uint8Array(digest)]
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

async function refreshThisDevice(): Promise<void> {
    const endpoint = await currentEndpoint();

    thisEndpointHash.value = endpoint ? await sha256(endpoint) : null;
}

const thisDevice = computed(
    () =>
        props.devices.find(
            (device) => device.endpoint_hash === thisEndpointHash.value,
        ) ?? null,
);

const deviceOn = computed(() => thisDevice.value !== null);

async function setDevice(on: boolean): Promise<void> {
    if (switching.value) {
        return;
    }

    switching.value = true;

    try {
        if (on) {
            // Straight from the tap: browsers only ask for permission then.
            const result = await subscribe(page.props.vapidPublicKey ?? '');

            if (result === 'denied') {
                blocked.value = true;
            } else if (result === 'failed' || result === 'unsupported') {
                toastError(t('settings.notifications.device.failed'));
            }
        } else {
            await unsubscribe();
        }

        await refreshThisDevice();
        router.reload({ only: ['devices'] });
    } finally {
        switching.value = false;
    }
}

// "Send a test", and whether it arrived (the service worker says so).
const LAST_TEST_KEY = 'manteion.lastPushTest';
const lastTestAt = ref<number | null>(null);
const testing = ref(false);
const waitingForTest = ref(false);
const testMissing = ref(false);
let testTimer: ReturnType<typeof setTimeout> | undefined;

async function sendTest(): Promise<void> {
    const endpoint = await currentEndpoint();

    if (!endpoint) {
        return;
    }

    testing.value = true;
    testMissing.value = false;

    try {
        await requestJson(sendTestPush(), { endpoint });
        waitingForTest.value = true;
        clearTimeout(testTimer);
        testTimer = setTimeout(() => {
            if (waitingForTest.value) {
                waitingForTest.value = false;
                testMissing.value = true;
            }
        }, 20_000);
    } catch {
        toastError(t('settings.notifications.device.testFailed'));
    } finally {
        testing.value = false;
    }
}

onPushMessage((data) => {
    if (data.type !== 'test') {
        return;
    }

    clearTimeout(testTimer);
    waitingForTest.value = false;
    testMissing.value = false;
    lastTestAt.value = Date.now();

    try {
        window.localStorage.setItem(LAST_TEST_KEY, String(lastTestAt.value));
    } catch {
        // Fine: it is only shown while the page is open.
    }
});

onMounted(async () => {
    const supported = pushSupport();

    support.value =
        supported === 'supported' && !page.props.vapidPublicKey
            ? 'unconfigured'
            : supported;
    blocked.value = permission() === 'denied';

    try {
        lastTestAt.value =
            Number(window.localStorage.getItem(LAST_TEST_KEY)) || null;
    } catch {
        lastTestAt.value = null;
    }

    await refreshThisDevice();
});

onBeforeUnmount(() => clearTimeout(testTimer));

const deviceStatus = computed(() => {
    if (support.value === 'checking') {
        return '';
    }

    if (deviceOn.value) {
        return t('settings.notifications.device.on', {
            device: thisDevice.value!.label,
        });
    }

    return blocked.value
        ? t('settings.notifications.device.blocked')
        : t('settings.notifications.device.off');
});

const testStatus = computed(() => {
    if (waitingForTest.value) {
        return t('settings.notifications.device.testSent');
    }

    if (testMissing.value) {
        return t('settings.notifications.device.testMissing');
    }

    return lastTestAt.value
        ? t('settings.notifications.device.lastTest', {
              ago: dates.ago(new Date(lastTestAt.value)),
          })
        : t('settings.notifications.device.testHint');
});

// The kinds of notification, grouped as people think of them.
const groupOrder: { key: string; categories: NotificationCategory[] }[] = [
    { key: 'messages', categories: ['direct_messages', 'group_messages'] },
    {
        key: 'events',
        categories: ['event_invites', 'event_comments', 'followed_events'],
    },
    { key: 'people', categories: ['friend_requests', 'followers'] },
    { key: 'leaflets', categories: ['leaflet_responses'] },
];

const groups = computed(() => {
    const known = new Set(props.categories.map((category) => category.value));

    return groupOrder
        .map((group) => ({
            ...group,
            categories: group.categories.filter((category) =>
                known.has(category),
            ),
        }))
        .filter((group) => group.categories.length);
});

const push = reactive({ ...props.notificationSettings.push });
const previews = ref(props.notificationSettings.message_previews);
const adminEmail = reactive({ ...props.notificationSettings.admin_email });

watch(
    () => props.notificationSettings,
    (next) => {
        Object.assign(push, next.push);
        previews.value = next.message_previews;
        Object.assign(adminEmail, next.admin_email);
    },
);

// Administrators can ask to be emailed about what happens on the site.
const isAdministrator = computed(
    () => page.props.auth.user.role === 'administrator',
);
const adminKinds: AdminAlertKind[] = [
    'new_user',
    'account_deleted',
    'account_suspended',
    'message_sent',
    'event_created',
    'leaflet_published',
    'report_filed',
];

function setAdminEmail(kind: AdminAlertKind, on: boolean): void {
    const previous = adminEmail[kind];

    adminEmail[kind] = on;
    save({ admin_email: { [kind]: on } }, () => {
        adminEmail[kind] = previous;
    });
}

function save(
    changes: {
        push?: Partial<Record<NotificationCategory, boolean>>;
        message_previews?: boolean;
        admin_email?: Partial<Record<AdminAlertKind, boolean>>;
    },
    revert: () => void,
): void {
    router.put(updateNotifications().url, changes, {
        preserveScroll: true,
        preserveState: true,
        only: ['notificationSettings'],
        onError: () => {
            revert();
            toastError(t('settings.saveFailed'));
        },
    });
}

function setCategory(category: NotificationCategory, on: boolean): void {
    const previous = push[category];

    push[category] = on;
    save({ push: { [category]: on } }, () => {
        push[category] = previous;
    });
}

function setPreviews(on: boolean): void {
    const previous = previews.value;

    previews.value = on;
    save({ message_previews: on }, () => {
        previews.value = previous;
    });
}

// Devices.
function deviceIcon(label: string): Component {
    if (/iPad|tablet|nettbrett/i.test(label)) {
        return Tablet;
    }

    return /iPhone|Android|Pixel|mobil|telefon|phone/i.test(label)
        ? Smartphone
        : Laptop;
}

async function removeDevice(device: { id: number; label: string }) {
    const confirmed = await confirmAction({
        title: t('settings.notifications.devices.removeTitle'),
        message: t('settings.notifications.devices.removeBody', {
            device: device.label,
        }),
        confirmLabel: t('settings.notifications.devices.remove'),
        tone: 'danger',
    });

    if (!confirmed) {
        return;
    }

    router.delete(destroyDevice(device.id).url, {
        preserveScroll: true,
        only: ['devices'],
        onSuccess: () =>
            toast({
                title: t('settings.notifications.devices.removed', {
                    device: device.label,
                }),
                icon: BellOff,
            }),
    });
}
</script>

<template>
    <SettingsLayout
        section="notifications"
        :title="t('settings.sections.notifications')"
        :lede="t('settings.notifications.lede')"
    >
        <!-- This device -->
        <div class="overflow-hidden rounded-lg border border-line bg-surface">
            <template v-if="support === 'needs-install'">
                <div class="flex gap-3.5 p-4 sm:p-5">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                        aria-hidden="true"
                    >
                        <Share :size="20" :stroke-width="1.75" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold text-ink">
                            {{
                                t('settings.notifications.device.installTitle')
                            }}
                        </p>
                        <p class="mt-1 text-meta text-ink-muted">
                            {{ t('settings.notifications.device.installBody') }}
                        </p>
                    </div>
                </div>
            </template>
            <template
                v-else-if="
                    support === 'unsupported' || support === 'unconfigured'
                "
            >
                <div class="flex gap-3.5 p-4 sm:p-5">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                        aria-hidden="true"
                    >
                        <BellOff :size="20" :stroke-width="1.75" />
                    </span>
                    <p class="min-w-0 self-center text-meta text-ink-muted">
                        {{
                            support === 'unconfigured'
                                ? t(
                                      'settings.notifications.device.unconfigured',
                                  )
                                : t('settings.notifications.device.unsupported')
                        }}
                    </p>
                </div>
            </template>
            <template v-else>
                <label
                    for="device-push"
                    class="flex cursor-pointer items-center gap-3.5 p-4 sm:px-5"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-muted"
                        aria-hidden="true"
                    >
                        <BellRing :size="20" :stroke-width="1.75" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium text-ink">{{
                            t('settings.notifications.device.title')
                        }}</span>
                        <span
                            class="mt-0.5 flex items-center gap-1.5 text-meta text-ink-muted"
                        >
                            <span
                                v-if="deviceOn"
                                class="size-[7px] shrink-0 rounded-full bg-success"
                                aria-hidden="true"
                            />
                            <span>{{ deviceStatus }}</span>
                        </span>
                    </span>
                    <Switch
                        id="device-push"
                        :model-value="deviceOn"
                        :disabled="
                            support === 'checking' ||
                            switching ||
                            (blocked && !deviceOn)
                        "
                        @update:model-value="setDevice"
                    />
                </label>
                <div
                    v-if="deviceOn"
                    class="flex items-center gap-3 border-t border-line px-4 py-3 sm:px-5"
                >
                    <p
                        class="min-w-0 flex-1 text-meta text-ink-muted"
                        aria-live="polite"
                    >
                        {{ testStatus }}
                    </p>
                    <Button
                        size="sm"
                        :icon="Send"
                        :disabled="testing || waitingForTest"
                        @click="sendTest"
                    >
                        {{ t('settings.notifications.device.sendTest') }}
                    </Button>
                </div>
            </template>
        </div>

        <SettingsGroup
            :label="t('settings.notifications.notifyMeAbout')"
            :note="t('settings.notifications.defaultsNote')"
        >
            <div
                v-for="(group, index) in groups"
                :key="group.key"
                :class="index > 0 ? 'border-t border-line' : ''"
            >
                <p
                    class="px-4 pt-3.5 pb-0.5 text-meta font-semibold text-ink-muted"
                >
                    {{ t(`settings.notifications.groups.${group.key}`) }}
                </p>
                <div>
                    <SwitchRow
                        v-for="category in group.categories"
                        :key="category"
                        :model-value="push[category]"
                        :title="
                            t(
                                `settings.notifications.categories.${category}.title`,
                            )
                        "
                        :description="
                            t(
                                `settings.notifications.categories.${category}.description`,
                            )
                        "
                        @update:model-value="setCategory(category, $event)"
                    />
                </div>
            </div>
        </SettingsGroup>

        <SettingsGroup :label="t('settings.notifications.privacy')">
            <SwitchRow
                :model-value="previews"
                :title="t('settings.notifications.previews.title')"
                :description="t('settings.notifications.previews.description')"
                @update:model-value="setPreviews"
            />
        </SettingsGroup>

        <SettingsGroup
            v-if="isAdministrator"
            :label="t('settings.notifications.admin.title')"
            :note="t('settings.notifications.admin.note')"
        >
            <SwitchRow
                v-for="kind in adminKinds"
                :key="kind"
                :model-value="adminEmail[kind]"
                :title="t(`settings.notifications.admin.kinds.${kind}`)"
                @update:model-value="setAdminEmail(kind, $event)"
            />
        </SettingsGroup>

        <SettingsGroup
            :label="t('settings.notifications.devices.title')"
            :note="
                props.devices.length
                    ? t('settings.notifications.devices.note')
                    : undefined
            "
        >
            <ListRow
                v-for="device in props.devices"
                :key="device.id"
                :inset="72"
            >
                <template #leading>
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-md bg-surface-2 text-ink-muted"
                        aria-hidden="true"
                    >
                        <component
                            :is="deviceIcon(device.label)"
                            :size="20"
                            :stroke-width="1.75"
                        />
                    </span>
                </template>
                <template #title>
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="truncate">{{ device.label }}</span>
                        <Tag v-if="device.id === thisDevice?.id" tone="success">
                            {{ t('settings.notifications.devices.thisDevice') }}
                        </Tag>
                    </span>
                </template>
                <template #sub>
                    {{
                        t('settings.notifications.devices.added', {
                            date: dates.dayMonth(device.created_at),
                        })
                    }}
                </template>
                <template #trailing>
                    <Button
                        v-if="device.id !== thisDevice?.id"
                        variant="ghost"
                        size="sm"
                        @click="removeDevice(device)"
                    >
                        {{ t('settings.notifications.devices.remove') }}
                    </Button>
                </template>
            </ListRow>
            <p
                v-if="!props.devices.length"
                class="p-4 text-meta text-ink-muted"
            >
                {{ t('settings.notifications.devices.none') }}
            </p>
        </SettingsGroup>
    </SettingsLayout>
</template>
