---
paths:
  - 'android/**'
---

# Android

## Building the Android app locally needs Java 21, and native HTTP must not send its own Cookie header
Gradle 8.14 can't run on Android Studio's bundled Java 25, and ~/.gradle/gradle.properties pins org.gradle.java.home to it. Use the Temurin 21 in ~/.jdks: `J=~/.jdks/jdk-21.0.12.1+1; JAVA_HOME=$J ./gradlew -Dorg.gradle.java.home=$J assembleDebug` (CI uses Java 21 too). `MANTEION_APP_URL=http://10.0.2.2:8000 npx cap sync android` points a debug build at `php artisan serve` for the emulator; sync again without it afterwards. Debug builds accept pushes over adb via DebugPushReceiver. In SiteRequests, Capacitor's CookieHandler already adds the WebView's cookies to HttpURLConnection; setting a Cookie header as well sends two, which the server merges into a broken session (419). Only set it when CookieHandler.getDefault() is null (app process started by a notification button).
