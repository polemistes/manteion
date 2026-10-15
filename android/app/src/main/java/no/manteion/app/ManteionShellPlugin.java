package no.manteion.app;

import android.graphics.Color;
import android.net.Uri;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * The window around the page (resources/js/native/shell.ts), and the way
 * back to the site from the app's error page. Where the
 * WebView stays clear of the status and navigation bars (WebView before
 * version 140, see Capacitor's SystemBars), the window shows behind them;
 * it takes the page's canvas colour, so the bars read as part of the page
 * in light and dark mode.
 */
@CapacitorPlugin(name = "ManteionShell")
public class ManteionShellPlugin extends Plugin {

    /** Where the error page's "Try again" goes (resources/native-shell/index.html). */
    static final String RETRY_PATH = "/manteion-retry";

    /**
     * The error page lives at Capacitor's own local address and gets no
     * bridge, so it asks for the site again by going to RETRY_PATH there;
     * the app loads the site instead. Everything else is Capacitor's to
     * decide.
     */
    @Override
    public Boolean shouldOverrideLoad(Uri url) {
        if (!RETRY_PATH.equals(url.getPath()) || url.getHost() == null || !url.getHost().equals(getBridge().getHost())) {
            return null;
        }

        String site = getBridge().getServerUrl();

        getBridge().getWebView().post(() -> getBridge().getWebView().loadUrl(site != null ? site : getBridge().getAppUrl()));

        return true;
    }

    @PluginMethod
    public void setWindowColor(PluginCall call) {
        String color = call.getString("color");

        try {
            int parsed = Color.parseColor(color);

            getActivity().runOnUiThread(() -> {
                getActivity().getWindow().getDecorView().setBackgroundColor(parsed);
                getBridge().getWebView().setBackgroundColor(parsed);
                call.resolve();
            });
        } catch (IllegalArgumentException | NullPointerException e) {
            call.reject("Not a colour: " + color, "INVALID");
        }
    }
}
