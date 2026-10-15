package no.manteion.app;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // The app's own plugins, before the bridge starts.
        registerPlugin(ManteionPushPlugin.class);
        registerPlugin(ManteionShellPlugin.class);

        // Started by tapping a notification or a link to the site: load its
        // page straight away instead of the front page and then the page.
        // (While the app runs, the page itself visits it instead.)
        // Not when Android restores the activity, or reopens it from Recents
        // with the intent that once started it: that page was seen already.
        Intent intent = getIntent();
        boolean seenBefore = savedInstanceState != null || (intent != null && (intent.getFlags() & Intent.FLAG_ACTIVITY_LAUNCHED_FROM_HISTORY) != 0);
        String startPath = seenBefore ? null : startPath(intent);

        if (seenBefore && intent != null) {
            intent.removeExtra(Notifier.EXTRA_URL);
        }

        super.onCreate(savedInstanceState);

        if (startPath != null) {
            String url = SiteRequests.absolute(this, startPath);

            if (url != null) {
                getBridge().getWebView().loadUrl(url);
            }
        }
    }

    private static String startPath(Intent intent) {
        if (intent == null) {
            return null;
        }

        // An App Link (https://www.manteion.no/events/12): its page, on
        // whichever server this build shows.
        Uri link = intent.getData();

        if (Intent.ACTION_VIEW.equals(intent.getAction()) && link != null && "www.manteion.no".equals(link.getHost())) {
            String path = link.getEncodedPath() == null || link.getEncodedPath().isEmpty() ? "/" : link.getEncodedPath();

            return link.getEncodedQuery() != null ? path + "?" + link.getEncodedQuery() : path;
        }

        String path = intent.getStringExtra(Notifier.EXTRA_URL);

        if (path == null || !path.startsWith("/") || path.startsWith("//")) {
            return null;
        }

        intent.removeExtra(Notifier.EXTRA_URL);

        return path;
    }
}
