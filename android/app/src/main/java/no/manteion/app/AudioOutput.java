package no.manteion.app;

import android.content.Context;
import android.media.AudioDeviceCallback;
import android.media.AudioDeviceInfo;
import android.media.AudioManager;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.os.PowerManager;
import androidx.annotation.Nullable;
import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import java.util.ArrayList;
import java.util.List;
import java.util.function.Consumer;
import org.json.JSONObject;

/**
 * Where a call's sound comes out (ManteionShell.getAudioOutputs and
 * setAudioOutput): the loudspeaker, the earpiece, or a wired or Bluetooth
 * headset. The WebView routes a call by itself — to a connected headset,
 * else the loudspeaker — and gives the page no say (Android's browsers
 * have no setSinkId), so the page asks the app which outputs there are and
 * which is in use, and for another one. While the earpiece is in use the
 * screen goes dark near the face (a proximity wake lock), as in a phone
 * call. Everything runs on the main thread.
 */
final class AudioOutput {

    /** The kinds the page knows; it names the built-in ones itself. */
    static final String SPEAKER = "speaker";
    static final String EARPIECE = "earpiece";
    static final String WIRED = "wired";
    static final String BLUETOOTH = "bluetooth";
    static final String OTHER = "other";

    /** A call this long is not expected; the lock goes at its end anyway. */
    private static final long LONGEST_CALL_MS = 4 * 60 * 60 * 1000;

    private static final Handler MAIN = new Handler(Looper.getMainLooper());

    @Nullable
    private static PowerManager.WakeLock nearFace;

    @Nullable
    private static Consumer<JSObject> watcher;

    @Nullable
    private static AudioDeviceCallback devicesCallback;

    @Nullable
    private static AudioManager.OnCommunicationDeviceChangedListener routeListener;

    private AudioOutput() {}

    /**
     * The outputs there are and the one in use, as the page gets them
     * ({ outputs: [{ id, kind, name }], current: id | null }). Keeps the
     * screen's darkness in step with the route while it is at it.
     */
    static JSObject state(Context context) {
        AudioManager audio = audio(context);
        List<Output> outputs = audio != null ? outputs(audio) : new ArrayList<>();
        String current = audio != null ? current(audio, outputs) : null;

        keepScreenDarkAtEar(context, EARPIECE.equals(kindOf(outputs, current)));

        JSArray list = new JSArray();

        for (Output output : outputs) {
            list.put(output.toJson());
        }

        JSObject state = new JSObject();

        state.put("outputs", list);
        state.put("current", current != null ? current : JSONObject.NULL);

        return state;
    }

