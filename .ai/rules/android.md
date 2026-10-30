---
paths:
  - 'android/**'
---

# Android

## Building the Android app locally needs Java 21, and native HTTP must not send its own Cookie header
Gradle 8.14 can't run on Android Studio's bundled Java 25, and ~/.gradle/gradle.properties pins org.gradle.java.home to it. Use the Temurin 21 in ~/.jdks: `J=~/.jdks/jdk-21.0.12.1+1; JAVA_HOME=$J ./gradlew -Dorg.gradle.java.home=$J assembleDebug` (CI uses Java 21 too). `MANTEION_APP_URL=http://10.0.2.2:8000 npx cap sync android` points a debug build at `php artisan serve` for the emulator; sync again without it afterwards. Debug builds accept pushes over adb via DebugPushReceiver. In SiteRequests, Capacitor's CookieHandler already adds the WebView's cookies to HttpURLConnection; setting a Cookie header as well sends two, which the server merges into a broken session (419). Only set it when CookieHandler.getDefault() is null (app process started by a notification button).

## Camera capture needs the IMAGE_CAPTURE query, and the page gates on the app version
A page's camera request (an image input with `capture`) reaches the camera app only because AndroidManifest.xml lists android.media.action.IMAGE_CAPTURE under <queries>. Without that, Android 11+ hides the camera app and Capacitor silently falls back to the photo picker (logcat: "Capacitor/FileChooser: Media capture intent could not be launched"). No CAMERA permission is declared, on purpose: declaring it would make Capacitor ask for it first. Apps before 1.2 lack the query, so canTakePhotos() in resources/js/native/shell.ts shows camera buttons only in browsers and Android app 1.2+ (never in the iOS app, which has no NSCameraUsageDescription). Capacitor never deletes its JPEG_*.jpg captures; MainActivity clears ones older than an hour at launch.
