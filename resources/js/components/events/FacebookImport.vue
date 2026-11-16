<script setup lang="ts">
import { Check, Link2 } from '@lucide/vue';
import { nextTick, ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import TextField from '@/components/ui/TextField.vue';
import {
    cityFromAddress,
    countryCodeFromAddress,
    countryFromAddress,
    reverseGeocode,
} from '@/composables/useNominatimSearch';
import { requestJson, RequestError } from '@/realtime';
import { facebook as importFromFacebook } from '@/routes/events/import';

/** What the server read from the Facebook event. */
interface FacebookEventDetails {
    name: string;
    description: string;
    starts_at: string;
    ends_at: string | null;
    place: string | null;
    latitude: number | null;
    longitude: number | null;
    city: string | null;
    /** The cover picture, as a data: URL. */
    image: string | null;
}

/** The fields of the event form an import fills in. */
export interface ImportedEvent {
    name: string;
    description: string;
    starts_at: string;
    ends_at: string | null;
    place: string | null;
    latitude: number | null;
    longitude: number | null;
    city: string | null;
    country: string | null;
    country_code: string | null;
}

/**
 * "Copy from a Facebook event": takes a link to a public event, has the
 * server read it, and reports the details for the new-event form to start
 * from. Nothing is saved here. It is a form of its own, so it must sit
 * outside the event form.
 */
const emit = defineEmits<{
    imported: [event: ImportedEvent, image: Blob | null];
}>();

const { t, locale } = useI18n();

const isOpen = ref(false);
const link = ref('');
const fetching = ref(false);
const error = ref<string | null>(null);
const done = ref(false);
const form = useTemplateRef<HTMLFormElement>('form');

async function open(): Promise<void> {
    isOpen.value = true;
    done.value = false;
    await nextTick();
    form.value?.querySelector('input')?.focus();
}

function blobFromDataUrl(dataUrl: string): Blob | null {
    const match = /^data:(image\/[a-z]+);base64,(.+)$/.exec(dataUrl);

    if (!match) {
        return null;
    }

    try {
        const bytes = Uint8Array.from(atob(match[2]), (character) =>
            character.charCodeAt(0),
        );

        return new Blob([bytes], { type: match[1] });
    } catch {
        return null;
    }
}

function messageFor(reason: unknown): string {
    const status = reason instanceof RequestError ? reason.status : 0;

    if (status === 422) {
        return t('events.import.errors.notAnEvent');
    }

    if (status === 404) {
        return t('events.import.errors.unreadable');
    }

    if (status === 429) {
        return t('events.import.errors.tooMany');
    }

    return t('events.import.errors.failed');
}

async function fetchDetails(): Promise<void> {
    if (fetching.value || link.value.trim() === '') {
        return;
    }

    fetching.value = true;
    error.value = null;

    try {
        const details = await requestJson<FacebookEventDetails>(
            importFromFacebook(),
            { url: link.value.trim() },
        );

        if (!details) {
            throw new RequestError(0, 'No details in the answer');
        }

        const { image, ...fields } = details;
        const event: ImportedEvent = {
            ...fields,
            country: null,
            country_code: null,
        };

        // The pin's city and country as the map names them elsewhere in
        // the app; Facebook's city stays if the lookup fails.
        if (event.latitude !== null && event.longitude !== null) {
            const found = await reverseGeocode(
                event.latitude,
                event.longitude,
                locale.value,
            );

            if (found?.address) {
                event.city = cityFromAddress(found.address) ?? event.city;
                event.country = countryFromAddress(found.address);
                event.country_code = countryCodeFromAddress(found.address);
            }
        }

        emit('imported', event, image ? blobFromDataUrl(image) : null);
        link.value = '';
        isOpen.value = false;
        done.value = true;
    } catch (reason) {
        error.value = messageFor(reason);
    } finally {
        fetching.value = false;
    }
}
</script>

<template>
    <div>
        <form
            v-if="isOpen"
            ref="form"
            class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4"
            @submit.prevent="fetchDetails"
        >
            <TextField
                v-model="link"
                type="url"
                inputmode="url"
                autocomplete="off"
                :label="t('events.import.linkLabel')"
                :placeholder="t('events.import.linkPlaceholder')"
                :hint="error ? undefined : t('events.import.hint')"
                :error="error ?? undefined"
                :disabled="fetching"
                required
                maxlength="2048"
            />
            <div class="flex items-center gap-2">
                <Button type="submit" :disabled="fetching">
                    {{
                        fetching
                            ? t('events.import.fetching')
                            : t('events.import.fetch')
                    }}
                </Button>
                <Button
                    variant="ghost"
                    :disabled="fetching"
                    @click="isOpen = false"
                >
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </form>
        <Button v-else size="sm" :icon="Link2" @click="open">
            {{ t('events.import.open') }}
        </Button>
        <p
            class="flex items-start gap-1.5 text-meta text-ink-muted"
            :class="done && !isOpen ? 'mt-2.5' : 'sr-only'"
            role="status"
        >
            <template v-if="done && !isOpen">
                <Check
                    :size="16"
                    :stroke-width="2"
                    class="mt-0.5 shrink-0"
                    aria-hidden="true"
                />
                {{ t('events.import.done') }}
            </template>
        </p>
    </div>
</template>