    /** Sends the sound to the output with this id, and says what stands afterwards. */
    static JSObject select(Context context, String id) {
        AudioManager audio = audio(context);

        if (audio != null) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                AudioDeviceInfo device = communicationDevice(audio, id);

                if (device != null) {
                    audio.setCommunicationDevice(device);
                }
            } else {
                audio.setSpeakerphoneOn(SPEAKER.equals(id));
            }
        }

        return state(context);
    }

    /**
     * Tells `onChange` the new state whenever an output comes or goes or
     * the route changes (the WebView moves it too), until reset().
     */
    static void watch(Context context, Consumer<JSObject> onChange) {
        watcher = onChange;

        AudioManager audio = audio(context);

        if (audio == null || devicesCallback != null) {
            return;
        }

        Context app = context.getApplicationContext();

        devicesCallback = new AudioDeviceCallback() {
            @Override
            public void onAudioDevicesAdded(AudioDeviceInfo[] added) {
                changed(app);
            }

            @Override
            public void onAudioDevicesRemoved(AudioDeviceInfo[] removed) {
                changed(app);
            }
        };
        audio.registerAudioDeviceCallback(devicesCallback, MAIN);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            routeListener = device -> changed(app);
            audio.addOnCommunicationDeviceChangedListener(MAIN::post, routeListener);
        }
    }

    /** After a call: the app's own routing again, the screen stays on, no more news. */
    static void reset(Context context) {
        releaseNearFace();

        AudioManager audio = audio(context);

        if (audio != null) {
            if (devicesCallback != null) {
                audio.unregisterAudioDeviceCallback(devicesCallback);
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                if (routeListener != null) {
                    audio.removeOnCommunicationDeviceChangedListener(routeListener);
                }

                audio.clearCommunicationDevice();
            }
        }

        devicesCallback = null;
        routeListener = null;
        watcher = null;
    }

    private static void changed(Context context) {
        if (watcher != null) {
            watcher.accept(state(context));
        }
    }

    /** The one AudioManager of the app, so callbacks register and unregister on the same. */
    @Nullable
    private static AudioManager audio(Context context) {
        return context.getApplicationContext().getSystemService(AudioManager.class);
    }

    private static final class Output {

        final String id;
        final String kind;
        final String name;

        Output(String id, String kind, String name) {
            this.id = id;
            this.kind = kind;
            this.name = name;
        }

        JSObject toJson() {
            JSObject json = new JSObject();

            json.put("id", id);
            json.put("kind", kind);
            json.put("name", name);

            return json;
        }
    }

    /**
     * Android 12 and up lists the devices a call can use. Before that it is
     * what the speakerphone switch reaches: the loudspeaker, and a wired
     * headset or else the earpiece. Bluetooth is left out there, as it
     * needs the SCO link managed by hand.
     */
    private static List<Output> outputs(AudioManager audio) {
        List<Output> outputs = new ArrayList<>();

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            for (AudioDeviceInfo device : audio.getAvailableCommunicationDevices()) {
                String kind = kindOf(device.getType());
                boolean builtIn = SPEAKER.equals(kind) || EARPIECE.equals(kind);

                outputs.add(new Output(String.valueOf(device.getId()), kind, builtIn ? "" : String.valueOf(device.getProductName())));
            }

            return outputs;
        }

        boolean earpiece = false;
        boolean wired = false;

        for (AudioDeviceInfo device : audio.getDevices(AudioManager.GET_DEVICES_OUTPUTS)) {
            String kind = kindOf(device.getType());

            earpiece |= EARPIECE.equals(kind);
            wired |= WIRED.equals(kind);
        }

        outputs.add(new Output(SPEAKER, SPEAKER, ""));

        if (wired) {
            outputs.add(new Output(WIRED, WIRED, ""));
        } else if (earpiece) {
            outputs.add(new Output(EARPIECE, EARPIECE, ""));
        }

        return outputs;
    }

    @Nullable
    private static String current(AudioManager audio, List<Output> outputs) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            AudioDeviceInfo device = audio.getCommunicationDevice();

            return device != null ? String.valueOf(device.getId()) : null;
        }

        if (audio.isSpeakerphoneOn() || outputs.size() < 2) {
            return SPEAKER;
        }

        return outputs.get(1).id;
    }

    @Nullable
    private static AudioDeviceInfo communicationDevice(AudioManager audio, String id) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
            return null;
        }

        for (AudioDeviceInfo device : audio.getAvailableCommunicationDevices()) {
            if (String.valueOf(device.getId()).equals(id)) {
                return device;
            }
        }

        return null;
    }

    @Nullable
    private static String kindOf(List<Output> outputs, @Nullable String id) {
        for (Output output : outputs) {
            if (output.id.equals(id)) {
                return output.kind;
            }
        }

        return null;
    }

    private static String kindOf(int type) {
        switch (type) {
            case AudioDeviceInfo.TYPE_BUILTIN_SPEAKER:
            case AudioDeviceInfo.TYPE_BUILTIN_SPEAKER_SAFE:
                return SPEAKER;
            case AudioDeviceInfo.TYPE_BUILTIN_EARPIECE:
                return EARPIECE;
            case AudioDeviceInfo.TYPE_WIRED_HEADSET:
            case AudioDeviceInfo.TYPE_WIRED_HEADPHONES:
            case AudioDeviceInfo.TYPE_USB_HEADSET:
            case AudioDeviceInfo.TYPE_USB_DEVICE:
            case AudioDeviceInfo.TYPE_USB_ACCESSORY:
            case AudioDeviceInfo.TYPE_LINE_ANALOG:
            case AudioDeviceInfo.TYPE_LINE_DIGITAL:
                return WIRED;
            case AudioDeviceInfo.TYPE_BLUETOOTH_SCO:
            case AudioDeviceInfo.TYPE_BLUETOOTH_A2DP:
            case AudioDeviceInfo.TYPE_BLE_HEADSET:
            case AudioDeviceInfo.TYPE_BLE_SPEAKER:
            case AudioDeviceInfo.TYPE_BLE_BROADCAST:
            case AudioDeviceInfo.TYPE_HEARING_AID:
                return BLUETOOTH;
            default:
                return OTHER;
        }
    }

    /** The screen off while the phone is at the face, as in a phone call. */
    private static void keepScreenDarkAtEar(Context context, boolean atEar) {
        if (!atEar) {
            releaseNearFace();

            return;
        }

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
