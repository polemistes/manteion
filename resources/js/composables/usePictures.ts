import { onBeforeUnmount, ref } from 'vue';
import type { Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { prepareImageUpload } from '@/lib/image';
import type { PreparedImage } from '@/lib/image';
import { toastError } from '@/lib/toast';
import { MAX_PICTURES, PICTURE_MAX_BYTES, PICTURE_MAX_EDGE } from '@/pictures';

/**
 * The pictures someone is about to send with a message, a leaflet or a
 * comment: each one picked is scaled down and stripped of its metadata in
 * the browser (lib/image.ts), kept with a preview, and can be taken off
 * again. `room` says how many more fit, counting pictures already on the
 * thing being edited. Says so when one cannot be read or there are too
 * many. The previews are released when the component goes, or by take().
 */
export function usePictures(options: { room?: () => number } = {}): {
    pictures: Ref<PreparedImage[]>;
    preparing: Ref<boolean>;
    add: (files: FileList | File[] | null | undefined) => Promise<void>;
    remove: (index: number) => void;
    clear: () => void;
    take: () => PreparedImage[];
} {
    const { t } = useI18n();
    const pictures = ref<PreparedImage[]>([]);
    const preparing = ref(false);

    const room = (): number =>
        (options.room?.() ?? MAX_PICTURES) - pictures.value.length;

    async function add(
        files: FileList | File[] | null | undefined,
    ): Promise<void> {
        const picked = [...(files ?? [])];

        if (picked.length === 0) {
            return;
        }

        const fitting = picked.slice(0, Math.max(0, room()));

        if (fitting.length < picked.length) {
            toastError(t('pictures.tooMany', { max: MAX_PICTURES }));
        }

        preparing.value = true;

        let unreadable = 0;

        try {
            // One after the other, in the order picked: a phone has little
            // memory to decode several large photos at once.
            for (const file of fitting) {
                try {
                    pictures.value = [
                        ...pictures.value,
                        await prepareImageUpload(
                            file,
                            PICTURE_MAX_EDGE,
                            PICTURE_MAX_BYTES,
                        ),
                    ];
                } catch {
                    unreadable += 1;
                }
            }
        } finally {
            preparing.value = false;
        }

        if (unreadable > 0) {
            toastError(t('pictures.unreadable', { n: unreadable }, unreadable));
        }
    }

    function remove(index: number): void {
        const gone = pictures.value[index];

        if (gone) {
            URL.revokeObjectURL(gone.previewUrl);
            pictures.value = pictures.value.filter((_, at) => at !== index);
        }
    }

    function clear(): void {
        for (const picture of pictures.value) {
            URL.revokeObjectURL(picture.previewUrl);
        }

        pictures.value = [];
    }

    /** Hands the pictures over, previews and all, and starts afresh. */
    function take(): PreparedImage[] {
        const taken = pictures.value;

        pictures.value = [];

        return taken;
    }

    onBeforeUnmount(clear);

    return { pictures, preparing, add, remove, clear, take };
}

/**
 * Puts prepared pictures into a hidden file input, so a form sent the
 * ordinary way (an Inertia <Form>) carries them under the input's name.
 */
export function fillFileInput(
    input: HTMLInputElement | null,
    pictures: PreparedImage[],
): void {
    if (!input) {
        return;
    }

    const transfer = new DataTransfer();

    for (const picture of pictures) {
        transfer.items.add(picture.file);
    }

    input.files = transfer.files;
}
