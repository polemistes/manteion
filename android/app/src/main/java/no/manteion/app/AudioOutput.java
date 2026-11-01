package no.manteion.app;

import android.content.Context;
import android.media.AudioDeviceInfo;
import android.media.AudioManager;
import android.os.Build;
import android.os.PowerManager;
import androidx.annotation.Nullable;
import java.util.List;

/**
 * Where a call's sound comes out (ManteionShell.setAudioOutput): the
 * loudspeaker, or the phone at the ear — a connected headset, else the
 * earpiece. The WebView sends a call to the loudspeaker on its own and
 * gives the page no say (Android's browsers have no setSinkId), so the
 * page asks the app. With the phone at the ear the screen goes dark near
 * the face (a proximity wake lock), so a cheek can't press anything.
 * Everything runs on the main thread.
 */
final class AudioOutput {

    /** What stands after a request: the route, and whether there was a choice. */
    static final class Route {

        final boolean loudspeaker;

        /** False on a device with nothing but a loudspeaker (a tablet). */
        final boolean choice;

        Route(boolean loudspeaker, boolean choice) {
            this.loudspeaker = loudspeaker;
            this.choice = choice;
        }
    }

    /** Something at the ear that is not the earpiece, nearest first. */
    private static final int[] HEADSETS = {
        AudioDeviceInfo.TYPE_WIRED_HEADSET,
        AudioDeviceInfo.TYPE_WIRED_HEADPHONES,
        AudioDeviceInfo.TYPE_USB_HEADSET,
        AudioDeviceInfo.TYPE_BLUETOOTH_SCO,
        AudioDeviceInfo.TYPE_BLE_HEADSET,
    };

    /** A call this long is not expected; the lock goes at its end anyway. */
    private static final long LONGEST_CALL_MS = 4 * 60 * 60 * 1000;

    @Nullable
    private static PowerManager.WakeLock nearFace;

    private AudioOutput() {}

    /**
     * Sends the sound to the loudspeaker, or to the ear (a headset when one
     * is connected, else the earpiece), and says what stands. A device
     * without an earpiece or headset keeps its loudspeaker.
     */
    static Route set(Context context, boolean loudspeaker) {
        AudioManager audio = context.getSystemService(AudioManager.class);

        if (audio == null) {
            return new Route(true, false);
        }

        Route route = Build.VERSION.SDK_INT >= Build.VERSION_CODES.S
            ? setCommunicationDevice(audio, loudspeaker)
            : setSpeakerphone(audio, loudspeaker);

        boolean atEarpiece = route.choice && !route.loudspeaker && !usesHeadset(audio);

        if (atEarpiece) {
            darkenNearFace(context);
        } else {
            releaseNearFace();
        }

        return route;
    }

    /** After a call: the app's own routing again, and the screen stays on. */
    static void reset(Context context) {
        releaseNearFace();

        AudioManager audio = context.getSystemService(AudioManager.class);

        if (audio != null && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            audio.clearCommunicationDevice();
        }
    }

    /** Android 12 and up: the communication device is chosen outright. */
    private static Route setCommunicationDevice(AudioManager audio, boolean loudspeaker) {
        List<AudioDeviceInfo> devices = audio.getAvailableCommunicationDevices();
        AudioDeviceInfo speaker = find(devices, AudioDeviceInfo.TYPE_BUILTIN_SPEAKER);
        AudioDeviceInfo headset = null;

        for (int type : HEADSETS) {
            headset = find(devices, type);

            if (headset != null) {
                break;
            }
        }

        AudioDeviceInfo ear = headset != null ? headset : find(devices, AudioDeviceInfo.TYPE_BUILTIN_EARPIECE);
        AudioDeviceInfo wanted = loudspeaker ? speaker : ear;

        if (wanted != null) {
            audio.setCommunicationDevice(wanted);
        }

        AudioDeviceInfo now = audio.getCommunicationDevice();
        boolean onSpeaker = now == null || now.getType() == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER;

        return new Route(onSpeaker, speaker != null && ear != null);
    }

    /** Before Android 12: the speakerphone switch; off means a headset, else the earpiece. */
    private static Route setSpeakerphone(AudioManager audio, boolean loudspeaker) {
        boolean choice = has(audio, AudioDeviceInfo.TYPE_BUILTIN_EARPIECE) || usesHeadset(audio);

        if (choice) {
            audio.setSpeakerphoneOn(loudspeaker);
        }

        return new Route(!choice || audio.isSpeakerphoneOn(), choice);
    }

    private static boolean usesHeadset(AudioManager audio) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            AudioDeviceInfo now = audio.getCommunicationDevice();

            return now != null && isHeadset(now.getType());
        }

        for (AudioDeviceInfo device : audio.getDevices(AudioManager.GET_DEVICES_OUTPUTS)) {
            if (isHeadset(device.getType())) {
                return true;
            }
        }

        return false;
    }

    private static boolean isHeadset(int type) {
        for (int headset : HEADSETS) {
            if (headset == type) {
                return true;
            }
        }

        return false;
    }

    private static boolean has(AudioManager audio, int type) {
        for (AudioDeviceInfo device : audio.getDevices(AudioManager.GET_DEVICES_OUTPUTS)) {
            if (device.getType() == type) {
                return true;
            }
        }

        return false;
    }

    @Nullable
    private static AudioDeviceInfo find(List<AudioDeviceInfo> devices, int type) {
        for (AudioDeviceInfo device : devices) {
            if (device.getType() == type) {
                return device;
            }
        }

        return null;
    }

    /** The screen off while the phone is at the face, as in a phone call. */
    private static void darkenNearFace(Context context) {
        if (nearFace != null && nearFace.isHeld()) {
            return;
        }

        PowerManager power = context.getSystemService(PowerManager.class);

        if (power == null || !power.isWakeLockLevelSupported(PowerManager.PROXIMITY_SCREEN_OFF_WAKE_LOCK)) {
            return;
        }

        nearFace = power.newWakeLock(PowerManager.PROXIMITY_SCREEN_OFF_WAKE_LOCK, "manteion:call-at-ear");
        nearFace.acquire(LONGEST_CALL_MS);
    }

    /** The screen comes back once the phone leaves the face, not against it. */
    private static void releaseNearFace() {
        if (nearFace != null && nearFace.isHeld()) {
            nearFace.release(PowerManager.RELEASE_FLAG_WAIT_FOR_NO_PROXIMITY);
        }

        nearFace = null;
    }
}
