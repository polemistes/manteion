import { registerPlugin } from '@capacitor/core';
import type { PluginListenerHandle } from '@capacitor/core';
import type { PushData } from '@/pwa/serviceWorker';

/** Android's notification permission, in the web's words. */
export type NativePermission = 'granted' | 'denied' | 'prompt';

export type NativeChannel = {
    id: string;
    name: string;
    description?: string;
    group: string;
    importance: 'high' | 'default';
};

/**
 * The Android app's own plugin (android/app/src/main/java/no/manteion/app/
 * ManteionPushPlugin.java): push notifications through Firebase Cloud
 * Messaging, shown by the app itself.
 */
export interface ManteionPushPlugin {
    checkPermissions(): Promise<{ receive: NativePermission }>;
    requestPermissions(): Promise<{ receive: NativePermission }>;
    /** Rejects with code UNCONFIGURED in a build without Firebase. */
    getToken(): Promise<{ token: string }>;
    deleteToken(): Promise<void>;
    /** Channel groups, channels and the notifications' words, in the user's language. */
    configure(options: {
        groups: { id: string; name: string }[];
        channels: NativeChannel[];
        strings: Record<string, string>;
    }): Promise<void>;
    /** Closes the notification with this tag, or all of them. */
    cancel(options: { tag?: string }): Promise<void>;
    /** The page in front, whose own pushes the app doesn't show. */
    setCurrentPath(options: { path: string }): Promise<void>;
    addListener(
        event: 'token',
        listener: (event: { token: string }) => void,
    ): Promise<PluginListenerHandle>;
    addListener(
        event: 'received',
        listener: (data: PushData) => void,
    ): Promise<PluginListenerHandle>;
    addListener(
        event: 'tapped',
        listener: (event: { url: string }) => void,
    ): Promise<PluginListenerHandle>;
}

export const ManteionPush = registerPlugin<ManteionPushPlugin>('ManteionPush');

/** One place a call's sound can come out of, in the Android app. */
export type NativeAudioOutput = {
    /** Android's device id, or the kind itself on Android 11 and older. */
    id: string;
    kind: 'speaker' | 'earpiece' | 'wired' | 'bluetooth' | 'other';
    /** The product's name for a headset; empty for the built-in ones. */
    name: string;
};

/** The outputs there are and the one in use, by id (null when Android can't say). */
export type NativeAudioOutputs = {
    outputs: NativeAudioOutput[];
    current: string | null;
};

/**
 * The app's window (ManteionShellPlugin.java): its colour shows behind the
 * status and navigation bars where the WebView doesn't reach under them.
 * The phone's ring for a call the page shows, and where a call's sound
 * comes out.
 */
export interface ManteionShellPlugin {
    setWindowColor(options: { color: string }): Promise<void>;
    /**
     * Rings like a phone — the ringtone at the ring volume, vibration —
     * until stopRinging(), or for a ring's length at most. App 1.5+; older
     * apps reject the call.
     */
    startRinging(): Promise<void>;
    stopRinging(): Promise<void>;
    /**
     * The outputs a call's sound can come out of and the one in use, and
     * from then on an audioOutputsChanged event for every change (a
     * headset connected, the route moved) until resetAudioOutput(). App
     * 1.6+; older apps reject the call.
     */
    getAudioOutputs(): Promise<NativeAudioOutputs>;
    /**
     * Sends the sound to this output (an id from getAudioOutputs) and
     * answers as it does. With the earpiece in use the screen goes dark
     * near the face.
     */
    setAudioOutput(options: { id: string }): Promise<NativeAudioOutputs>;
    /** After a call: the app's own routing again, the screen stays on, no more events. */
    resetAudioOutput(): Promise<void>;
    addListener(
        event: 'audioOutputsChanged',
        listener: (state: NativeAudioOutputs) => void,
    ): Promise<PluginListenerHandle>;
}

export const ManteionShell =
    registerPlugin<ManteionShellPlugin>('ManteionShell');
