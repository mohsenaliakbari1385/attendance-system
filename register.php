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
$formStep = 1; // Step 1: Registration, Step 2: Email verification pending

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_teacher'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Check password confirmation
    if ($password !== $passwordConfirm) {
        $error = "رمز عبور و تأیید آن مطابقت ندارند";
    } else {
        $result = $auth->registerTeacher($fullname, $email, $username, $password);
        
        if ($result['success']) {
            $success = $result['message'];
            $formStep = 2;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت‌نام معلم - سیستم حضور و غیاب</title>
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

.register-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.register-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 3rem 2.5rem;
    width: 100%;
    max-width: 500px;
    box-shadow: var(--shadow), 0 0 80px rgba(108,99,255,0.08);
    animation: fadeUp 0.5s ease;
}

.register-logo {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 1.5rem;
}

.register-title {
    text-align: center;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.register-subtitle {
    text-align: center;
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 2rem;
}

.alert {
    padding: 14px 20px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s ease;
}

.alert-success { background: var(--success-bg); border: 1px solid rgba(16,185,129,0.25); color: var(--success); }
.alert-danger { background: var(--danger-bg); border: 1px solid rgba(244,63,94,0.25); color: var(--danger); }

.form-group { margin-bottom: 1rem; }
.form-label { display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 6px; font-weight: 500; }

.form-control {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-family: 'Vazirmatn', sans-serif;
    font-size: 14px;
    padding: 11px 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}

.form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.form-control::placeholder { color: var(--text-dim); }

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
    width: 100%;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent), #8b5cf6);
    color: #fff;
    box-shadow: 0 4px 16px rgba(108,99,255,0.35);
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(108,99,255,0.45); }
.btn-primary:active { transform: translateY(0); }

.form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
.form-row .form-group { flex: 1; min-width: 150px; }

.hint-box {
    background: rgba(108,99,255,0.08);
    border: 1px solid rgba(108,99,255,0.2);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-top: 1.5rem;
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.8;
}

.hint-box strong { color: var(--accent2); }
.hint-box a { color: var(--accent); text-decoration: none; }
.hint-box a:hover { text-decoration: underline; }

.form-hidden { display: none; }

.step-indicator {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 2rem;
    justify-content: center;
}

.step-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--surface2);
    border: 2px solid var(--border);
    transition: all 0.3s;
}

.step-dot.active {
    background: var(--accent);
    border-color: var(--accent);
}

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
    .register-card { padding: 2rem 1.5rem; }
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

<!-- REGISTER FORM -->
<div class="register-page">
    <div class="register-card">
        <div class="register-logo">📝</div>
        <h1 class="register-title">ثبت‌نام معلم</h1>
        <p class="register-subtitle">حساب جدید معلم بسازید</p>

        <?php if ($formStep === 1): ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="step-indicator">
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
        </div>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">نام کامل</label>
                <input type="text" class="form-control" name="fullname" placeholder="نام و نام خانوادگی" required value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">ایمیل</label>
                <input type="email" class="form-control" name="email" placeholder="your@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <small style="color: var(--text-dim); margin-top: 4px; display: block;">ایمیل شما برای تأیید حساب استفاده می‌شود</small>
            </div>

            <div class="form-group">
                <label class="form-label">نام کاربری</label>
                <input type="text" class="form-control" name="username" placeholder="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" pattern="[a-zA-Z0-9_]{3,20}">
                <small style="color: var(--text-dim); margin-top: 4px; display: block;">3-20 کاراکتر، حروف، اعداد و _ فقط</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">رمز عبور</label>
                    <input type="password" class="form-control" name="password" placeholder="••••••••" required minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">تأیید رمز عبور</label>
                    <input type="password" class="form-control" name="password_confirm" placeholder="••••••••" required minlength="6">
                </div>
            </div>

            <button class="btn btn-primary" name="register_teacher" style="margin-top: 0.5rem;">ثبت‌نام</button>
        </form>

        <div class="hint-box">
            <strong>قبلاً ثبت‌نام کرده‌اید؟</strong><br>
            <a href="index.php">برای ورود کلیک کنید</a>
        </div>

        <?php else: ?>

        <div class="step-indicator">
            <div class="step-dot active"></div>
            <div class="step-dot active"></div>
        </div>

        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>

        <div class="hint-box" style="background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.2); margin-top: 1rem;">
            <strong>مراحل بعدی:</strong><br>
            1️⃣ ایمیل خود را بررسی کنید (ممکن است در پوشه Spam باشد)<br>
            2️⃣ بر روی لینک تأیید در ایمیل کلیک کنید<br>
            3️⃣ پس از تأیید، می‌توانید وارد سیستم شوید<br>
            <br>
            <a href="index.php" style="color: var(--success);">برای ورود کلیک کنید</a>
        </div>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
