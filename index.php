<?php
require_once __DIR__ . '/config.php';

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $command = $_POST['command'] ?? '';

    if ($command === 'ON' || $command === 'OFF') {
        $result = sendArduinoCommand($command === 'ON' ? '1' : '0');
        $message = $result['message'];
        $type = $result['success'] ? 'success' : 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Arduino LED Controller</title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:Arial,sans-serif}
.card{width:420px;max-width:92%;background:#fff;padding:35px;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.12);text-align:center}
h1{color:#1f2937;margin-bottom:8px}.subtitle{color:#64748b}
.port{display:inline-block;padding:8px 14px;border-radius:20px;background:#eef2ff;color:#3730a3;margin:20px}
.buttons{display:flex;gap:15px;justify-content:center}
button{border:0;border-radius:10px;padding:16px 28px;color:white;font-size:17px;font-weight:bold;cursor:pointer;min-width:140px}
.on{background:#16a34a}.off{background:#dc2626}
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
<form method="post">
<div class="buttons">
<button class="on" type="submit" name="command" value="ON">LED ON</button>
<button class="off" type="submit" name="command" value="OFF">LED OFF</button>
</div>
</form>
<?php if ($message !== ''): ?>
<div class="message <?php echo htmlspecialchars($type); ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<div class="note">Close Arduino Serial Monitor before using this page.</div>
</div>
</body>
</html>
