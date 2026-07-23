<?php
/**
 * Dwelro — Admin Login
 */

session_start();

// If already logged in, redirect to admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// HTML escape function
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        // Check against admin_users table
        $user = db_fetch_all("SELECT id, name, email, role, password_hash FROM admin_users WHERE email = :email AND password_hash IS NOT NULL AND is_active = 1", ['email' => $username]);

        if ($user && password_verify($password, $user[0]['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user[0]['id'];
            $_SESSION['admin_name'] = $user[0]['name'];
            $_SESSION['admin_email'] = $user[0]['email'];
            $_SESSION['admin_role'] = $user[0]['role'];

            // Update last login
            db_execute("UPDATE admin_users SET last_login = NOW() WHERE id = :id", ['id' => $user[0]['id']]);

            header('Location: admin.php');
            exit;
        } else {
            $login_error = 'Invalid username or password';
        }
    } else {
        $login_error = 'Please enter username and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dwerlo Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1a3a5c;--navy-dk:#0f2640;--navy-lt:#e8eef5;
  --gold:#c9a84c;--white:#fff;--bg:#f4f6fa;--border:#e5e9f0;
  --muted:#8a94a6;--ink:#1a1f2e;
  --green:#22c55e;--red:#ef4444;--amber:#f59e0b;--blue:#3b82f6;
  --serif:'DM Serif Display',serif;--sans:'DM Sans',sans-serif;
}
body{font-family:var(--sans);background:linear-gradient(135deg,var(--navy) 0%,#1e5799 100%);color:var(--ink);display:flex;min-height:100vh;align-items:center;justify-content:center;padding:20px}
.login-card{background:var(--white);border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.15)}
.logo{text-align:center;margin-bottom:32px}
.logo-icon{width:48px;height:48px;background:var(--navy);border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.logo-icon svg{width:24px;height:24px;fill:#fff}
.logo-text{font-family:var(--serif);font-size:24px;color:var(--navy);font-weight:400}.logo-text span{color:var(--gold)}
.form-field{margin-bottom:20px}
.form-field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--ink)}
.form-field input{width:100%;border:1px solid var(--border);border-radius:8px;padding:12px 16px;font-family:var(--sans);font-size:14px;color:var(--ink);outline:none}
.form-field input:focus{border-color:var(--navy)}
.form-field input:invalid{box-shadow:0 0 0 3px rgba(239,68,68,.15)}
.btn-login{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:14px 24px;font-family:var(--sans);font-size:14px;font-weight:600;width:100%;cursor:pointer;transition:.2s}
.btn-login:hover{background:var(--navy-dk)}
.error-msg{background:#fee2e2;color:#dc2626;padding:12px;border-radius:6px;font-size:13px;margin-bottom:20px;text-align:center}
@media(max-width:520px){
  body{padding:16px}
  .login-card{padding:28px;max-width:100%;width:100%}
}
</style>
</head>
<body>
<div class="login-card">
  <div class="logo">
    <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></div>
    <div class="logo-text">Dwerlo</div>
  </div>

  <?php if ($login_error): ?>
  <div class="error-msg"><?= h($login_error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-field">
      <label>Email address</label>
      <input type="email" name="username" required autocomplete="username" autofocus/>
    </div>
    <div class="form-field">
      <label>Password</label>
      <input type="password" name="password" required autocomplete="current-password"/>
    </div>
    <button type="submit" class="btn-login">Sign In</button>
  </form>
</div>
</body>
</html>
