package no.manteion.app;

import android.os.Handler;
import android.os.Looper;
import androidx.annotation.Nullable;

/**
 * What the messaging service needs to know about the running app: whether
 * it is in front, which page it shows, and the plugin that talks to that
 * page. All empty while the app isn't running.
 */
final class AppState {

    /** The app is on screen (between the activity's resume and pause). */
    static volatile boolean foreground = false;

    /** The path of the page the app shows ("/messages/12"), as the page reports it. */
    @Nullable
    static volatile String currentPath = null;

    /** The plugin of the running app, while its bridge exists. */
    @Nullable
    static volatile ManteionPushPlugin plugin = null;

    private static final Handler MAIN = new Handler(Looper.getMainLooper());

    private AppState() {}

    /** Runs on the main thread, where the WebView and its bridge live. */
    static void onMain(Runnable task) {
        if (Looper.myLooper() == Looper.getMainLooper()) {
            task.run();
        } else {
            MAIN.post(task);
        }
    }

    /** Whether two site paths are the same page, ignoring a trailing slash. */
    static boolean samePage(@Nullable String a, @Nullable String b) {
        if (a == null || b == null) {
            return false;
        }

        return trim(a).equals(trim(b));
    }

    private static String trim(String path) {
        int query = path.indexOf('?');
        String bare = query >= 0 ? path.substring(0, query) : path;
        int hash = bare.indexOf('#');
        bare = hash >= 0 ? bare.substring(0, hash) : bare;

        while (bare.length() > 1 && bare.endsWith("/")) {
            bare = bare.substring(0, bare.length() - 1);
        }

        return bare.isEmpty() ? "/" : bare;
    }
}
