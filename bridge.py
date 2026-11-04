"""
Bridge: Firebase Realtime Database -> USB Serial -> Arduino

Listens to /led in the database and forwards it to the Arduino
("1" = LED ON, "0" = LED OFF). The serial port is opened ONCE and kept
open, so there is no Arduino reset (and no 3-second delay) on every click.

Usage:
    pip install -r requirements.txt
    python bridge.py
"""

import time
import threading

import serial
import firebase_admin
from firebase_admin import credentials, db

# ---------- Settings (change these) ----------
SERIAL_PORT = "COM5"          # Check Device Manager -> Ports (COM & LPT)
BAUD_RATE = 9600
SERVICE_ACCOUNT_FILE = "serviceAccountKey.json"
DATABASE_URL = "https://arduino-led-controller-730f6-default-rtdb.asia-southeast1.firebasedatabase.app"
HEARTBEAT_SECONDS = 5
# ---------------------------------------------

arduino = None
serial_lock = threading.Lock()


def open_serial():
    """Open the serial port and wait for the Uno to finish resetting."""
    global arduino
    arduino = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=1)
    time.sleep(2.5)  # Opening the port resets an Uno; wait for the sketch to boot
    print(f"[serial] Connected to {SERIAL_PORT}")


def send_to_arduino(state):
    """Send '1' or '0' to the Arduino. Reconnect once if the port was lost."""
    global arduino
    payload = b"1" if state == "ON" else b"0"

    with serial_lock:
        for attempt in range(2):
            try:
                if arduino is None or not arduino.is_open:
                    open_serial()
                arduino.write(payload)
                arduino.flush()
                print(f"[serial] Sent {payload!r} (LED {state})")
                return
            except (serial.SerialException, OSError) as err:
                print(f"[serial] Error: {err}")
                try:
                    if arduino is not None:
                        arduino.close()
                except Exception:
                    pass
                arduino = None
                time.sleep(1)

    print("[serial] Could not send the command to the Arduino.")


def on_led_change(event):
    """Called once on startup (current value) and then on every change."""
    # event.path == "/" means the whole /led value was set
    if event.path != "/":
        return
    value = event.data
    if value in ("ON", "OFF"):
        print(f"[firebase] /led = {value}")
        send_to_arduino(value)


def heartbeat_loop():
    """Tell the web page that this bridge is alive."""
    ref = db.reference("bridge/heartbeat")
    while True:
        try:
            ref.set(int(time.time() * 1000))
        except Exception as err:
            print(f"[heartbeat] {err}")
        time.sleep(HEARTBEAT_SECONDS)


def main():
    cred = credentials.Certificate(SERVICE_ACCOUNT_FILE)
    firebase_admin.initialize_app(cred, {"databaseURL": DATABASE_URL})

    open_serial()

    threading.Thread(target=heartbeat_loop, daemon=True).start()
    db.reference("led").listen(on_led_change)

    print("[bridge] Running. Press Ctrl+C to stop.")
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("\n[bridge] Stopped.")
        if arduino is not None and arduino.is_open:
            arduino.close()


if __name__ == "__main__":
    main()
