import { router } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AttendanceStatus, AttendanceVisibility } from '@/events';
import { toast, toastError } from '@/lib/toast';
import { update as updateAttendance } from '@/routes/events/attendance';

export interface RsvpState {
    status: AttendanceStatus | null;
    visibility: AttendanceVisibility | null;
}

/**
 * The viewer's answer to one event, saved the moment it changes: the new
 * answer shows at once, a toast says who can see it (with Undo), and a
 * failed save puts the old answer back. `reload` names the page props to
 * refresh afterwards (the counts and faces that changed).
 */
export function useRsvp(options: {
    eventId: () => number;
    host: () => string;
    initial: () => RsvpState;
    reload: string[];
}) {
    const { t } = useI18n();

    const status = ref<AttendanceStatus | null>(options.initial().status);
    const visibility = ref<AttendanceVisibility>(
        options.initial().visibility ?? 'everyone',
    );
    const saving = ref(false);

    // The server's answer wins once a refresh brings it.
    watch(options.initial, (next) => {
        if (!saving.value) {
            status.value = next.status;
            visibility.value = next.visibility ?? 'everyone';
        }
    });

    function confirmation(next: AttendanceStatus | null): {
        title: string;
        description: string;
    } {
        const title =
            next === 'attending'
                ? t('events.rsvp.savedGoing')
                : next === 'interested'
                  ? t('events.rsvp.savedMaybe')
                  : next === 'not_attending'
                    ? t('events.rsvp.savedCantGo')
                    : t('events.rsvp.savedNoAnswer');
        const audience =
            visibility.value === 'friends'
                ? t('events.rsvp.audienceFriends')
                : visibility.value === 'none'
                  ? t('events.rsvp.audienceNone', { host: options.host() })
                  : t('events.rsvp.audienceEveryone', { host: options.host() });

        return { title, description: audience };
    }

    function save(
        next: Partial<{
            status: AttendanceStatus | null;
            visibility: AttendanceVisibility;
        }>,
        { announce = true }: { announce?: boolean } = {},
    ): void {
        const previous = {
            status: status.value,
            visibility: visibility.value,
        };

        if (next.status !== undefined) {
            status.value = next.status;
        }

        if (next.visibility !== undefined) {
            visibility.value = next.visibility;
        }

        saving.value = true;

        router.put(
            updateAttendance(options.eventId()).url,
            {
                status: status.value ?? 'no_response',
                attendance_visibility: visibility.value,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: options.reload,
                onSuccess: () => {
                    if (!announce) {
                        return;
                    }

                    toast({
                        ...confirmation(status.value),
                        icon: CircleCheck,
                        action: {
                            label: t('ui.undo'),
                            onClick: () =>
                                save(
                                    {
                                        status: previous.status,
                                        visibility: previous.visibility,
                                    },
                                    { announce: false },
                                ),
                        },
                    });
                },
                onError: () => {
                    status.value = previous.status;
                    visibility.value = previous.visibility;
                    toastError(t('events.rsvp.failed'));
                },
                onFinish: () => {
                    saving.value = false;
                },
            },
        );
    }

    return { status, visibility, saving, save };
}
