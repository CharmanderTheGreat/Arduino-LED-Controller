<?php
session_start();
require_once __DIR__ . '/config.php';

define('STATE_FILE', __DIR__ . '/led_state.txt');

function getLedState(): string
{
    // If Apache was restarted after the last saved state, treat it as stale -> OFF
    $pidFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/apache/logs/httpd.pid';
    if (is_file(STATE_FILE) && is_file($pidFile) && filemtime($pidFile) > filemtime(STATE_FILE)) {
        file_put_contents(STATE_FILE, 'OFF', LOCK_EX);
        return 'OFF';
    }

    if (is_file(STATE_FILE) && trim((string) file_get_contents(STATE_FILE)) === 'ON') {
        return 'ON';
    }
    return 'OFF'; // default matches the sketch (LED starts LOW)
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newState = getLedState() === 'ON' ? 'OFF' : 'ON';
    $result = sendArduinoCommand($newState === 'ON' ? '1' : '0');

    if ($result['success']) {
        file_put_contents(STATE_FILE, $newState, LOCK_EX);
    }

    $_SESSION['flash'] = [
        'message' => $result['message'],
        'type'    => $result['success'] ? 'success' : 'error',
    ];

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$state = getLedState();
$isOn  = $state === 'ON';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Arduino LED Controller</title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:Arial,sans-serif}
.card{width:420px;max-width:92%;background:#fff;padding:35px;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.12);text-align:center}
h1{color:#1f2937;margin-bottom:8px}.subtitle{color:#64748b}
.port{display:inline-block;padding:8px 14px;border-radius:20px;background:#eef2ff;color:#3730a3;margin:20px}
.toggle{border:0;border-radius:14px;padding:22px 0;width:100%;color:#fff;font-size:20px;font-weight:bold;cursor:pointer;transition:background .2s,transform .1s}
.toggle:active{transform:scale(.98)}
.toggle:disabled{opacity:.7;cursor:wait}
.toggle.on{background:#16a34a}
.toggle.off{background:#dc2626}
.toggle small{display:block;margin-top:6px;font-size:12px;font-weight:normal;opacity:.9}
.message{margin-top:22px;padding:13px;border-radius:9px;font-weight:bold}
.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}
.note{font-size:12px;color:#64748b;margin-top:22px}
</style>
</head>
<body>
<div class="card">
<h1>Arduino LED Controller</h1>
<div class="subtitle">Local PC → USB Serial → Arduino</div>
<div class="port">Arduino Port: <?php echo htmlspecialchars(ARDUINO_PORT); ?></div>

<form method="post" id="toggleForm">
<button class="toggle <?php echo $isOn ? 'on' : 'off'; ?>" type="submit" id="toggleBtn">
LED <?php echo $state; ?>
<small>Click to turn <?php echo $isOn ? 'OFF' : 'ON'; ?></small>
</button>
</form>

<?php if ($flash): ?>
<div class="message <?php echo htmlspecialchars($flash['type']); ?>">
<?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div class="note">Close Arduino Serial Monitor before using this page.</div>
</div>

<script>
document.getElementById('toggleForm').addEventListener('submit', function () {
  var btn = document.getElementById('toggleBtn');
  btn.disabled = true;
  btn.textContent = 'Sending...';
});
</script>
</body>
</html>