package no.manteion.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.util.Log;
import org.json.JSONObject;

/**
 * Debug builds only: shows a push handed over by adb exactly as one from
 * FCM would be, to try the notifications without a Firebase project:
 *
 *   adb shell am broadcast -n no.manteion.app/.DebugPushReceiver \
 *       --es payload '{"title":"Kari","body":"Hi","tag":"conversation-1","data":{...}}'
 */
public class DebugPushReceiver extends BroadcastReceiver {

    @Override
    public void onReceive(Context context, Intent intent) {
        String payload = intent.getStringExtra("payload");

        if (payload == null) {
            return;
        }

        PendingResult pending = goAsync();

        new Thread(() -> {
            try {
                Notifier.handlePush(context.getApplicationContext(), new JSONObject(payload));
            } catch (Exception e) {
                Log.w("ManteionPush", "Debug push failed", e);
            } finally {
                pending.finish();
            }
        }).start();
    }
}
