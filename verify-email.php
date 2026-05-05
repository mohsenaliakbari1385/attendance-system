<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$db = new PDO("sqlite:attendance.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once 'api/auth.php';

$auth = new AuthHandler($db);
$success = null;
$error = null;
$token = $_GET['token'] ?? null;

if ($token) {
    $result = $auth->verifyEmail($token);
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تأیید ایمیل - سیستم حضور و غیاب</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg: #0f1117;
    --surface: #1a1d27;
    --surface2: #22263a;
    --border: rgba(255,255,255,0.07);
    --border-hover: rgba(255,255,255,0.15);
    --accent: #6c63ff;
    --accent2: #a78bfa;
    --accent-glow: rgba(108,99,255,0.25);
    --text: #f0f0f8;
    --text-muted: #8b8fa8;
    --text-dim: #555870;
    --success: #10b981;
    --success-bg: rgba(16,185,129,0.1);
    --danger: #f43f5e;
    --danger-bg: rgba(244,63,94,0.1);
    --warning: #f59e0b;
    --radius: 16px;
    --radius-sm: 10px;
    --shadow: 0 8px 32px rgba(0,0,0,0.4);
}

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Vazirmatn', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: -200px;
    right: -200px;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(108,99,255,0.12) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
}

body::after {
    content: '';
    position: fixed;
    bottom: -200px;
    left: -200px;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(167,139,250,0.08) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
}

.navbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(15,17,23,0.85);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    padding: 0 2rem;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 17px;
    font-weight: 700;
    color: var(--text);
    text-decoration: none;
}

.nav-brand-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.nav-links {
    display: flex;
    gap: 1rem;
}

.nav-links a {
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    padding: 8px 12px;
    transition: color 0.2s;
}

.nav-links a:hover {
    color: var(--text);
}

.verify-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.verify-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 3rem 2.5rem;
    width: 100%;
    max-width: 500px;
    box-shadow: var(--shadow), 0 0 80px rgba(108,99,255,0.08);
    animation: fadeUp 0.5s ease;
    text-align: center;
}

.verify-logo {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    margin: 0 auto 1.5rem;
}

.verify-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 1rem;
}

.verify-subtitle {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}

.alert {
    padding: 14px 20px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    animation: slideDown 0.3s ease;
}

.alert-success { background: var(--success-bg); border: 1px solid rgba(16,185,129,0.25); color: var(--success); }
.alert-danger { background: var(--danger-bg); border: 1px solid rgba(244,63,94,0.25); color: var(--danger); }

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 22px;
    border-radius: var(--radius-sm);
    font-family: 'Vazirmatn', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent), #8b5cf6);
    color: #fff;
    box-shadow: 0 4px 16px rgba(108,99,255,0.35);
    display: inline-flex;
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(108,99,255,0.45); }

.hint-box {
    background: rgba(108,99,255,0.08);
    border: 1px solid rgba(108,99,255,0.2);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-top: 1.5rem;
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.8;
    text-align: right;
}

.hint-box strong { color: var(--accent2); }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 600px) {
    .navbar { padding: 0 1rem; }
    .verify-card { padding: 2rem 1.5rem; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="nav-brand">
        <div class="nav-brand-icon">📋</div>
        سیستم حضور و غیاب
    </a>
    <div class="nav-links">
        <a href="index.php">ورود</a>
        <a href="register.php">ثبت‌نام</a>
    </div>
</nav>

<!-- VERIFY EMAIL -->
<div class="verify-page">
    <div class="verify-card">
        <?php if ($success): ?>
            <div class="verify-logo">✅</div>
            <h1 class="verify-title">تأیید موفق!</h1>
            <p class="verify-subtitle">ایمیل شما با موفقیت تأیید شد</p>
            <div class="alert alert-success">
                <span>✨</span>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
            <a href="index.php" class="btn btn-primary">ورود به سیستم</a>
        <?php else: ?>
            <div class="verify-logo">⚠️</div>
            <h1 class="verify-title">خطا در تأیید</h1>
            <p class="verify-subtitle">متأسفانه تأیید ایمیل انجام نشد</p>
            <div class="alert alert-danger">
                <span>❌</span>
                <div><?= $error ?: 'توکن تأیید نامعتبر است' ?></div>
            </div>
            <a href="register.php" class="btn btn-primary">بازگشت به ثبت‌نام</a>
        <?php endif; ?>

        <div class="hint-box">
            <strong>نکته:</strong><br>
            اگر لینک تأیید منقضی شده است، می‌توانید دوباره <a href="register.php" style="color: var(--accent);">ثبت‌نام</a> کنید.
        </div>
    </div>
</div>

</body>
</html>
