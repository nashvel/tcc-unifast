# TCC UniFAST Mobile

Android Capacitor WebView wrapper for the deployed TCC UniFAST portal.

This implementation follows the linked
[`weburl-mobile-wrapper`](https://github.com/ckentdev/weburl-mobile-wrapper)
structure while using current Capacitor packages and stricter permissions.

## Requirements

- Node.js 22 or newer
- Android Studio with Android SDK Platform 36, Build Tools 35, and JDK 21

Accept the Android SDK licenses through Android Studio's SDK Manager before the
first native build.

## Setup

```bash
cd mobile
npm install
npm run sync
npm run open:android
```

The default portal is:

```text
https://tcc-unifast.nashvel.online
```

Override it for a build by setting `CAPACITOR_SERVER_URL` before running a
Capacitor command. Production URLs must use HTTPS.

For local device testing only, an HTTP live-reload server can be enabled with:

```bash
CAPACITOR_SERVER_URL=http://192.168.1.10:5173 \
CAPACITOR_ALLOW_CLEARTEXT=true \
npm run sync
```

The development server must be reachable from the device. Never enable
cleartext mode for a production build.

## Commands

```bash
npm run sync
npm run run:android
npm run open:android
npm run build:android
```

The wrapper requests Android camera permission because the identity and School
ID workflows use browser `getUserMedia`. It does not request microphone,
location, Bluetooth, or broad storage permissions.

Capacitor documents `server.url` primarily as a live-reload option and does not
recommend it for a normal production bundle. This project intentionally uses a
remote URL because its existing authentication depends on first-party secure
cookies at the deployed portal origin. Revisit this choice before public app
store submission.

Official references:

- <https://capacitorjs.com/docs/getting-started>
- <https://capacitorjs.com/docs/basics/workflow>
- <https://capacitorjs.com/docs/config>

## iOS

The current wrapper is Android-first, matching the reference project. Add iOS
from macOS with Xcode installed:

```bash
npm install @capacitor/ios@8.5.1
npx cap add ios
npx cap sync ios
```
