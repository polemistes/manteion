package no.manteion.app;

import android.content.Intent;
import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // The app's own plugins, before the bridge starts.
        registerPlugin(ManteionPushPlugin.class);
        registerPlugin(ManteionShellPlugin.class);

        // Started by tapping a notification: load its page straight away
        // instead of the front page and then the page. (While the app runs,
        // the plugin hands the tap to the page instead.)
        String startPath = savedInstanceState == null ? takeNotificationPath(getIntent()) : null;

        super.onCreate(savedInstanceState);

        if (startPath != null) {
            String url = SiteRequests.absolute(this, startPath);

            if (url != null) {
                getBridge().getWebView().loadUrl(url);
            }
        }
    }

    private static String takeNotificationPath(Intent intent) {
        if (intent == null) {
            return null;
        }

        String path = intent.getStringExtra(Notifier.EXTRA_URL);

        if (path == null || !path.startsWith("/") || path.startsWith("//")) {
            return null;
        }

        intent.removeExtra(Notifier.EXTRA_URL);

        return path;
    }
}
