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

/**
 * The app's window (ManteionShellPlugin.java): its colour shows behind the
 * status and navigation bars where the WebView doesn't reach under them.
 */
export interface ManteionShellPlugin {
    setWindowColor(options: { color: string }): Promise<void>;
}

export const ManteionShell =
    registerPlugin<ManteionShellPlugin>('ManteionShell');
