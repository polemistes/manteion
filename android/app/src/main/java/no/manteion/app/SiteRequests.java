package no.manteion.app;

import android.content.Context;
import android.net.Uri;
import android.webkit.CookieManager;
import android.webkit.WebSettings;
import androidx.annotation.Nullable;
import com.getcapacitor.CapConfig;
import java.io.IOException;
import java.io.OutputStream;
import java.net.CookieHandler;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.List;
import java.util.Map;
import org.json.JSONObject;

/**
 * Requests to the site from outside the WebView (a reply typed into a
 * notification), signed in with the WebView's own session: its cookies, and
 * the XSRF-TOKEN cookie as the X-XSRF-TOKEN header, as the site's own
 * scripts send it. Cookie reads and writes happen on the main thread (see
 * NotificationActionReceiver); the request itself must not.
 */
final class SiteRequests {

    private SiteRequests() {}

    /** The site the app shows, as "https://www.manteion.no". */
    static String origin(Context context) {
        String serverUrl = CapConfig.loadDefault(context).getServerUrl();
        Uri uri = Uri.parse(serverUrl != null ? serverUrl : "https://www.manteion.no");
        String origin = uri.getScheme() + "://" + uri.getHost();

        return uri.getPort() > 0 ? origin + ":" + uri.getPort() : origin;
    }

    /** The site address of a path, or null for anything not on the site. */
    @Nullable
    static String absolute(Context context, @Nullable String pathOrUrl) {
        if (pathOrUrl == null || pathOrUrl.isEmpty()) {
            return null;
        }

        if (pathOrUrl.startsWith("/") && !pathOrUrl.startsWith("//")) {
            return origin(context) + pathOrUrl;
        }

        return pathOrUrl.startsWith("https://") || pathOrUrl.startsWith("http://") ? pathOrUrl : null;
    }

    /** The WebView's cookies for the site. Main thread only. */
    @Nullable
    static String cookies(Context context) {
        return CookieManager.getInstance().getCookie(origin(context));
    }

    /**
     * Keeps the cookies a response set (the refreshed session), unless
     * Capacitor's cookie handler already did. Main thread only.
     */
    static void storeCookies(Context context, List<String> setCookies) {
        if (CookieHandler.getDefault() != null) {
            return;
        }

        CookieManager manager = CookieManager.getInstance();
        String origin = origin(context);

        for (String cookie : setCookies) {
            manager.setCookie(origin, cookie);
        }

        manager.flush();
    }

    /** What a request answered: its status and the cookies it set. */
    static final class Result {

        final int status;
        final List<String> setCookies;

        Result(int status, List<String> setCookies) {
            this.status = status;
            this.setCookies = setCookies;
        }

        boolean ok() {
            return status >= 200 && status < 300;
        }
    }

    /** POSTs `body` as JSON to a path on the site. Never on the main thread. */
    static Result postJson(Context context, String path, JSONObject body, @Nullable String cookies) throws IOException {
        HttpURLConnection connection = (HttpURLConnection) new URL(origin(context) + path).openConnection();

        try {
            connection.setRequestMethod("POST");
            connection.setConnectTimeout(10_000);
            connection.setReadTimeout(15_000);
            connection.setInstanceFollowRedirects(false);
            connection.setDoOutput(true);
            connection.setRequestProperty("Content-Type", "application/json");
            connection.setRequestProperty("Accept", "application/json");
            connection.setRequestProperty("X-Requested-With", "XMLHttpRequest");
            connection.setRequestProperty("User-Agent", WebSettings.getDefaultUserAgent(context) + " Manteion-Android");

            if (cookies != null) {
                // While the app runs, Capacitor's cookie handler adds the
                // WebView's cookies to every request itself; a second Cookie
                // header would reach the site merged into one unreadable
                // session cookie. Without it (the app was started by the
                // notification's button) they are sent here.
                if (CookieHandler.getDefault() == null) {
                    connection.setRequestProperty("Cookie", cookies);
                }

                String xsrf = cookieValue(cookies, "XSRF-TOKEN");

                if (xsrf != null) {
                    connection.setRequestProperty("X-XSRF-TOKEN", URLDecoder.decode(xsrf, StandardCharsets.UTF_8.name()));
                }
            }

            try (OutputStream out = connection.getOutputStream()) {
                out.write(body.toString().getBytes(StandardCharsets.UTF_8));
            }

            int status = connection.getResponseCode();
            List<String> setCookies = new java.util.ArrayList<>();

            for (Map.Entry<String, List<String>> header : connection.getHeaderFields().entrySet()) {
                if (header.getKey() != null && header.getKey().equalsIgnoreCase("Set-Cookie")) {
                    setCookies.addAll(header.getValue());
                }
            }

            return new Result(status, setCookies);
        } finally {
            connection.disconnect();
        }
    }

    @Nullable
    private static String cookieValue(String cookies, String name) {
        for (String part : cookies.split(";")) {
            String trimmed = part.trim();

            if (trimmed.startsWith(name + "=")) {
                return trimmed.substring(name.length() + 1);
            }
        }

        return null;
    }
}
