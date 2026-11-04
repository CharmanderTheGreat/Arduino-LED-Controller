# SETUP GUIDE

A step-by-step guide to getting the Arduino LED Controller (Firebase Edition) running from scratch. The steps should be followed in order.

> For an explanation of how everything works, see **[README.md](README.md)**.

---

## Requirements

**Hardware**
- Arduino Uno (or compatible board)
- USB cable (Type-B for the Uno)
- (Optional) 1 LED and a 220/330 ohm resistor for an external LED. The built-in LED on pin 13 also works.

**Software** (on the PC connected to the Arduino)
- [Arduino IDE](https://www.arduino.cc/en/software)
- [Node.js](https://nodejs.org/) (for the Firebase CLI)
- [Python 3.9+](https://www.python.org/downloads/) (**check "Add Python to PATH"** during installation)
- A Google account (for Firebase)

---

## Part 1: Upload the Sketch to the Arduino

1. Connect the Arduino to the PC with the USB cable.
2. Open the **Arduino IDE**.
3. Open `arduino_led_controller/arduino_led_controller.ino`.
   - Important: the `.ino` file must sit in a folder with the **same name** (an Arduino IDE requirement).
4. Go to **Tools → Board → Arduino Uno**.
5. Go to **Tools → Port** and select the Arduino's port (for example `COM4`).
   - **Note this COM number.** It is needed in Part 6.
6. Click **Upload** (→) and wait for "Done uploading".
7. **Close the Serial Monitor** if it is open. The Serial Monitor holds the COM port and blocks the bridge.

**Test (optional):** Open the Serial Monitor (9600 baud), type `1`, and press Enter. The LED should light up and `LED ON` should appear. Type `0` to turn it off. Close the Serial Monitor afterwards.

### Wiring (external LED only)

```
D13 ──► 220/330 ohm resistor ──► LED long leg (+)
GND ───────────────────────────► LED short leg (-)
```

No wiring is needed for the built-in LED.

---

## Part 2: Create the Firebase Project

1. Go to <https://console.firebase.google.com> and sign in.
2. Click **Create a project** (or **Add project**).
3. Name it `arduino-led-controller` and click Continue.
4. Google Analytics can be turned off (it is not needed). Click Create project.
5. Wait for the setup to finish, then click **Continue**.

The project receives a **Project ID** (for example `arduino-led-controller-730f6`). It is visible in the console URL and in Project settings.

---

## Part 3: Set Up the Realtime Database

1. In the left sidebar, open **Databases and storage → Realtime Database** (or search for it under "Search for products").
2. Click **Create Database**.
3. Location: **Singapore (asia-southeast1)**.
4. Security rules: **Start in test mode**, then click **Enable**.
5. Add the first value:
   - Hover the mouse over the database URL line until a **+** appears.
   - Name: `led`, Value: `OFF`, then click **Add**.
   - If no **+** appears, create a file named `led.json` containing `{ "led": "OFF" }`, then use **⋮ → Import JSON**.

### Rules

The **Rules** tab shows the test mode rules:

```json
{
  "rules": {
    ".read": "now < 1796313600000",
    ".write": "now < 1796313600000"
  }
}
```

These are **open until December 4, 2026**. They can stay as they are for testing and must be replaced before they expire (see [Part 9](#part-9-secure-the-database)).

---

## Part 4: Register the Web App and Get the Config

1. On **Project Overview**, click **+ Add app → Web** (`</>`).
2. App nickname: `led-web`.
3. "Also set up Firebase Hosting" does not need to be checked now (it is handled in Part 5).
4. Click **Register app**.
5. The `firebaseConfig` is displayed. The configuration for this project is already included in `public/index.html`. When a different Firebase project is used, replace `firebaseConfig` inside the `<script type="module">` block.

---

## Part 5: Activate Hosting and Deploy

### 5.1 Activate Hosting in the Console (important)

Skipping this step causes the error `Failed to make request to firebasehosting.googleapis.com`.

1. In the Firebase Console, open **Hosting and serverless → Hosting** (or search for "Hosting").
2. Click **Get started**.
3. Click **Next** through the steps until **Continue to the console**. The commands shown there do not need to be followed.
4. The default domain `<project-id>.web.app` should now be visible.

### 5.2 Install the Firebase CLI

```bash
npm install -g firebase-tools
```

If `'firebase' is not recognized` appears afterwards, there are two options:

**Option A (easiest): use `npx`**
Replace `firebase` with `npx firebase-tools` in every command below.

**Option B: fix the PATH**
1. Run `npm config get prefix` and copy the path (usually `C:\Users\<name>\AppData\Roaming\npm`).
2. Open Start → **Edit environment variables for your account** → **Path** → **Edit** → **New** → paste the path → OK.
3. Open a **new** Command Prompt.

### 5.3 Log In and Select the Project

Inside the project folder (where `firebase.json` is located):

```bash
npx firebase-tools login
npx firebase-tools use <project-id>
```

Example: `npx firebase-tools use arduino-led-controller-730f6`

### 5.4 Confirm `firebase.json`

```json
{
  "hosting": {
    "site": "arduino-led-controller-730f6",
    "public": "public",
    "ignore": ["firebase.json", "**/.*", "**/node_modules/**"]
  }
}
```

Replace `site` with the actual site ID. The site ID can be listed with:

```bash
npx firebase-tools hosting:sites:list
```

### 5.5 Deploy

```bash
npx firebase-tools deploy --only hosting
```

On success the output shows:

```
Hosting URL: https://arduino-led-controller-730f6.web.app
```

Open the URL. The UI should appear. The badge reads **"Bridge: offline"** at this point, which is expected because the bridge has not been started yet.

---

## Part 6: Set Up the Bridge on the PC

### 6.1 Get `serviceAccountKey.json`

1. In the Firebase Console, open ⚙️ **Project settings** (next to Project Overview).
2. Open the **Service accounts** tab.
3. Click **Generate new private key**, then **Generate key**.
4. Save the downloaded file as **`serviceAccountKey.json`** in the **same folder as `bridge.py`**.

> ⚠️ **This file is secret.** It must not be shared with anyone or pushed to GitHub. It is already listed in `.gitignore`.

### 6.2 Install the Python Dependencies

```bash
pip install -r requirements.txt
```

If `pip` is not recognized, use `python -m pip install -r requirements.txt`.

### 6.3 Set the COM Port

Open `bridge.py` and edit the line near the top:

```python
SERIAL_PORT = "COM4"   # change to the Arduino's COM port
```

The COM port can be found in **Device Manager → Ports (COM & LPT)** or in the Arduino IDE under Tools → Port.

Also confirm the `DATABASE_URL`:

```python
DATABASE_URL = "https://arduino-led-controller-730f6-default-rtdb.asia-southeast1.firebasedatabase.app"
```

### 6.4 Run

```bash
python bridge.py
```

The expected output is:

```
[serial] Connected to COM4
[bridge] Running. Press Ctrl+C to stop.
[firebase] /led = OFF
[serial] Sent b'0' (LED OFF)
```

**Keep this window open** while the system is in use. Closing it stops the bridge.

---

## Part 7: Test the Full System

1. Open `https://<project-id>.web.app` in a browser.
2. Both badges should be green: **"Firebase: connected"** and **"Bridge: online"**.
3. Click **Turn ON**. The LED on the Arduino should light up within about a second, and the terminal should print `[serial] Sent b'1' (LED ON)`.
4. Click **Turn OFF**. The LED should turn off.

### Nationwide Test

1. On a phone, **turn off WiFi** and use **mobile data**.
2. Open the same `.web.app` URL.
3. Press the button. If the LED responds, the system works outside the home WiFi network.
4. Open the URL on two devices at the same time. Pressing the button on one should update the other **instantly**.

---

## Part 8: Auto-Start the Bridge (Optional)

To avoid starting `bridge.py` manually after every restart:

1. Create `start_bridge.bat` in the project folder:
   ```bat
   @echo off
   cd /d "%~dp0"
   python bridge.py
   pause
   ```
2. Press `Win + R`, type `shell:startup`, and press Enter.
3. Place a **shortcut** to `start_bridge.bat` in the folder that opens.

The bridge then starts every time the Windows user logs in.

---

## Part 9: Secure the Database

**This should be done before December 4, 2026, or before showing the project to the professor.** Test mode is open to everyone.

In **Realtime Database → Rules**, replace the rules with:

```json
{
  "rules": {
    ".read": "auth != null",
    ".write": "auth != null"
  }
}
```

Then click **Publish**.

> **Firebase Authentication** must be added to `index.html` so the web page can still write. `bridge.py` is not affected because the Admin SDK (service account) bypasses the rules. Authentication is not part of the current version and is listed as a future improvement.

---

## Part 10: Push to GitHub

```bash
git init
git add .
git commit -m "Arduino LED Controller (Firebase edition)"
git branch -M main
git remote add origin https://github.com/CharmanderTheGreat/<repo-name>.git
git push -u origin main
```

**Before pushing, confirm that:**
- [ ] `serviceAccountKey.json` does not appear in `git status`.
- [ ] It is listed in `.gitignore`.

To double-check:

```bash
git status
git ls-files | findstr serviceAccount
```

The second command should return **no output**.

---

## Troubleshooting

| Problem | Possible Cause | Solution |
| --- | --- | --- |
| `'firebase' is not recognized` | Not on PATH | Use `npx firebase-tools ...` |
| `Assertion failed: resolving hosting target...` | No `"site"` in `firebase.json`, or Hosting not yet active | Add `site` and complete Part 5.1 |
| `Failed to make request to firebasehosting.googleapis.com` | Hosting API not active | Complete Part 5.1, or enable the *Firebase Hosting API* in the Google Cloud Console |
| `could not open port 'COM4'` / `PermissionError` | Serial Monitor or another program is using the port | Close the Serial Monitor and any other serial program |
| `could not open port 'COM4': FileNotFoundError` | Wrong COM number or the USB cable is unplugged | Check Device Manager and correct `SERIAL_PORT` |
| `FileNotFoundError: serviceAccountKey.json` | The file is not in the folder of `bridge.py` | See Part 6.1 |
| `ModuleNotFoundError: No module named 'serial'` | Dependencies are not installed | `pip install -r requirements.txt` |
| "Bridge: offline" on the web page | `bridge.py` is not running, or the PC has no internet | Start the bridge and check the terminal for errors |
| "Cannot read/write the database: permission denied" | Test mode rules expired (December 4) or the rules are wrong | Check the Rules tab |
| The button works but the LED does not light up | Bridge offline, wrong COM port, or sketch not uploaded | Check the bridge terminal. If `Sent b'1'` appears but the LED stays off, re-upload the sketch |
| The button state and the LED are opposite | The Arduino was reset (USB unplugged and replugged) | Restart `bridge.py` to re-sync |
| The web page does not update after deploying | Browser cache | Press `Ctrl + Shift + R` |

---

## Quick Checklist

- [X] Arduino: `.ino` uploaded, Serial Monitor closed
- [X] Firebase: project created, Realtime Database (`led: "OFF"`), Web app registered
- [X] Hosting: activated (Get started), `"site"` in `firebase.json`, deployed
- [X] Bridge: `serviceAccountKey.json` in place, correct `SERIAL_PORT`, `python bridge.py` running
- [X] Test: works on mobile data and updates two devices at once
- [ ] Security: Rules replaced before December 4
