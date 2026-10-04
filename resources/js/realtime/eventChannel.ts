import { onBeforeUnmount, onMounted, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import type { EventActivity } from '@/types';
import { joinPrivateChannel, onReconnect } from './echo';

/**
 * Calls `onActivity` whenever something changes on the event page — a
 * comment, attendance, an invitation, an edit, deletion (`.event.activity`
 * on `events.{id}`), so the page can reload what changed. Subscribed from
 * mount to unmount; `eventId` may be a ref or getter. Call from a
 * component's setup. After a dropped connection it reports an `updated`,
 * since whatever happened meanwhile was missed.
 */
export function useEventChannel(
    eventId: MaybeRefOrGetter<number>,
    onActivity: (activity: EventActivity) => void,
): void {
    let current: number | null = null;
    let joined: ReturnType<typeof joinPrivateChannel> = null;

    const handleActivity = (activity: EventActivity): void => {
        onActivity(activity);
    };

    function connect(id: number): void {
        current = id;
        joined = joinPrivateChannel(`events.${id}`);
        joined?.channel.listen('.event.activity', handleActivity);
    }

    function disconnect(): void {
        joined?.channel.stopListening('.event.activity', handleActivity);
        joined?.release();
        joined = null;
        current = null;
    }

    onMounted(() => connect(toValue(eventId)));

    // Back from a dropped connection: whatever changed meanwhile was not
    // heard, so treat it as an edit and let the page reload.
    onReconnect(() => {
        if (current !== null) {
            onActivity({ event_id: current, kind: 'updated' });
        }
    });

    watch(
        () => toValue(eventId),
        (id) => {
            if (current !== null && id !== current) {
                disconnect();
                connect(id);
            }
        },
    );

    onBeforeUnmount(disconnect);
}
