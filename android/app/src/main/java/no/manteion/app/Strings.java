package no.manteion.app;

import android.content.Context;
import android.content.SharedPreferences;
import androidx.annotation.Nullable;
import org.json.JSONException;
import org.json.JSONObject;

/**
 * The few words the app's own notifications use ("Reply", "You"), in the
 * language the user chose on the site. The page hands them over at every
 * start (ManteionPush.configure); until it has, English.
 */
final class Strings {

    private static final String PREFERENCES = "manteion.push";
    private static final String KEY = "strings";

    static final String YOU = "you";
    static final String REPLY = "reply";
    static final String MARK_READ = "markRead";
    static final String REPLY_FAILED = "replyFailed";
    static final String REPLY_FAILED_BODY = "replyFailedBody";
    static final String NEW_MESSAGES = "newMessages";
    static final String NEW_ACTIVITY = "newActivity";

    private Strings() {}

    static void save(Context context, @Nullable JSONObject strings) {
        if (strings == null) {
            return;
        }

        preferences(context).edit().putString(KEY, strings.toString()).apply();
    }

    static String get(Context context, String key) {
        try {
            String stored = preferences(context).getString(KEY, null);

            if (stored != null) {
                String value = new JSONObject(stored).optString(key, "");

                if (!value.isEmpty()) {
                    return value;
                }
            }
        } catch (JSONException ignored) {
            // Fall back to English below.
        }

        return english(key);
    }

    /** A string with a count in it, where the page's text has "{n}". */
    static String count(Context context, String key, int count) {
        return get(context, key).replace("{n}", String.valueOf(count));
    }

    private static String english(String key) {
        switch (key) {
            case YOU:
                return "You";
            case REPLY:
                return "Reply";
            case MARK_READ:
                return "Mark as read";
            case REPLY_FAILED:
                return "Your reply wasn't sent";
            case REPLY_FAILED_BODY:
                return "Open the conversation to try again.";
            case NEW_MESSAGES:
                return "{n} conversations";
            case NEW_ACTIVITY:
                return "{n} notifications";
            default:
                return "";
        }
    }

    static SharedPreferences preferences(Context context) {
        return context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE);
    }
}
