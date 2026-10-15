package no.manteion.app;

import androidx.annotation.NonNull;
import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;
import org.json.JSONException;
import org.json.JSONObject;

/**
 * Receives the site's pushes from Firebase Cloud Messaging. They are
 * data-only (App\Notifications\Messages\FcmMessage): `payload` is the web
 * push JSON, which Notifier turns into the notification. A new registration
 * token goes to the running page, which registers it with the site.
 */
public class ManteionMessagingService extends FirebaseMessagingService {

    @Override
    public void onMessageReceived(@NonNull RemoteMessage message) {
        String payload = message.getData().get("payload");

        if (payload == null) {
            return;
        }

        try {
            // On FCM's own background thread, which may fetch the sender's
            // picture before showing it.
            Notifier.handlePush(getApplicationContext(), new JSONObject(payload));
        } catch (JSONException ignored) {
            // Not ours to show.
        } catch (RuntimeException e) {
            // One push that can't be shown must not take the app down.
            android.util.Log.w("ManteionPush", "Push not shown", e);
        }
    }

    @Override
    public void onNewToken(@NonNull String token) {
        ManteionPushPlugin plugin = AppState.plugin;

        if (plugin != null) {
            plugin.emitToken(token);
        }
    }
}
