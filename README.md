# Arduino LED Controller

Control an Arduino's LED from a web page. A PHP page running on XAMPP sends commands over USB serial to an Arduino Uno. One toggle button turns the LED on or off.

## Tech Stack

| Layer       | Technology                       | Role                                         |
| ----------- | -------------------------------- | -------------------------------------------- |
| Frontend    | HTML, CSS, vanilla JS            | Single toggle button (green = ON, red = OFF) |
| Backend     | PHP 8+ on Apache (XAMPP)         | Handles toggle logic, talks to the COM port  |
| Serial link | Windows `mode` command + `fopen` | Configures and writes to `\\.\COMx`          |
| Firmware    | Arduino C++ (`.ino`)             | Reads `1`/`0` over serial, drives pin 13     |
| Hardware    | Arduino Uno, USB cable           | Built-in LED on pin 13 (or external LED)     |

**How it flows:**

Browser → Apache/PHP → Windows COM port → USB → Arduino → LED

## Project Structure

| File                         | Purpose                                               |
| ---------------------------- | ----------------------------------------------------- |
| `index.php`                  | Web UI and toggle logic                               |
| `config.php`                 | COM port settings and serial helper functions         |
| `test_com_port.php`          | Diagnostic page to check if PHP can open the COM port |
| `arduino_led_controller.ino` | Arduino sketch                                        |
| `led_state.txt`              | Auto-generated; stores the current LED state          |

## Setup

1. Put this folder in `C:\xampp\htdocs\arduino_php_led`.
2. Open `config.php` and set your Arduino's port:

   ```php
   define('ARDUINO_PORT', 'COM5');
   ```

3. Upload `arduino_led_controller.ino` to the Arduino using the Arduino IDE.
4. Close the Arduino Serial Monitor (it locks the port).
5. Start Apache in XAMPP.
6. Open <http://localhost/arduino_php_led/>.

## Usage

- Click the button to toggle the LED.
- **Green** = LED is ON. **Red** = LED is OFF.
- The status message below the button tells you if the command was sent or failed.

## Troubleshooting

Open <http://localhost/arduino_php_led/test_com_port.php>. A good result looks like:

```
RESULT: PHP successfully opened COM5.
The COM port is accessible by PHP.
```

If the port opens but the LED does not respond, check that the sketch is uploaded and the Serial Monitor is closed.

If the button color does not match the actual LED (for example after replugging the Arduino), click the button once to resync, or delete `led_state.txt`.

## Serial Protocol

| Command | Arduino action | Reply     |
| ------- | -------------- | --------- |
| `1`     | LED ON         | `LED ON`  |
| `0`     | LED OFF        | `LED OFF` |

Settings: 9600 baud, 8 data bits, no parity, 1 stop bit.

## Wiring (external LED)

```
D13 -> 220/330 ohm resistor -> LED long leg (+)
GND ------------------------------ LED short leg (-)
```

For the built-in LED, no wiring is needed.

## Notes

- Opening the serial port resets an Arduino Uno, so each click takes about 3 seconds.
- The saved LED state resets to OFF whenever Apache is restarted (assumes the default `C:\xampp` install).
- Windows only (uses the `mode` command).