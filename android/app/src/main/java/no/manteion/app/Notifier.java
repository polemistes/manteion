package no.manteion.app;

import android.app.NotificationChannel;
import android.app.NotificationChannelGroup;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.os.Build;
import android.os.Bundle;
import android.service.notification.StatusBarNotification;
import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import androidx.core.app.Person;
import androidx.core.app.RemoteInput;
import androidx.core.content.pm.ShortcutInfoCompat;
import androidx.core.content.pm.ShortcutManagerCompat;
import androidx.core.graphics.drawable.IconCompat;
import java.util.ArrayList;
import java.util.List;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

/**
 * Turns a push from the site into an Android notification, the way a social
 * app does: a conversation with the sender's picture that collects its
 * messages and can be answered or marked read from the notification, an
 * activity notification with the actor's picture, each kind in its own
 * channel (one per notification category in Settings → Notifications), and
 * grouped under the app with a summary.
 *
 * The push is the same JSON the site's service worker gets (public/sw.js);
 * like the service worker, it is not shown while the app shows its page.
 */
final class Notifier {

    /** The site path a tapped notification opens, on MainActivity's intent. */
    static final String EXTRA_URL = "no.manteion.app.URL";

    /** Each notification is posted under its push tag ("conversation-12") with this id. */
    static final int ITEM_ID = 1;

    static final String CONVERSATIONS_GROUP = "no.manteion.app.CONVERSATIONS";
    static final String ACTIVITY_GROUP = "no.manteion.app.ACTIVITY";
    private static final int CONVERSATIONS_SUMMARY_ID = 2;
    private static final int ACTIVITY_SUMMARY_ID = 3;

    /** Terracotta, the site's accent. */
    private static final int ACCENT = 0xFFC23132;

    /** The newest push per conversation, so a reply can rebuild its notification. */
    private static final String LAST_PUSH_PREFIX = "push:";

    /**
     * The channels until the page names them in the user's language
     * (ManteionPush.configure): id, English name, group, high importance.
     */
    private static final String[][] DEFAULT_CHANNELS = {
        { "direct_messages", "Direct messages", "messages", "high" },
        { "group_messages", "Group messages", "messages", "high" },
        { "event_invites", "Event invitations", "events", "default" },
        { "event_comments", "Comments on events", "events", "default" },
        { "followed_events", "Events from people you follow", "events", "default" },
        { "friend_requests", "Friend requests", "people", "default" },
        { "followers", "Followers", "people", "default" },
        { "organizer_invites", "Organizer invitations", "people", "default" },
        { "leaflet_responses", "Replies to leaflets", "leaflets", "default" },
    };

    private static final String[][] DEFAULT_GROUPS = {
        { "messages", "Messages" },
        { "events", "Events" },
        { "people", "People" },
        { "leaflets", "Leaflets" },
    };

    private Notifier() {}

    /**
     * A push arrived (from FCM, or the debug receiver). The open page hears
     * of it; it shows unless the app is on the push's own page. A test push
     * always shows: that is what it tests.
     */
    static void handlePush(Context context, JSONObject payload) {
        JSONObject data = data(payload);
        String url = data.optString("url", "/");
        boolean isTest = "test".equals(data.optString("type"));
        ManteionPushPlugin plugin = AppState.plugin;

        if (AppState.foreground && plugin != null) {
            plugin.emitReceived(data);
        }

        if (!isTest && AppState.foreground && AppState.samePage(url, AppState.currentPath)) {
            return;
        }

        show(context, payload, null);
    }

