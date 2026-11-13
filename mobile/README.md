# Leakwatch mobile (Expo / React Native)

Android + iOS client for the Leakwatch API.

## Run it
```bash
cd mobile
npm install --legacy-peer-deps
npx expo start        # scan the QR with Expo Go (iOS/Android)
```
On the login screen enter your **server URL** (must be reachable from the phone, ideally `https://…`), email and password. For local testing expose Herd with `herd share` and use the https URL it prints.

## Build installable apps (needs your own accounts)
```bash
npm i -g eas-cli && eas login
eas build -p android --profile preview   # APK you can sideload
eas build -p ios --profile preview       # needs a paid Apple Developer account ($99/yr)
```
Google Play needs a one-time $25 developer account. TestFlight/App Store need the Apple account.

The token is kept in the device's secure storage (expo-secure-store).
