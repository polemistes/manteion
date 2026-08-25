import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { Style, StatusBar } from '@capacitor/status-bar';

/**
 * Wires up native-shell behavior when running inside the Capacitor app.
 * A complete no-op on the web build.
 */
export function setupNativeShell(): void {
    if (!Capacitor.isNativePlatform()) {
        return;
    }

    void StatusBar.setStyle({ style: Style.Dark });
    void StatusBar.setBackgroundColor({ color: '#000000' });

    void App.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack) {
            window.history.back();
        } else {
            void App.exitApp();
        }
    });
}
