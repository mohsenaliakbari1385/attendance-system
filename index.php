<?php
session_start();

$db = new PDO("sqlite:attendance.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT,
    username TEXT UNIQUE,
    password TEXT,
    role TEXT
);
CREATE TABLE IF NOT EXISTS classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_name TEXT
);
CREATE TABLE IF NOT EXISTS courses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_name TEXT,
    class_id INTEGER
);
CREATE TABLE IF NOT EXISTS attendance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER,
    course_id INTEGER,
    status TEXT,
    attendance_date TEXT
);
");

$checkAdmin = $db->query("SELECT COUNT(*) as total FROM users WHERE username='admin'")->fetch();
if($checkAdmin['total'] == 0){
    $password = md5("admin");
    $stmt = $db->prepare("INSERT INTO users(fullname,username,password,role) VALUES(?,?,?,?)");
    $stmt->execute(["Admin","admin",$password,"teacher"]);
}

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $stmt = $db->prepare("SELECT * FROM users WHERE username=? AND password=?");
    $stmt->execute([$username,$password]);
    $user = $stmt->fetch();
    if($user){ $_SESSION['user'] = $user; header("Location:index.php"); exit; }
    else { $error = "نام کاربری یا رمز عبور اشتباه است"; }
}

if(isset($_GET['logout'])){ session_destroy(); header("Location:index.php"); exit; }

if(isset($_POST['create_student'])){
    $stmt = $db->prepare("INSERT INTO users(fullname,username,password,role) VALUES(?,?,?,?)");
    $stmt->execute([$_POST['fullname'],$_POST['username'],md5($_POST['password']),"student"]);
    $success = "دانش‌آموز با موفقیت ایجاد شد";
}

if(isset($_POST['create_class'])){
    $stmt = $db->prepare("INSERT INTO classes(class_name) VALUES(?)");
    $stmt->execute([$_POST['class_name']]);
    $success = "کلاس با موفقیت ایجاد شد";
}

if(isset($_POST['create_course'])){
    $stmt = $db->prepare("INSERT INTO courses(course_name,class_id) VALUES(?,?)");
    $stmt->execute([$_POST['course_name'],$_POST['class_id']]);
    $success = "درس با موفقیت ایجاد شد";
}

if(isset($_POST['mark_attendance'])){
    $student_id = $_SESSION['user']['id'];
    $course_id = $_POST['course_id'];
    $date = date("Y-m-d");
    $check = $db->prepare("SELECT * FROM attendance WHERE student_id=? AND course_id=? AND attendance_date=?");
    $check->execute([$student_id,$course_id,$date]);
    if(!$check->fetch()){
        $stmt = $db->prepare("INSERT INTO attendance(student_id,course_id,status,attendance_date) VALUES(?,?,?,?)");
        $stmt->execute([$student_id,$course_id,"Present",$date]);
        $success = "حضور شما با موفقیت ثبت شد";
    } else { $error = "حضور شما امروز قبلاً ثبت شده است"; }
}

if(isset($_GET['export'])){
    header('Content-Type:text/csv');
    header('Content-Disposition:attachment; filename=attendance.csv');
    $output = fopen("php://output","w");
    fputcsv($output,['Student','Course','Status','Date']);
    $result = $db->query("SELECT users.fullname,courses.course_name,attendance.status,attendance.attendance_date FROM attendance JOIN users ON attendance.student_id=users.id JOIN courses ON attendance.course_id=courses.id");
    while($row = $result->fetch(PDO::FETCH_ASSOC)){ fputcsv($output,$row); }
    fclose($output); exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>سیستم حضور و غیاب</title>
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

/* NAVBAR */
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

.nav-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nav-user-name {
    font-size: 14px;
    color: var(--text-muted);
}

.nav-user-name strong {
    color: var(--text);
    font-weight: 600;
}

/* WRAPPER */
.wrapper {
    position: relative;
    z-index: 1;
    max-width: 1100px;
    margin: 0 auto;
    padding: 2.5rem 1.5rem;
}

/* ALERTS */
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

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* LOGIN PAGE */
.login-page {
    min-height: calc(100vh - 64px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.login-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 3rem 2.5rem;
    width: 100%;
    max-width: 420px;
    box-shadow: var(--shadow), 0 0 80px rgba(108,99,255,0.08);
    animation: fadeUp 0.5s ease;
}

.login-logo {
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

.login-title {
    text-align: center;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.login-subtitle {
    text-align: center;
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 2rem;
}

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

.hint-box span { color: var(--accent2); font-weight: 600; }

/* DASHBOARD HEADER */
.dash-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.dash-welcome {
    font-size: 26px;
    font-weight: 700;
}

.dash-welcome small {
    display: block;
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 400;
    margin-top: 2px;
}

/* CARDS */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1.75rem;
    transition: border-color 0.2s;
}

.card:hover { border-color: var(--border-hover); }

.card-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 12px;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-title::before {
    content: '';
    width: 3px;
    height: 14px;
    background: var(--accent);
    border-radius: 2px;
    display: inline-block;
}

/* GRID */
.grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }
.grid-1 { display: grid; gap: 1.5rem; margin-bottom: 1.5rem; }

/* FORMS */
.form-group { margin-bottom: 1rem; }
.form-label { display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 6px; font-weight: 500; }

.form-control, .form-select {
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
    -webkit-appearance: none;
}

.form-control:focus, .form-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-glow);
}

