package no.manteion.app;

import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.util.Log;
import androidx.annotation.Nullable;
import androidx.core.app.RemoteInput;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import org.json.JSONObject;

/**
 * The buttons on a conversation's notification: Reply sends what was typed
 * (POST /messages/{id}/send), Mark as read marks the conversation read
 * (POST /messages/{id}/read), both with the WebView's session. A reply that
 * doesn't go through says so in the notification, which then opens the
 * conversation.
 */
public class NotificationActionReceiver extends BroadcastReceiver {

    static final String ACTION_REPLY = "no.manteion.app.REPLY";
    static final String ACTION_MARK_READ = "no.manteion.app.MARK_READ";
    static final String KEY_REPLY = "reply";

    private static final String EXTRA_CONVERSATION = "conversation_id";
    private static final String EXTRA_TAG = "tag";

    private static final String TAG = "ManteionPush";

    private static final ExecutorService NETWORK = Executors.newSingleThreadExecutor();

    static PendingIntent intent(Context context, String action, int conversationId, String tag, boolean takesInput) {
        Intent intent = new Intent(context, NotificationActionReceiver.class)
            .setAction(action)
            .putExtra(EXTRA_CONVERSATION, conversationId)
            .putExtra(EXTRA_TAG, tag);
        // A reply's text is added to the intent by the system, so that one
        // must stay mutable.
        int flags = PendingIntent.FLAG_UPDATE_CURRENT | (takesInput ? PendingIntent.FLAG_MUTABLE : PendingIntent.FLAG_IMMUTABLE);

        return PendingIntent.getBroadcast(context, (action + ":" + tag).hashCode(), intent, flags);
    }

    @Override
    public void onReceive(Context context, Intent intent) {
        String action = intent.getAction();
        int conversationId = intent.getIntExtra(EXTRA_CONVERSATION, 0);
        String tag = intent.getStringExtra(EXTRA_TAG);

        if (conversationId <= 0 || tag == null || action == null) {
            return;
        }

        CharSequence reply = ACTION_REPLY.equals(action) ? replyText(intent) : null;

        if (ACTION_REPLY.equals(action) && (reply == null || reply.toString().trim().isEmpty())) {
            return;
        }

        Context app = context.getApplicationContext();
        // The WebView's cookie store is read here, on the main thread.
        String cookies = SiteRequests.cookies(app);
        PendingResult pending = goAsync();

        NETWORK.execute(() -> {
            try {
                if (ACTION_REPLY.equals(action)) {
                    sendReply(app, conversationId, tag, reply.toString().trim(), cookies);
                } else {
                    markRead(app, conversationId, tag, cookies);
                }
            } finally {
                pending.finish();
            }
        });
    }

    private static void sendReply(Context context, int conversationId, String tag, String text, @Nullable String cookies) {
        boolean sent = false;

        try {
            SiteRequests.Result result = SiteRequests.postJson(context, "/messages/" + conversationId + "/send", new JSONObject().put("body", text), cookies);

            keepCookies(context, result);
            sent = result.ok();

            if (!sent) {
                Log.w(TAG, "Reply refused: HTTP " + result.status);
            }
        } catch (Exception e) {
            Log.w(TAG, "Reply failed", e);
        }

        if (sent) {
            Notifier.showOwnReply(context, tag, text);
        } else {
            Notifier.showReplyFailed(context, tag);
        }
    }

    private static void markRead(Context context, int conversationId, String tag, @Nullable String cookies) {
        try {
            keepCookies(context, SiteRequests.postJson(context, "/messages/" + conversationId + "/read", new JSONObject(), cookies));
        } catch (Exception ignored) {
            // The conversation stays unread on the site; the notification goes anyway.
        }

        Notifier.cancel(context, tag);
    }

    private static void keepCookies(Context context, SiteRequests.Result result) {
        if (!result.setCookies.isEmpty()) {
            AppState.onMain(() -> SiteRequests.storeCookies(context, result.setCookies));
        }
    }

    @Nullable
    private static CharSequence replyText(Intent intent) {
        Bundle input = RemoteInput.getResultsFromIntent(intent);

        return input != null ? input.getCharSequence(KEY_REPLY) : null;
    }
}
