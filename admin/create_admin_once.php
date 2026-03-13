<?php
/**
 * ONE-TIME SCRIPT: Creates the first admin user.
 * Run this once, then DELETE this file for security.
 *
 * Usage: Visit http://localhost/mechanics_tracer/admin/create_admin_once.php
 *        with POST: email, password, full_name, phone
 *        Or use defaults below for quick setup (change password immediately after).
 */
session_start();
require_once __DIR__ . '/../forms/config.php';

// Security: refuse to run if an admin already exists
$check = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($check && $check->num_rows > 0) {
    die('
    <html><body style="font-family:sans-serif;padding:40px;">
    <h2>Admin already exists</h2>
    <p>Delete this file (<code>admin/create_admin_once.php</code>) for security.</p>
    <p><a href="../forms/auth/login.php">Go to Login</a></p>
    </body></html>');
}

// Check if role enum supports admin (run add_admin_role.sql first if needed)
$cols = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'")->fetch_assoc();
if ($cols && strpos($cols['Type'], 'admin') === false) {
    die('
    <html><body style="font-family:sans-serif;padding:40px;">
    <h2>Database update needed</h2>
    <p>Run <code>admin/sql/add_admin_role.sql</code> in phpMyAdmin first, then refresh.</p>
    </body></html>');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? 'Admin');
    $phone = trim($_POST['phone'] ?? '0000000000');

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'admin';
        $profile_completed = 1;

        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role, profile_completed) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $full_name, $email, $hash, $phone, $role, $profile_completed);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = 'Could not create admin. Email may already exist.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin (One-Time)</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 420px; margin: 60px auto; padding: 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 8px; }
        .sub { color: #6b7280; font-size: 0.9rem; margin-bottom: 20px; }
        .box { background: #f0fdf4; border: 1px solid #86efac; padding: 14px; border-radius: 8px; margin-bottom: 16px; color: #166534; }
        .err { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; }
        label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 0.9rem; }
        input { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #d1d5db; margin-bottom: 12px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #0f172a; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        button:hover { background: #1e293b; }
        .warn { margin-top: 24px; padding: 12px; background: #fef3c7; border-radius: 8px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <h1>Create first admin</h1>
    <p class="sub">One-time setup. Delete this file after use.</p>

    <?php if ($success): ?>
        <div class="box">
            <strong>Admin created.</strong> Log in at the main login page, then <strong>delete this file</strong>.
        </div>
        <a href="../forms/auth/login.php">Go to Login</a>
    <?php else: ?>
        <?php if ($error): ?><div class="box err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <label>Full name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? 'Admin'); ?>" required>
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="admin@example.com">
            <label>Phone</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="0700000000">
            <label>Password (min 8 chars)</label>
            <input type="password" name="password" required>
            <button type="submit">Create admin</button>
        </form>
        <div class="warn">⚠️ Delete <code>admin/create_admin_once.php</code> after creating the admin.</div>
    <?php endif; ?>
</body>
</html>