.form-control::placeholder { color: var(--text-dim); }

.form-select option { background: var(--surface2); }

/* BUTTONS */
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
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(108,99,255,0.45); }
.btn-primary:active { transform: translateY(0); }

.btn-success {
    background: rgba(16,185,129,0.15);
    color: var(--success);
    border: 1px solid rgba(16,185,129,0.3);
}
.btn-success:hover { background: rgba(16,185,129,0.25); }

.btn-danger {
    background: rgba(244,63,94,0.1);
    color: var(--danger);
    border: 1px solid rgba(244,63,94,0.2);
    padding: 9px 18px;
    font-size: 13px;
}
.btn-danger:hover { background: rgba(244,63,94,0.2); }

.btn-warning {
    background: rgba(245,158,11,0.12);
    color: var(--warning);
    border: 1px solid rgba(245,158,11,0.25);
}
.btn-warning:hover { background: rgba(245,158,11,0.2); }

.btn-full { width: 100%; }

/* INLINE FORM ROW */
.form-row { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
.form-row .form-group { flex: 1; min-width: 150px; }
.form-row .btn { padding: 11px 20px; }

/* TABLE */
.table-wrap {
    overflow-x: auto;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

thead th {
    background: var(--surface2);
    color: var(--text-muted);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 12px 16px;
    text-align: right;
    border-bottom: 1px solid var(--border);
}

tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}

tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.02); }

tbody td {
    padding: 13px 16px;
    color: var(--text);
}

/* BADGE */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-present {
    background: rgba(16,185,129,0.12);
    color: var(--success);
    border: 1px solid rgba(16,185,129,0.2);
}

.badge-present::before {
    content: '';
    width: 6px;
    height: 6px;
    background: var(--success);
    border-radius: 50%;
    display: inline-block;
}

/* TABLE TOP BAR */
.table-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.table-topbar h3 {
    font-size: 16px;
    font-weight: 600;
}

/* STUDENT PANEL */
.attendance-form-card {
    background: linear-gradient(135deg, rgba(108,99,255,0.08), rgba(139,92,246,0.05));
    border: 1px solid rgba(108,99,255,0.2);
}

/* STAT PILLS */
.stat-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.stat-pill {
    flex: 1;
    min-width: 140px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 1rem 1.25rem;
    text-align: center;
}

