package no.manteion.app;

import android.Manifest;
import android.content.Intent;
import android.os.Build;
import androidx.core.app.NotificationManagerCompat;
import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;
import com.google.firebase.FirebaseApp;
import com.google.firebase.messaging.FirebaseMessaging;
import org.json.JSONException;
import org.json.JSONObject;

/**
 * The page's way to the app's push notifications (resources/js/native/):
 * permission, the FCM registration token, the notification channels in the
 * user's language, closing notifications, and the page in front. Tells the
 * page about a new token, a push that arrived while it was open, and a
 * tapped notification (kept until the page listens, for a cold start).
 */
@CapacitorPlugin(name = "ManteionPush", permissions = { @Permission(alias = "notifications", strings = { Manifest.permission.POST_NOTIFICATIONS }) })
public class ManteionPushPlugin extends Plugin {

    @Override
    public void load() {
        AppState.plugin = this;
        handleTap(getActivity().getIntent());
    }

    @Override
    protected void handleOnNewIntent(Intent intent) {
        super.handleOnNewIntent(intent);
        handleTap(intent);
    }

    @Override
    protected void handleOnResume() {
        super.handleOnResume();
        AppState.foreground = true;
    }

    @Override
    protected void handleOnPause() {
        super.handleOnPause();
        AppState.foreground = false;
    }

    @Override
    protected void handleOnDestroy() {
        super.handleOnDestroy();

        if (AppState.plugin == this) {
            AppState.plugin = null;
        }

        AppState.foreground = false;
    }

    /** "granted", "denied" or "prompt", the web's words for it. */
    @PluginMethod
    @Override
    public void checkPermissions(PluginCall call) {
        call.resolve(permissionResult());
    }

    @PluginMethod
    @Override
    public void requestPermissions(PluginCall call) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU || getPermissionState("notifications") == PermissionState.GRANTED) {
            call.resolve(permissionResult());

            return;
        }

        requestPermissionForAlias("notifications", call, "permissionAnswered");
    }

    @PermissionCallback
    private void permissionAnswered(PluginCall call) {
        call.resolve(permissionResult());
    }

    private JSObject permissionResult() {
        String state;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            PermissionState permission = getPermissionState("notifications");

            state = permission == PermissionState.GRANTED
                ? (NotificationManagerCompat.from(getContext()).areNotificationsEnabled() ? "granted" : "denied")
                : permission == PermissionState.DENIED ? "denied" : "prompt";
        } else {
            // Before Android 13 apps may notify unless the user turned them off.
            state = NotificationManagerCompat.from(getContext()).areNotificationsEnabled() ? "granted" : "denied";
        }

        JSObject result = new JSObject();

        result.put("receive", state);

        return result;
    }

    /** The FCM registration token; rejects with code UNCONFIGURED in a build without Firebase. */
    @PluginMethod
    public void getToken(PluginCall call) {
        if (FirebaseApp.getApps(getContext()).isEmpty()) {
            call.reject("This build has no Firebase project.", "UNCONFIGURED");

            return;
        }

        FirebaseMessaging.getInstance()
            .getToken()
            .addOnCompleteListener(task -> {
                if (task.isSuccessful() && task.getResult() != null) {
                    JSObject result = new JSObject();

                    result.put("token", task.getResult());
                    call.resolve(result);
                } else {
                    Exception error = task.getException();

                    call.reject(error != null ? error.getMessage() : "No FCM token.", "FAILED", error);
                }
            });
    }

    /** Invalidates the token, so pushes to it stop (on logout, or the device switched off). */
    @PluginMethod
    public void deleteToken(PluginCall call) {
        if (FirebaseApp.getApps(getContext()).isEmpty()) {
            call.resolve();

            return;
        }

        FirebaseMessaging.getInstance().deleteToken().addOnCompleteListener(task -> call.resolve());
    }

    /**
     * The channel groups, channels and notification words in the user's
     * language: { groups: [{id, name}], channels: [{id, name, description,
     * group, importance}], strings: {...} }.
     */
    @PluginMethod
    public void configure(PluginCall call) {
        try {
            Notifier.createChannels(getContext(), call.getArray("groups"), call.getArray("channels"));
            Strings.save(getContext(), call.getObject("strings"));
            call.resolve();
        } catch (JSONException e) {
            call.reject("Bad channel list.", "INVALID", e);
        }
    }

    /** Closes the notification with `tag`, or all of the app's without one. */
    @PluginMethod
    public void cancel(PluginCall call) {
        Notifier.cancel(getContext(), call.getString("tag"));
        call.resolve();
    }

    /** The page in front, so its own pushes aren't shown on top of it. */
    @PluginMethod
    public void setCurrentPath(PluginCall call) {
        AppState.currentPath = call.getString("path");
        call.resolve();
    }

    void emitToken(String token) {
        JSObject data = new JSObject();

        data.put("token", token);
        AppState.onMain(() -> notifyListeners("token", data));
    }

    void emitReceived(JSONObject pushData) {
        try {
            JSObject data = JSObject.fromJSONObject(pushData);

            AppState.onMain(() -> notifyListeners("received", data));
        } catch (JSONException ignored) {
            // Nothing the page could use.
        }
    }

    private void handleTap(Intent intent) {
        if (intent == null || !intent.hasExtra(Notifier.EXTRA_URL)) {
            return;
        }

        String url = intent.getStringExtra(Notifier.EXTRA_URL);

        // Once: a recreated activity gets the same intent again.
        intent.removeExtra(Notifier.EXTRA_URL);

        if (url == null) {
            return;
        }

        JSObject data = new JSObject();

        data.put("url", url);
        notifyListeners("tapped", data, true);
    }
}
