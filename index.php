<?php
session_start();
require_once __DIR__ . '/config.php';

define('STATE_FILE', __DIR__ . '/led_state.txt');

function getLedState(): string
{
    $pidFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/apache/logs/httpd.pid';

    if (
        is_file(STATE_FILE) &&
        is_file($pidFile) &&
        filemtime($pidFile) > filemtime(STATE_FILE)
    ) {
        file_put_contents(STATE_FILE, 'OFF', LOCK_EX);
        return 'OFF';
    }

    return is_file(STATE_FILE) &&
        trim((string) file_get_contents(STATE_FILE)) === 'ON'
        ? 'ON'
        : 'OFF';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newState = getLedState() === 'ON' ? 'OFF' : 'ON';
    $result = sendArduinoCommand($newState === 'ON' ? '1' : '0');

    if ($result['success']) {
        file_put_contents(STATE_FILE, $newState, LOCK_EX);
    }

    $_SESSION['flash'] = [
        'message' => $result['message'],
        'type' => $result['success'] ? 'success' : 'error',
    ];

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$state = getLedState();
$isOn = $state === 'ON';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f7fb">

    <title>Arduino LED Controller</title>

    <style>
        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --text: #142238;
            --muted: #64748b;
            --border: #e6edf5;
            --blue: #2563eb;
            --green: #16a34a;
            --red: #dc2626;
            --radius: 24px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            padding: 32px 18px;
            display: grid;
            place-items: center;
            background:
                radial-gradient(
                    circle at 50% 0%,
                    #e5edff 0,
                    transparent 45%
                ),
                var(--bg);
            color: var(--text);
            font-family:
                Inter, -apple-system, BlinkMacSystemFont,
                "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button {
            font: inherit;
        }

        .dashboard {
            width: 100%;
            max-width: 540px;
            padding: 36px;
            background: var(--surface);
            border: 1px solid rgba(226, 232, 240, .9);
            border-radius: var(--radius);
            box-shadow:
                0 24px 70px rgba(30, 64, 110, .09),
                0 3px 10px rgba(30, 64, 110, .03);
        }

        .header {
            text-align: center;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
            color: var(--blue);
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 18px;
        }

        .brand-icon svg {
            width: 29px;
            height: 29px;
        }

        h1 {
            margin: 0;
            font-size: clamp(25px, 5vw, 32px);
            letter-spacing: -1.1px;
            font-weight: 750;
        }

        .subtitle {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .divider {
            height: 1px;
            margin: 28px 0;
            background: var(--border);
        }

        .port {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 30px;
        }

        .port-badge {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 10px 15px;
            border: 1px solid #dbeafe;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 650;
        }

        .port-badge svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }

        .device {
            padding: 28px 20px 26px;
            text-align: center;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 20px;
        }

        .led-halo {
            width: 112px;
            height: 112px;
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
            border-radius: 50%;
            transition: background .25s ease;
        }

        .led-halo.on {
            background: rgba(34, 197, 94, .09);
        }

        .led-halo.off {
            background: rgba(239, 68, 68, .07);
        }

        .led {
            position: relative;
            width: 48px;
            height: 62px;
            border: 3px solid #b91c1c;
            border-radius: 23px 23px 12px 12px;
            background: linear-gradient(
                145deg, #fca5a5, #ef4444 65%, #dc2626
            );
            box-shadow: 0 0 25px rgba(239, 68, 68, .22);
            transition: all .25s ease;
        }

        .led::before {
            content: "";
            position: absolute;
            top: 9px;
            left: 8px;
            width: 9px;
            height: 20px;
            border-radius: 99px;
            background: rgba(255, 255, 255, .65);
            transform: rotate(28deg);
        }

        .led::after {
            content: "";
            position: absolute;
            left: 9px;
            bottom: -17px;
            width: 24px;
            height: 12px;
            border-left: 4px solid #64748b;
            border-right: 4px solid #64748b;
        }

        .led.on {
            border-color: #15803d;
            background: linear-gradient(
                145deg, #bbf7d0, #4ade80 65%, #16a34a
            );
            box-shadow:
                0 0 20px rgba(34, 197, 94, .45),
                0 0 48px rgba(34, 197, 94, .2);
        }

        .led.on::after {
            border-color: #64748b;
        }

        .status-label {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 0;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -.7px;
        }

        .status-label .state {
            color: var(--red);
        }

        .status-label .state.on {
            color: var(--green);
        }

        .status-description {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .toggle {
            width: 100%;
            min-height: 62px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding: 16px 20px;
            border: 0;
            border-radius: 15px;
            color: #fff;
            font-size: 16px;
            font-weight: 750;
            cursor: pointer;
            box-shadow: 0 6px 15px rgba(15, 23, 42, .10);
            transition:
                transform .15s ease,
                box-shadow .15s ease,
                background .2s ease;
        }

        .toggle.on {
            background: #15803d;
        }

        .toggle.off {
            background: #dc2626;
        }

        .toggle:hover {
            box-shadow: 0 9px 20px rgba(15, 23, 42, .16);
            transform: translateY(-1px);
        }

        .toggle:active {
            transform: scale(.99);
        }

        .toggle:focus-visible {
            outline: 3px solid #93c5fd;
            outline-offset: 4px;
        }

        .toggle:disabled {
            opacity: .7;
            cursor: wait;
            transform: none;
        }

        .toggle svg {
            width: 21px;
            height: 21px;
            flex-shrink: 0;
        }

        .message {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 20px;
            padding: 15px;
            border: 1px solid;
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .message.success {
            color: #166534;
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .message.error {
            color: #991b1b;
            background: #fef2f2;
            border-color: #fecaca;
        }

        .message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .message strong {
            display: block;
            margin-bottom: 2px;
        }

        .footer {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 10px;
            margin-top: 26px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .footer svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        @media (max-width: 480px) {
            body {
                padding: 18px 12px;
            }

            .dashboard {
                padding: 24px 18px;
                border-radius: 20px;
            }

            .divider {
                margin: 22px 0;
            }

            .device {
                padding: 24px 12px;
            }

            .status-label {
                font-size: 23px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>
    <main class="dashboard">
        <header class="header">
            <div class="brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 8V5l3-3 3 3v3"/>
                    <path d="M9 16v3l3 3 3-3v-3"/>
                    <path d="M8 9H5l-3 3 3 3h3"/>
                    <path d="M16 9h3l3 3-3 3h-3"/>
                    <circle cx="12" cy="12" r="4"/>
                </svg>
            </div>

            <h1>Arduino LED Controller</h1>
            <p class="subtitle">Control your hardware, right from your browser.</p>
        </header>

        <div class="divider"></div>

        <div class="port">
            <span class="port-badge">
                <svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M12 22V4"/>
                    <path d="m9 7 3-3 3 3"/>
                    <path d="M12 12H7l-2 2v3"/>
                    <path d="M12 16h5l2-2v-2"/>
                    <circle cx="5" cy="19" r="2"/>
                    <rect x="17" y="7" width="4" height="4" rx="1"/>
                </svg>
                Arduino Port:
                <span><?= htmlspecialchars((string) ARDUINO_PORT, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </div>

        <section class="device" aria-label="LED device status">
            <div class="led-halo <?= $isOn ? 'on' : 'off' ?>">
                <div class="led <?= $isOn ? 'on' : 'off' ?>"
                    role="img"
                    aria-label="<?= $isOn ? 'LED illuminated' : 'LED not illuminated' ?>">
                </div>
            </div>

            <h2 class="status-label">
                LED
                <span class="state <?= $isOn ? 'on' : '' ?>">
                    <?= htmlspecialchars($state, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </h2>

            <p class="status-description">
                <?= $isOn ? 'Your LED is currently on.' : 'Your LED is currently off.' ?>
            </p>
        </section>

        <form method="post" id="toggleForm">
            <button
                class="toggle <?= $isOn ? 'on' : 'off' ?>"
                type="submit"
                id="toggleBtn"
                aria-label="<?= $isOn ? 'Turn LED off' : 'Turn LED on' ?>"
            >
                <svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2.2"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M12 2v10"/>
                    <path d="M5.6 5.6a9 9 0 1 0 12.8 0"/>
                </svg>
                <span><?= $isOn ? 'Turn OFF' : 'Turn ON' ?></span>
            </button>
        </form>

        <?php if ($flash): ?>
            <?php $flashType = $flash['type'] === 'success' ? 'success' : 'error'; ?>

            <div
                class="message <?= $flashType ?>"
                role="<?= $flashType === 'error' ? 'alert' : 'status' ?>"
                aria-live="polite"
            >
                <?php if ($flashType === 'success'): ?>
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="m8 12 3 3 5-6"/>
                    </svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4"/>
                        <path d="M12 16h.01"/>
                    </svg>
                <?php endif; ?>

                <div>
                    <strong>
                        <?= $flashType === 'success'
                            ? 'Command result'
                            : 'Unable to control LED' ?>
                    </strong>
                    <?= htmlspecialchars(
                        (string) ($flash['message'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            </div>
        <?php endif; ?>

        <footer class="footer">
            <svg viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"
                aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 11v5"/>
                <path d="M12 8h.01"/>
            </svg>
            <span>
                Close the Arduino Serial Monitor before using this controller.
            </span>
        </footer>
    </main>

<script>
    document.getElementById('toggleForm').addEventListener('submit', function () {
        const btn = document.getElementById('toggleBtn');

        btn.disabled = true;
        btn.querySelector('span').textContent = 'Sending command...';
    });
</script>
</body>
</html>