.stat-pill-value {
    font-size: 28px;
    font-weight: 700;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-pill-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* DIVIDER */
.divider { height: 1px; background: var(--border); margin: 1.5rem 0; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate { animation: fadeUp 0.4s ease both; }
.delay-1 { animation-delay: 0.05s; }
.delay-2 { animation-delay: 0.1s; }
.delay-3 { animation-delay: 0.15s; }
.delay-4 { animation-delay: 0.2s; }

@media (max-width: 600px) {
    .navbar { padding: 0 1rem; }
    .wrapper { padding: 1.5rem 1rem; }
    .login-card { padding: 2rem 1.5rem; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="#" class="nav-brand">
        <div class="nav-brand-icon">📋</div>
        سیستم حضور و غیاب
    </a>
    <?php if(isset($_SESSION['user'])): ?>
    <div class="nav-user">
        <span class="nav-user-name">خوش آمدید، <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>
        <a href="?logout=1" class="btn btn-danger">خروج</a>
    </div>
    <?php endif; ?>
</nav>

<?php if(!isset($_SESSION['user'])): ?>

<!-- LOGIN -->
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">📋</div>
        <h1 class="login-title">ورود به سیستم</h1>
        <p class="login-subtitle">برای ادامه وارد حساب کاربری خود شوید</p>

        <?php if(isset($error)): ?>
        <div class="alert alert-danger">⚠️ <?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">نام کاربری</label>
                <input type="text" class="form-control" name="username" placeholder="نام کاربری را وارد کنید" required>
            </div>
            <div class="form-group">
                <label class="form-label">رمز عبور</label>
                <input type="password" class="form-control" name="password" placeholder="رمز عبور را وارد کنید" required>
            </div>
            <button class="btn btn-primary btn-full" name="login" style="margin-top: 0.5rem;">ورود به سیستم</button>
        </form>

        <div class="hint-box">
            <strong style="color: var(--accent2);">اطلاعات پیش‌فرض ادمین:</strong><br>
            نام کاربری: <span>admin</span> &nbsp;|&nbsp; رمز عبور: <span>admin</span>
        </div>
    </div>
</div>

<?php else: ?>

<div class="wrapper">

    <?php if(isset($success)): ?>
    <div class="alert alert-success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
    <div class="alert alert-danger">⚠️ <?= $error ?></div>
    <?php endif; ?>

    <?php if($_SESSION['user']['role'] == 'teacher'): ?>

    <!-- TEACHER DASHBOARD -->
    <div class="dash-header animate">
        <div class="dash-welcome">
            داشبورد معلم
            <small>مدیریت دانش‌آموزان، کلاس‌ها و گزارش حضور</small>
        </div>
    </div>

    <?php
    $totalStudents = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $totalClasses = $db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $totalAttendance = $db->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
    ?>

    <div class="stat-row">
        <div class="stat-pill animate delay-1">
            <div class="stat-pill-value"><?= $totalStudents ?></div>
            <div class="stat-pill-label">دانش‌آموزان</div>
        </div>
        <div class="stat-pill animate delay-2">
            <div class="stat-pill-value"><?= $totalClasses ?></div>
            <div class="stat-pill-label">کلاس‌ها</div>
        </div>
        <div class="stat-pill animate delay-3">
            <div class="stat-pill-value"><?= $totalAttendance ?></div>
            <div class="stat-pill-label">کل ثبت‌ها</div>
        </div>
    </div>

    <div class="grid-2">

        <!-- CREATE STUDENT -->
        <div class="card animate delay-1">
            <div class="card-title">ایجاد دانش‌آموز</div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">نام کامل</label>
                    <input type="text" class="form-control" name="fullname" placeholder="نام و نام خانوادگی" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نام کاربری</label>
                    <input type="text" class="form-control" name="username" placeholder="username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">رمز عبور</label>
                    <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                </div>
                <button class="btn btn-primary" name="create_student">➕ ایجاد دانش‌آموز</button>
            </form>
        </div>

        <!-- CREATE CLASS -->
        <div class="card animate delay-2">
            <div class="card-title">ایجاد کلاس</div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">نام کلاس</label>
                    <input type="text" class="form-control" name="class_name" placeholder="مثلاً: کلاس دهم الف" required>
                </div>
                <button class="btn btn-warning" name="create_class">🏫 ایجاد کلاس</button>
            </form>

            <?php
            $classes_list = $db->query("SELECT * FROM classes ORDER BY id DESC LIMIT 5");
            $list = $classes_list->fetchAll();
            if(count($list) > 0):
            ?>
            <div class="divider"></div>
            <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">کلاس‌های اخیر</div>
            <?php foreach($list as $cl): ?>
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 14px;">
                <span style="color: var(--accent);">📁</span>
                <?= htmlspecialchars($cl['class_name']) ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- CREATE COURSE -->
    <div class="card animate delay-3" style="margin-bottom: 1.5rem;">
        <div class="card-title">ایجاد درس / دوره</div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">نام درس</label>
                    <input type="text" class="form-control" name="course_name" placeholder="مثلاً: ریاضی ۱" required>
                </div>
                <div class="form-group">
                    <label class="form-label">کلاس</label>
                    <select class="form-select" name="class_id" required>
                        <option value="">انتخاب کلاس...</option>
                        <?php
                        $classes = $db->query("SELECT * FROM classes");
                        while($class = $classes->fetch()):
                        ?>
                        <option value="<?= $class['id'] ?>"><?= htmlspecialchars($class['class_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button class="btn btn-success" name="create_course">✚ ایجاد درس</button>
            </div>
        </form>
    </div>

    <!-- ATTENDANCE REPORT -->
    <div class="card animate delay-4">
        <div class="table-topbar">
            <h3>📊 گزارش حضور و غیاب</h3>
            <a href="?export=1" class="btn btn-success">⬇ دانلود CSV</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>دانش‌آموز</th>
                        <th>درس</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $result = $db->query("
                    SELECT users.fullname, courses.course_name, attendance.status, attendance.attendance_date
                    FROM attendance
                    JOIN users ON attendance.student_id=users.id
                    JOIN courses ON attendance.course_id=courses.id
                    ORDER BY attendance.id DESC
                ");
                $rows = $result->fetchAll();
                if(count($rows) == 0):
                ?>
                <tr><td colspan="4" style="text-align:center; color: var(--text-dim); padding: 2rem;">هنوز هیچ حضوری ثبت نشده است</td></tr>
                <?php else:
                foreach($rows as $row): ?>
                <tr>
                    <td style="font-weight: 500;"><?= htmlspecialchars($row['fullname']) ?></td>
                    <td style="color: var(--text-muted);"><?= htmlspecialchars($row['course_name']) ?></td>
                    <td><span class="badge badge-present"><?= $row['status'] ?></span></td>
                    <td style="color: var(--text-muted); font-size: 13px;"><?= $row['attendance_date'] ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>

    <!-- STUDENT PANEL -->
    <div class="dash-header animate">
        <div class="dash-welcome">
            پنل دانش‌آموز
            <small>ثبت حضور و مشاهده سابقه</small>
        </div>
    </div>

    <?php
    $student_id = $_SESSION['user']['id'];
    $myTotal = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=?");
    $myTotal->execute([$student_id]);
    $myCount = $myTotal->fetchColumn();

    $todayCheck = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=? AND attendance_date=?");
    $todayCheck->execute([$student_id, date("Y-m-d")]);
    $todayCount = $todayCheck->fetchColumn();
    ?>

    <div class="stat-row">
        <div class="stat-pill animate delay-1">
            <div class="stat-pill-value"><?= $myCount ?></div>
            <div class="stat-pill-label">کل حضورها</div>
        </div>
        <div class="stat-pill animate delay-2">
            <div class="stat-pill-value"><?= $todayCount ?></div>
            <div class="stat-pill-label">امروز</div>
        </div>
    </div>

    <div class="card attendance-form-card animate delay-1" style="margin-bottom: 1.5rem;">
        <div class="card-title">ثبت حضور</div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label class="form-label">انتخاب درس</label>
                    <select class="form-select" name="course_id" required>
                        <option value="">یک درس انتخاب کنید...</option>
                        <?php
                        $courses = $db->query("SELECT * FROM courses");
                        while($course = $courses->fetch()):
                        ?>
                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button class="btn btn-primary" name="mark_attendance">✓ ثبت حضور</button>
            </div>
        </form>
    </div>

    <div class="card animate delay-2">
        <div class="table-topbar">
            <h3>📅 سابقه حضور من</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>درس</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $query = $db->prepare("
                    SELECT courses.course_name, attendance.status, attendance.attendance_date
                    FROM attendance
                    JOIN courses ON attendance.course_id=courses.id
                    WHERE attendance.student_id=?
                    ORDER BY attendance.id DESC
                ");
                $query->execute([$student_id]);
                $rows = $query->fetchAll();
                if(count($rows) == 0):
                ?>
                <tr><td colspan="3" style="text-align:center; color: var(--text-dim); padding: 2rem;">هنوز حضوری ثبت نکرده‌اید</td></tr>
                <?php else:
                foreach($rows as $row): ?>
                <tr>
                    <td style="font-weight: 500;"><?= htmlspecialchars($row['course_name']) ?></td>
                    <td><span class="badge badge-present"><?= $row['status'] ?></span></td>
                    <td style="color: var(--text-muted); font-size: 13px;"><?= $row['attendance_date'] ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php endif; ?>

</body>
</html>