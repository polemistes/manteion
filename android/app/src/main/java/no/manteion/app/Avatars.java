package no.manteion.app;

import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.BitmapShader;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.Shader;
import androidx.annotation.Nullable;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;

/**
 * Profile pictures for notifications: fetched from the site (and only from
 * the site), cut into a circle and kept in the app's cache, so the second
 * message from someone shows at once. A picture that can't be had within a
 * few seconds is left out; the notification goes up without it.
 */
final class Avatars {

    private static final int SIZE = 192;

    private Avatars() {}

    /** The round picture at this site path or address. Never on the main thread. */
    @Nullable
    static Bitmap round(Context context, @Nullable String pathOrUrl) {
        String url = SiteRequests.absolute(context, pathOrUrl);

        if (url == null || !url.startsWith(SiteRequests.origin(context) + "/") || url.contains("/icons/")) {
            return null;
        }

        File cached = new File(new File(context.getCacheDir(), "avatars"), hash(url) + ".png");

        if (cached.isFile()) {
            Bitmap bitmap = BitmapFactory.decodeFile(cached.getPath());

            if (bitmap != null) {
                return bitmap;
            }
        }

        Bitmap picture = download(url);

        if (picture == null) {
            return null;
        }

        Bitmap round = circle(picture);

        try {
            File folder = cached.getParentFile();

            if (folder != null && (folder.isDirectory() || folder.mkdirs())) {
                try (FileOutputStream out = new FileOutputStream(cached)) {
                    round.compress(Bitmap.CompressFormat.PNG, 100, out);
                }
            }
        } catch (Exception ignored) {
            // Shown this once without being kept.
        }

        return round;
    }

    @Nullable
    private static Bitmap download(String url) {
        HttpURLConnection connection = null;

        try {
            connection = (HttpURLConnection) new URL(url).openConnection();
            connection.setConnectTimeout(4_000);
            connection.setReadTimeout(4_000);

            if (connection.getResponseCode() != 200) {
                return null;
            }

            try (InputStream in = connection.getInputStream()) {
                return BitmapFactory.decodeStream(in);
            }
        } catch (Exception e) {
            return null;
        } finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    private static Bitmap circle(Bitmap source) {
        int side = Math.min(source.getWidth(), source.getHeight());
        Bitmap square = Bitmap.createBitmap(source, (source.getWidth() - side) / 2, (source.getHeight() - side) / 2, side, side);
        Bitmap scaled = Bitmap.createScaledBitmap(square, SIZE, SIZE, true);
        Bitmap output = Bitmap.createBitmap(SIZE, SIZE, Bitmap.Config.ARGB_8888);
        Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);

        paint.setShader(new BitmapShader(scaled, Shader.TileMode.CLAMP, Shader.TileMode.CLAMP));
        new Canvas(output).drawCircle(SIZE / 2f, SIZE / 2f, SIZE / 2f, paint);

        return output;
    }

    private static String hash(String text) {
        try {
            byte[] digest = MessageDigest.getInstance("SHA-1").digest(text.getBytes("UTF-8"));
            StringBuilder hex = new StringBuilder();

            for (byte b : digest) {
                hex.append(String.format("%02x", b));
            }

            return hex.toString();
        } catch (Exception e) {
            return String.valueOf(text.hashCode());
        }
    }
}