    /**
     * Shows a push. `ownReply` adds what the user just answered from the
     * notification to its conversation, quietly.
     */
    static void show(Context context, JSONObject payload, @Nullable CharSequence ownReply) {
        if (!NotificationManagerCompat.from(context).areNotificationsEnabled()) {
            return;
        }

        ensureDefaultChannels(context);

        JSONObject data = data(payload);
        String type = data.optString("type");
        String url = data.optString("url", "/");
        String tag = payload.optString("tag", "");

        if (tag.isEmpty()) {
            tag = type + ":" + url;
        }

        String channel = channelFor(context, data);
        NotificationCompat.Builder builder = "message".equals(type)
            ? message(context, payload, data, tag, channel, ownReply)
            : activity(context, payload, data, channel);

        builder
            .setSmallIcon(R.drawable.ic_stat_manteion)
            .setColor(ACCENT)
            .setAutoCancel(true)
            .setShowWhen(true)
            .setContentIntent(openIntent(context, url, tag))
            .setNumber(Math.max(0, data.optInt("badge_count", 0)))
            .setGroupAlertBehavior(NotificationCompat.GROUP_ALERT_CHILDREN)
            .setOnlyAlertOnce(ownReply != null || !payload.optBoolean("renotify", false));

        if ("message".equals(type)) {
            rememberPush(context, tag, payload);
        }

        post(context, tag, builder);
        updateSummary(context, "message".equals(type) ? CONVERSATIONS_GROUP : ACTIVITY_GROUP, channel, null);
    }

