package no.manteion.app;

import android.content.Context;
import android.media.AudioAttributes;
import android.media.AudioManager;
import android.media.Ringtone;
import android.media.RingtoneManager;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.os.VibrationEffect;
import android.os.Vibrator;
import android.os.VibratorManager;
import androidx.annotation.Nullable;

/**
 * Rings like a phone for a call the page is showing (ManteionShell
 * .startRinging): the phone's ringtone, played as a ringtone, so it comes
 * at the ring volume and the phone's silent and vibrate modes are
 * honoured; and the vibration of a call. The page stops it when the ring is
 * answered, refused or given up; failing that it stops by itself once a
 * ring would have run out. Everything runs on the main thread.
 */
final class Ringer {

    /** A ring lasts 45 s (RING_MS in realtime/calls.ts); this one never outlives it by much. */
    private static final long LONGEST_MS = 50_000;

    /** A second on, a second off, like a phone. */
    private static final long[] VIBRATION = { 0, 1000, 1000 };

    private static final Handler MAIN = new Handler(Looper.getMainLooper());

    private static final Runnable STOP = Ringer::end;

    @Nullable
    private static Ringtone ringtone;

    @Nullable
    private static Vibrator vibrator;

    private Ringer() {}

    static void start(Context context) {
        Context app = context.getApplicationContext();

        AppState.onMain(() -> begin(app));
    }

    static void stop() {
        AppState.onMain(Ringer::end);
    }

    private static void begin(Context context) {
        end();

        AudioManager audio = context.getSystemService(AudioManager.class);
        int mode = audio != null ? audio.getRingerMode() : AudioManager.RINGER_MODE_NORMAL;

        if (mode == AudioManager.RINGER_MODE_NORMAL) {
            ringtone = RingtoneManager.getRingtone(context, RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE));

            if (ringtone != null) {
                ringtone.setAudioAttributes(
                    new AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build()
                );

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                    ringtone.setLooping(true);
                }

                ringtone.play();
            }
        }

        if (mode != AudioManager.RINGER_MODE_SILENT) {
            vibrator = vibrator(context);

            if (vibrator != null && vibrator.hasVibrator()) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    vibrator.vibrate(VibrationEffect.createWaveform(VIBRATION, 0));
                } else {
                    vibrator.vibrate(VIBRATION, 0);
                }
            }
        }

        MAIN.postDelayed(STOP, LONGEST_MS);
    }

    private static void end() {
        MAIN.removeCallbacks(STOP);

        if (ringtone != null) {
            ringtone.stop();
            ringtone = null;
        }

        if (vibrator != null) {
            vibrator.cancel();
            vibrator = null;
        }
    }

    @Nullable
    private static Vibrator vibrator(Context context) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            VibratorManager manager = context.getSystemService(VibratorManager.class);

            return manager != null ? manager.getDefaultVibrator() : null;
        }

        return (Vibrator) context.getSystemService(Context.VIBRATOR_SERVICE);
    }
}
