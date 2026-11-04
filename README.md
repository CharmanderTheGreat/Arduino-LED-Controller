# Arduino LED Controller (Firebase Edition)

A web-based controller that turns an Arduino's LED on or off from **anywhere**. A web page hosted on Firebase sends the command through a cloud database, and a small bridge program on the PC connected to the Arduino forwards it over USB.

> This is the second version of the project. The first version (PHP + XAMPP) could only be used on the local PC. This version uses **Firebase** so the LED can be controlled from any device with an internet connection.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Why Firebase Is Needed](#2-why-firebase-is-needed)
3. [Architecture](#3-architecture)
4. [Tech Stack](#4-tech-stack)
5. [Project Structure](#5-project-structure)
6. [How Each Part Works](#6-how-each-part-works)
7. [Database Structure](#7-database-structure)
8. [Timeline of Events](#8-timeline-of-events)
9. [Errors Encountered and Solutions](#9-errors-encountered-and-solutions)
10. [Security](#10-security)
11. [Limitations](#11-limitations)
12. [Future Improvements](#12-future-improvements)

For step-by-step installation instructions, see **[SETUP.md](SETUP.md)**.

---

## 1. Overview

This is an IoT (Internet of Things) project made of three parts:

- A **web page** with a single toggle button (green = ON, red = OFF).
- **Firebase**, which carries the command between the web page and the PC.
- A **bridge script** on the PC, which receives the command from Firebase and passes it to the Arduino over USB.

Updates are real-time. When one device presses the button, the button color and the status text change **at the same moment** on every other device that has the page open.

---

## 2. Why Firebase Is Needed

The original version worked like this:

```
Browser → Apache/PHP (XAMPP) → COM port → USB → Arduino
```

It worked, but it had two limitations when the system had to be hosted online:

1. **Firebase Hosting is static only.** It accepts HTML, CSS, and JavaScript files, but it does not run PHP.
2. **A cloud server cannot reach the USB port of a personal PC.** Even with an online PHP server, `COM4` exists only on the PC that the Arduino is plugged into.

The solution is to place a **middleman in the cloud** (Firebase Realtime Database) and run a small **bridge** on the PC that listens to that database.

The bridge **connects outward** to Firebase. It never waits for incoming connections, so **no port forwarding, public IP, or tunnel is required**.

---

## 3. Architecture

```
 Phone / Laptop (anywhere)
        │  1. button is pressed
        ▼
 Firebase Hosting          (index.html + JavaScript)
        │  2. writes "ON" or "OFF"
        ▼
 Firebase Realtime Database    /led = "ON" | "OFF"
        │  3. pushes the change instantly (real-time listener)
        ▼
 bridge.py  (runs on the PC connected to the Arduino)
        │  4. writes "1" or "0" to the serial port
        ▼
 USB cable → Arduino Uno → LED (pin 13)
```

Additional flow for the status badge:

```
 bridge.py ──(every 5 seconds)──▶ /bridge/heartbeat = <timestamp>
 index.html ◀── reads the heartbeat → shows "Bridge: online/offline"
```

---

## 4. Tech Stack

| Layer | Technology | Role |
| --- | --- | --- |
| Frontend | HTML, CSS, vanilla JS (ES modules) | User interface and toggle button |
| Hosting | Firebase Hosting | Serves the static web page on a free `.web.app` URL |
| Database | Firebase Realtime Database | Carries the ON/OFF command in real time |
| Bridge | Python 3, `firebase-admin`, `pyserial` | Listens to the database and writes to the serial port |
| Firmware | Arduino C++ (`.ino`) | Receives `1`/`0` and drives pin 13 |
| Hardware | Arduino Uno, USB cable | Built-in LED on pin 13 (or an external LED) |
| Tooling | Node.js, npm, Firebase CLI | Deployment |

---

## 5. Project Structure

```
arduino-led-controller/
├── public/
│   └── index.html                       ← web page deployed to Firebase Hosting
├── arduino_led_controller/
│   └── arduino_led_controller.ino       ← Arduino sketch
├── bridge.py                            ← Firebase → Serial bridge (runs on the PC)
├── requirements.txt                     ← Python dependencies
├── firebase.json                        ← Firebase Hosting configuration
├── .gitignore                           ← keeps secrets out of Git
├── README.md                            ← this file
├── SETUP.md                             ← step-by-step setup guide
└── legacy-php/                          ← (optional) the original PHP version
    ├── index.php
    ├── config.php
    └── test_com_port.php
```

> `serviceAccountKey.json` is required by `bridge.py` but **must never be committed to GitHub**. It is listed in `.gitignore`.

---

## 6. How Each Part Works

### 6.1 Arduino Sketch (`arduino_led_controller.ino`)

```cpp
/*
  USB Serial -> Arduino LED Controller

  Commands (received over serial, 9600 baud):
    '1' = LED ON
    '0' = LED OFF

  Built-in LED: Arduino Uno pin 13.
*/

const int LED_PIN = 13;

void setup()
{
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);   // LED starts OFF

  Serial.begin(9600);
  delay(500);

  Serial.println("READY");
}

void loop()
{
  if (Serial.available() > 0)
  {
    char command = Serial.read();

    if (command == '1')
    {
      digitalWrite(LED_PIN, HIGH);
      Serial.println("LED ON");
    }
    else if (command == '0')
    {
      digitalWrite(LED_PIN, LOW);
      Serial.println("LED OFF");
    }
  }
}
```

**Explanation:**
- `setup()` configures pin 13 as an output, starts it at LOW (OFF), and opens the serial connection at 9600 baud.
- `loop()` repeatedly checks whether a byte has arrived. A `'1'` turns the LED on and a `'0'` turns it off.
- The Arduino has no knowledge of Firebase or the internet. It only understands serial data, which is why the bridge is needed.

### 6.2 Web Page (`public/index.html`)

A single file containing HTML, CSS, and JavaScript. It loads the Firebase JS SDK from a CDN, so **no `npm install` or build step is required**.

Key parts of the JavaScript:

| Code | Purpose |
| --- | --- |
| `initializeApp(firebaseConfig)` | Connects the page to the Firebase project |
| `onValue(ref(db, "led"), ...)` | **Real-time listener.** Runs every time `/led` changes, which keeps the UI in sync across all devices |
| `set(ledRef, next)` | Writes the new state when the button is pressed |
| `.info/connected` | A special Firebase path that reports whether the browser is connected (drives the "Firebase: connected" badge) |
| `bridge/heartbeat` | Reads the timestamp written by the bridge. If it is older than 15 seconds, the badge shows "Bridge: offline" |

The button stays **disabled until the first value arrives** from the database, which prevents an accidental toggle from an unknown state.

### 6.3 Bridge (`bridge.py`)

A Python script that runs on the PC connected to the Arduino.

| Function | Purpose |
| --- | --- |
| `open_serial()` | Opens the COM port **once** and waits about 2.5 seconds, because an Uno resets whenever the port is opened |
| `on_led_change(event)` | Called by the Firebase listener. `"ON"` writes `b"1"` and `"OFF"` writes `b"0"` |
| `send_to_arduino(state)` | Writes to the serial port. If the connection is lost (for example the USB cable is unplugged), it attempts to reconnect |
| `heartbeat_loop()` | Writes the current time to `/bridge/heartbeat` every 5 seconds |
| `db.reference("led").listen(...)` | Subscribes to `/led`. It fires immediately on startup (to sync the LED with the stored value) and again on every change |

**Why is this faster than the PHP version?**
In the PHP version, every click opened the serial port, which reset the Uno and forced a wait of about 3 seconds. The bridge keeps the port **open permanently**, so the response is nearly instant.

### 6.4 Firebase Configuration (`firebase.json`)

```json
{
  "hosting": {
    "site": "arduino-led-controller-730f6",
    "public": "public",
    "ignore": ["firebase.json", "**/.*", "**/node_modules/**"]
  }
}
```

- `site` selects which Hosting site receives the deployment.
- `public` is the folder that gets deployed (`public/`).

---

## 7. Database Structure

```json
{
  "led": "OFF",
  "bridge": {
    "heartbeat": 1730655000000
  }
}
```

| Path | Value | Written by | Read by |
| --- | --- | --- | --- |
| `/led` | `"ON"` or `"OFF"` | Web page (when the button is pressed) | Web page (for the UI) and bridge (for the Arduino) |
| `/bridge/heartbeat` | Timestamp in milliseconds | Bridge (every 5 seconds) | Web page (for the status badge) |

---

## 8. Timeline of Events

All events took place on **November 4, 2026**.

| Date | Step | Event | Result |
| --- | --- | --- | --- |
| Nov 4, 2026 | 1 | The professor instructed Albert to host the system on Firebase. The existing system was a PHP/XAMPP version that worked only on a local PC. | It was determined that the architecture had to change to **static web page + cloud database + bridge**. |
| Nov 4, 2026 | 2 | The Firebase Hosting product page was reviewed. | Confirmed that Hosting supports **static websites only**, so `index.php` could not be deployed as is. |
| Nov 4, 2026 | 3 | A Firebase project named `arduino-led-controller` was created (Spark plan, free). | Project ID: `arduino-led-controller-730f6`. |
| Nov 4, 2026 | 4 | A **Realtime Database** was created in Singapore (`asia-southeast1`) in Test mode. | The database URL was ready, with the content still `null`. |
| Nov 4, 2026 | 5 | The **Rules** tab was inspected. | Test mode rules allowed open read/write access until **December 4, 2026**. They were left unchanged for testing. |
| Nov 4, 2026 | 6 | A Web app (`</>`) was registered and the `firebaseConfig` was obtained. | The configuration was ready to embed in `index.html`. |
| Nov 4, 2026 | 7 | `public/index.html`, `bridge.py`, `requirements.txt`, `firebase.json`, and `.gitignore` were created. | The code was complete. |
| Nov 4, 2026 | 8 | `npm install -g firebase-tools` was run. | The install succeeded, but the terminal reported `'firebase' is not recognized`. |
| Nov 4, 2026 | 9 | `npx firebase-tools` was used as a workaround. | `login` and `use` worked (already logged in, "Now using project..."). |
| Nov 4, 2026 | 10 | `npx firebase-tools deploy --only hosting` was run. | **Error:** `Assertion failed: resolving hosting target of a site with no site name...` |
| Nov 4, 2026 | 11 | `hosting:sites:list` and `hosting:sites:create` were attempted. | **Error:** failed requests to `firebasehosting.googleapis.com`, which showed that Hosting had not yet been activated on the project. |
| Nov 4, 2026 | 12 | It was concluded that **Get started** on the Hosting page of the Firebase Console had to be completed first, in order to activate Hosting and create the default site. `README.md`, `SETUP.md`, the Arduino sketch, and an updated `firebase.json` (with the `site` field) were written. | Next: activate Hosting, deploy, run the bridge, and test. |

### Remaining Steps (mark with ✅ when done)

- [ ] Activate Hosting in the Firebase Console (**Get started**)
- [ ] `npx firebase-tools hosting:sites:list` shows a site
- [ ] `npx firebase-tools deploy --only hosting` succeeds
- [ ] `https://arduino-led-controller-730f6.web.app` opens correctly
- [ ] `serviceAccountKey.json` downloaded
- [ ] `.ino` sketch uploaded to the Arduino
- [ ] `python bridge.py` runs (shows `[serial] Connected to COM4`)
- [ ] LED turns on and off from the web page
- [ ] Tested on a phone using mobile data (not WiFi)
- [ ] Database Rules changed to authenticated-only (before December 4, 2026)

---

## 9. Errors Encountered and Solutions

| Error | Cause | Solution |
| --- | --- | --- |
| `'firebase' is not recognized as an internal or external command` | The global npm folder is not on the Windows PATH | Use `npx firebase-tools <command>`, or add the output of `npm config get prefix` to PATH |
| `Assertion failed: resolving hosting target of a site with no site name or target name` | `firebase.json` had no `"site"` entry and/or no Hosting site existed in the project | Add `"site": "<project-id>"` to `firebase.json` and activate Hosting in the console |
| `Failed to make request to https://firebasehosting.googleapis.com/...` | Hosting (and its API) had not been activated on the project | Firebase Console → Hosting → **Get started**, or enable the *Firebase Hosting API* in the Google Cloud Console |
| `PHP could not open COM4` (old version) | Another program (such as the Serial Monitor) was holding the port | Close the Arduino Serial Monitor |
| `npm warn deprecated ...` / `allow-scripts` | Warnings from firebase-tools dependencies | No action needed. These are not errors |

---

## 10. Security

**Safe to push to GitHub:**
- The `firebaseConfig` (including `apiKey`) inside `index.html`. This is public information by design. Real protection comes from the **Database Rules**.

**Must NOT be pushed to GitHub:**
- `serviceAccountKey.json`. It grants full admin access to the project and is already listed in `.gitignore`. If it is ever committed by accident, **delete the key immediately** in the Firebase Console (Project settings → Service accounts) and generate a new one.

**About the Database Rules:**
- Currently the project is in test mode. Read and write access is open to **anyone** until **December 4, 2026**, after which the database will block all access.
- Before the final presentation, the rules should be replaced with:

```json
{
  "rules": {
    ".read": "auth != null",
    ".write": "auth != null"
  }
}
```

  This requires Firebase Authentication (a login) on the web page, which is not yet included in this version.

---

## 11. Limitations

- The **PC must be turned on** and `bridge.py` must be running while the system is in use.
- Both the PC and the controlling device need an internet connection.
- With test mode rules, there is **no protection** against other people who know the URL.
- The setup targets Windows (COM port naming), although the bridge also works on Linux and macOS when `SERIAL_PORT` is changed (for example `/dev/ttyUSB0`).

---

## 12. Future Improvements

1. **Firebase Authentication**, so only logged-in users can control the LED.
2. **ESP32 or ESP8266** instead of the Arduino Uno. These boards have built-in WiFi and can talk to Firebase directly, which **removes the need for the PC and the bridge**. This is the truly wireless, nationwide setup.
3. Control of **multiple LEDs or devices** (`/devices/led1`, `/devices/led2`, and so on).
4. An **activity log** recording who toggled the LED and when.
5. **Auto-start** of `bridge.py` when the PC boots (Windows Task Scheduler).

---

## Credits

Created by **Albert Lawrence (CharmanderTheGreat)** as a school project. The first version used PHP and XAMPP, and this version is Firebase-based for online control.