    private static NotificationCompat.Builder message(
        Context context,
        JSONObject payload,
        JSONObject data,
        String tag,
        String channel,
        @Nullable CharSequence ownReply
    ) {
        long when = payload.optLong("timestamp", System.currentTimeMillis());
        String title = payload.optString("title", "Manteion");
        String body = payload.optString("body", "");
        String text = data.isNull("text") ? null : data.optString("text", null);
        int conversationId = data.optInt("conversation_id", 0);
        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, channel)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE)
            .setGroup(CONVERSATIONS_GROUP)
            .setWhen(when)
            .setContentTitle(title)
            .setContentText(body);

        // Without previews the push carries no text: only that there is a
        // new message, so no conversation to build.
        if (text != null) {
            boolean isGroup = "group".equals(data.optString("conversation_type"));
            String senderName = data.optString("sender_name", title);
            Bitmap avatar = Avatars.round(context, data.isNull("sender_avatar") ? null : data.optString("sender_avatar", null));
            Person.Builder sender = new Person.Builder().setName(senderName).setKey("sender:" + senderName);

            if (avatar != null) {
                sender.setIcon(IconCompat.createWithBitmap(avatar));
            }

            NotificationCompat.MessagingStyle style = existingConversation(context, tag);

            if (style == null) {
                style = new NotificationCompat.MessagingStyle(new Person.Builder().setName(Strings.get(context, Strings.YOU)).setKey("me").build());
            }

            style.setGroupConversation(isGroup);
            style.setConversationTitle(isGroup ? data.optString("conversation_title", title) : null);

            if (ownReply != null) {
                style.addMessage(ownReply, System.currentTimeMillis(), (Person) null);
            } else {
                style.addMessage(text, when, sender.build());
            }

            builder.setStyle(style);
            builder.setShortcutInfo(conversationShortcut(context, tag, data.optString("url", "/messages"), isGroup ? style.getConversationTitle() : senderName, sender.build(), avatar));

            if (avatar != null && !isGroup && Build.VERSION.SDK_INT < Build.VERSION_CODES.P) {
                builder.setLargeIcon(avatar);
            }
        }

        if (conversationId > 0) {
            RemoteInput input = new RemoteInput.Builder(NotificationActionReceiver.KEY_REPLY).setLabel(Strings.get(context, Strings.REPLY)).build();

            builder.addAction(
                new NotificationCompat.Action.Builder(
                    R.drawable.ic_stat_manteion,
                    Strings.get(context, Strings.REPLY),
                    NotificationActionReceiver.intent(context, NotificationActionReceiver.ACTION_REPLY, conversationId, tag, true)
                )
                    .addRemoteInput(input)
                    .setAllowGeneratedReplies(false)
                    .setSemanticAction(NotificationCompat.Action.SEMANTIC_ACTION_REPLY)
                    .setShowsUserInterface(false)
                    .build()
            );
            builder.addAction(
                new NotificationCompat.Action.Builder(
                    R.drawable.ic_stat_manteion,
                    Strings.get(context, Strings.MARK_READ),
                    NotificationActionReceiver.intent(context, NotificationActionReceiver.ACTION_MARK_READ, conversationId, tag, false)
                )
                    .setSemanticAction(NotificationCompat.Action.SEMANTIC_ACTION_MARK_AS_READ)
                    .setShowsUserInterface(false)
                    .build()
            );
        }

        return builder;
    }

    /**
     * The conversation as a long-lived shortcut, which makes its
     * notification a conversation notification: in Android's Conversations
     * section, with the other person's picture, and one of the app's
     * shortcuts on the launcher.
     */
    private static ShortcutInfoCompat conversationShortcut(
        Context context,
        String tag,
        String url,
        @Nullable CharSequence label,
        Person person,
        @Nullable Bitmap avatar
    ) {
        Intent open = new Intent(context, MainActivity.class).setAction(Intent.ACTION_VIEW).putExtra(EXTRA_URL, url);
        String name = label != null && label.length() > 0 ? label.toString() : "Manteion";
        ShortcutInfoCompat shortcut = new ShortcutInfoCompat.Builder(context, tag)
            .setShortLabel(name)
            .setLongLived(true)
            .setPerson(person)
            .setIcon(avatar != null ? IconCompat.createWithBitmap(avatar) : IconCompat.createWithResource(context, R.mipmap.ic_launcher_round))
            .setIntent(open)
            .build();

        ShortcutManagerCompat.pushDynamicShortcut(context, shortcut);

        return shortcut;
    }

    private static NotificationCompat.Builder activity(Context context, JSONObject payload, JSONObject data, String channel) {
        String title = payload.optString("title", "Manteion");
        String body = payload.optString("body", "");
        NotificationCompat.Builder builder = new NotificationCompat.Builder(context, channel)
            .setCategory(NotificationCompat.CATEGORY_SOCIAL)
            .setGroup(ACTIVITY_GROUP)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(new NotificationCompat.BigTextStyle().bigText(body));

        if (payload.has("timestamp")) {
            builder.setWhen(payload.optLong("timestamp"));
        }

        Bitmap avatar = Avatars.round(context, payload.optString("icon", null));

        if (avatar != null) {
            builder.setLargeIcon(avatar);
        }

        return builder;
    }

    /** "Your reply wasn't sent", in place of the conversation's notification. */
    static void showReplyFailed(Context context, String tag) {
        JSONObject payload = lastPush(context, tag);
        String url = payload != null ? data(payload).optString("url", "/messages") : "/messages";
        String channel = payload != null ? channelFor(context, data(payload)) : "direct_messages";

        ensureDefaultChannels(context);
        post(
            context,
            tag,
            new NotificationCompat.Builder(context, channel)
                .setSmallIcon(R.drawable.ic_stat_manteion)
                .setColor(ACCENT)
                .setCategory(NotificationCompat.CATEGORY_ERROR)
                .setGroup(CONVERSATIONS_GROUP)
                .setGroupAlertBehavior(NotificationCompat.GROUP_ALERT_CHILDREN)
                .setContentTitle(Strings.get(context, Strings.REPLY_FAILED))
                .setContentText(Strings.get(context, Strings.REPLY_FAILED_BODY))
                .setAutoCancel(true)
                .setContentIntent(openIntent(context, url, tag))
        );
    }

    /** Puts what the user answered into the conversation's notification. */
    static void showOwnReply(Context context, String tag, CharSequence reply) {
        JSONObject payload = lastPush(context, tag);

        if (payload == null) {
            cancel(context, tag);

            return;
        }

        show(context, payload, reply);
    }

    /** Closes the notification with this tag, or all of the app's without one. */
    static void cancel(Context context, @Nullable String tag) {
        NotificationManager manager = context.getSystemService(NotificationManager.class);

        if (tag == null || tag.isEmpty()) {
            manager.cancelAll();
            forgetPushes(context, null);
            // Signed out: the conversations go from the launcher and from
            // Android's conversation list too.
            List<String> shortcuts = new ArrayList<>();

            for (ShortcutInfoCompat shortcut : ShortcutManagerCompat.getDynamicShortcuts(context)) {
                shortcuts.add(shortcut.getId());
            }

            ShortcutManagerCompat.removeLongLivedShortcuts(context, shortcuts);
            ShortcutManagerCompat.removeAllDynamicShortcuts(context);

            return;
        }

        manager.cancel(tag, ITEM_ID);
        Strings.preferences(context).edit().remove(LAST_PUSH_PREFIX + tag).apply();
        updateSummary(context, CONVERSATIONS_GROUP, null, tag);
        updateSummary(context, ACTIVITY_GROUP, null, tag);
    }

    /**
     * One summary per group while it holds two or more notifications, as
     * Android wants for bundles; gone when fewer remain.
     */
    private static void updateSummary(Context context, String group, @Nullable String channel, @Nullable String leavingTag) {
        NotificationManager manager = context.getSystemService(NotificationManager.class);
        int summaryId = CONVERSATIONS_GROUP.equals(group) ? CONVERSATIONS_SUMMARY_ID : ACTIVITY_SUMMARY_ID;
        List<StatusBarNotification> children = new ArrayList<>();

        for (StatusBarNotification active : manager.getActiveNotifications()) {
            boolean isChild = active.getId() == ITEM_ID && group.equals(active.getNotification().getGroup());

            if (isChild && (leavingTag == null || !leavingTag.equals(active.getTag()))) {
                children.add(active);
            }
        }

        if (children.size() < 2) {
            manager.cancel(summaryId);

            return;
        }

        String summaryChannel = channel != null ? channel : children.get(0).getNotification().getChannelId();
        NotificationCompat.InboxStyle inbox = new NotificationCompat.InboxStyle();

        for (int i = 0; i < Math.min(children.size(), 6); i++) {
            Bundle extras = children.get(i).getNotification().extras;
            CharSequence title = extras.getCharSequence(NotificationCompat.EXTRA_TITLE, "");
            CharSequence text = extras.getCharSequence(NotificationCompat.EXTRA_TEXT, "");

            inbox.addLine(title + ": " + text);
        }

        String title = Strings.count(context, CONVERSATIONS_GROUP.equals(group) ? Strings.NEW_MESSAGES : Strings.NEW_ACTIVITY, children.size());

        post(
            context,
            null,
            summaryId,
            new NotificationCompat.Builder(context, summaryChannel)
                .setSmallIcon(R.drawable.ic_stat_manteion)
                .setColor(ACCENT)
                .setContentTitle(title)
                .setStyle(inbox.setBigContentTitle(title))
                .setGroup(group)
                .setGroupSummary(true)
                .setGroupAlertBehavior(NotificationCompat.GROUP_ALERT_CHILDREN)
                .setOnlyAlertOnce(true)
                .setAutoCancel(true)
                .setContentIntent(openIntent(context, CONVERSATIONS_GROUP.equals(group) ? "/messages" : "/notifications", "summary:" + group))
        );
    }

    /**
     * Creates or renames the channel groups and channels. `groups` is
     * [{id, name}], `channels` [{id, name, description, group, importance:
     * "high"|"default"}]. Android keeps whatever the user changed about a
     * channel; only names and descriptions follow.
     */
    static void createChannels(Context context, @Nullable JSONArray groups, @Nullable JSONArray channels) throws JSONException {
        NotificationManager manager = context.getSystemService(NotificationManager.class);

        if (groups != null) {
            for (int i = 0; i < groups.length(); i++) {
                JSONObject group = groups.getJSONObject(i);

                manager.createNotificationChannelGroup(new NotificationChannelGroup(group.getString("id"), group.getString("name")));
            }
        }

        if (channels != null) {
            for (int i = 0; i < channels.length(); i++) {
                JSONObject spec = channels.getJSONObject(i);

                manager.createNotificationChannel(
                    channel(spec.getString("id"), spec.getString("name"), spec.optString("group", null), "high".equals(spec.optString("importance")), spec.optString("description", null))
                );
            }
        }
    }

    private static void ensureDefaultChannels(Context context) {
        NotificationManager manager = context.getSystemService(NotificationManager.class);

        if (manager.getNotificationChannel(DEFAULT_CHANNELS[0][0]) != null) {
            return;
        }

        for (String[] group : DEFAULT_GROUPS) {
            manager.createNotificationChannelGroup(new NotificationChannelGroup(group[0], group[1]));
        }

        for (String[] spec : DEFAULT_CHANNELS) {
            manager.createNotificationChannel(channel(spec[0], spec[1], spec[2], "high".equals(spec[3]), null));
        }
    }

    private static NotificationChannel channel(String id, String name, @Nullable String group, boolean high, @Nullable String description) {
        NotificationChannel channel = new NotificationChannel(id, name, high ? NotificationManager.IMPORTANCE_HIGH : NotificationManager.IMPORTANCE_DEFAULT);

        if (group != null && !group.isEmpty()) {
            channel.setGroup(group);
        }

        if (description != null && !description.isEmpty()) {
            channel.setDescription(description);
        }

        channel.setShowBadge(true);
        channel.enableLights(true);
        channel.setLightColor(ACCENT);

        return channel;
    }

    /** The push's category is its channel; anything unknown goes with direct messages. */
    private static String channelFor(Context context, JSONObject data) {
        String category = data.optString("category", "");
        NotificationManager manager = context.getSystemService(NotificationManager.class);

        return !category.isEmpty() && manager.getNotificationChannel(category) != null ? category : DEFAULT_CHANNELS[0][0];
    }

    static PendingIntent openIntent(Context context, String url, String tag) {
        Intent intent = new Intent(context, MainActivity.class)
            .setAction(Intent.ACTION_VIEW)
            .putExtra(EXTRA_URL, url)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_SINGLE_TOP);

        return PendingIntent.getActivity(context, ("open:" + tag).hashCode(), intent, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
    }

    @Nullable
    private static NotificationCompat.MessagingStyle existingConversation(Context context, String tag) {
        NotificationManager manager = context.getSystemService(NotificationManager.class);

        for (StatusBarNotification active : manager.getActiveNotifications()) {
            if (active.getId() == ITEM_ID && tag.equals(active.getTag())) {
                return NotificationCompat.MessagingStyle.extractMessagingStyleFromNotification(active.getNotification());
            }
        }

        return null;
    }

    private static void rememberPush(Context context, String tag, JSONObject payload) {
        forgetPushes(context, tag);
        Strings.preferences(context).edit().putString(LAST_PUSH_PREFIX + tag, payload.toString()).apply();
    }

    /**
     * Drops the remembered pushes of conversations no longer on screen
     * (swiped away, opened) except `keep`'s, or all of them when `keep` is
     * null.
     */
    private static void forgetPushes(Context context, @Nullable String keep) {
        NotificationManager manager = context.getSystemService(NotificationManager.class);
        java.util.Set<String> showing = new java.util.HashSet<>();

        if (keep != null) {
            showing.add(keep);

            for (StatusBarNotification active : manager.getActiveNotifications()) {
                if (active.getTag() != null) {
                    showing.add(active.getTag());
                }
            }
        }

        android.content.SharedPreferences.Editor editor = Strings.preferences(context).edit();

        for (String key : Strings.preferences(context).getAll().keySet()) {
            if (key.startsWith(LAST_PUSH_PREFIX) && !showing.contains(key.substring(LAST_PUSH_PREFIX.length()))) {
                editor.remove(key);
            }
        }

        editor.apply();
    }

    @Nullable
    private static JSONObject lastPush(Context context, String tag) {
        String stored = Strings.preferences(context).getString(LAST_PUSH_PREFIX + tag, null);

        try {
            return stored != null ? new JSONObject(stored) : null;
        } catch (JSONException e) {
            return null;
        }
    }

    private static JSONObject data(JSONObject payload) {
        JSONObject data = payload.optJSONObject("data");

        return data != null ? data : new JSONObject();
    }

    private static void post(Context context, String tag, NotificationCompat.Builder builder) {
        post(context, tag, ITEM_ID, builder);
    }

    private static void post(Context context, @Nullable String tag, int id, NotificationCompat.Builder builder) {
        try {
            NotificationManagerCompat.from(context).notify(tag, id, builder.build());
        } catch (SecurityException ignored) {
            // Notifications aren't allowed (any more): nothing to show.
        }
    }
}
