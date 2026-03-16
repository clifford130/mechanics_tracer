<?php
if (!isset($page_title)) $page_title = 'Admin';
if (!isset($active_nav)) $active_nav = '';
$admin_name = "Admin";
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $admin_name = explode(" ", $row['full_name'])[0];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | Admin MechanicTracer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/mechanics_tracer/assets/css/ux_enhancements.css">
    <script>window.LOADER_MANUAL_INIT = true;</script>
    <script src="/mechanics_tracer/assets/js/ux_enhancements.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f6f8; min-height: 100vh; }
        .app-wrapper { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px; background: #0f172a; color: #e2e8f0;
            display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh;
        }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid #334155; }
        .sidebar-header h2 { font-size: 1.3rem; color: #fff; }
        .sidebar-header p { font-size: 0.85rem; color: #94a3b8; margin-top: 4px; }
        .nav-links { flex: 1; padding: 20px 0; }
        .nav-links a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: #cbd5e1; text-decoration: none; font-size: 0.95rem;
        }
        .nav-links a i { width: 22px; text-align: center; }
        .nav-links a:hover, .nav-links a.active { background: #1e293b; color: #fff; border-left: 4px solid #3b82f6; }
        .sidebar-footer { padding: 20px; border-top: 1px solid #334155; }
        .sidebar-footer a { display: flex; align-items: center; gap: 10px; color: #f87171; text-decoration: none; font-weight: 500; }
        .main-content { flex: 1; padding: 28px 32px; max-width: 1400px; margin: 0 auto; width: 100%; overflow-x: auto; }
        .top-bar { margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .top-bar h1 { font-size: 1.6rem; color: #0f172a; }
        .top-bar p { color: #64748b; margin-top: 4px; font-size: 0.95rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #fff; border-radius: 14px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .stat-card h3 { font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .stat-card .num { font-size: 1.8rem; font-weight: 700; color: #0f172a; }
        .card {
            background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;
        }
        .card h2 { font-size: 1.1rem; margin-bottom: 16px; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        th { color: #64748b; font-weight: 600; background: #f8fafc; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-accepted { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-driver { background: #e0e7ff; color: #3730a3; }
        .badge-mechanic { background: #ddd6fe; color: #5b21b6; }
        .badge-admin { background: #fecaca; color: #991b1b; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 0.88rem; font-weight: 500; cursor: pointer; text-decoration: none; border: none; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-primary:hover { background: #1e293b; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 6px 10px; font-size: 0.8rem; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; align-items: center; }
        .filters select, .filters input { padding: 8px 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.9rem; }
        .empty-state { color: #64748b; padding: 24px; text-align: center; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; color: #334155; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.95rem;
        }
        .flex { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .mt-2 { margin-top: 16px; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle { display: block !important; }
        }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #0f172a; }
    </style>
</head>
<body>
<div id="system-initial-loader">
    <div class="loader-logo"><i class="fas fa-shield-alt" style="margin-right:10px;"></i>Admin Panel</div>
    <div class="loader-bar-container"><div class="loader-bar-fill"></div></div>
    <div style="margin-top:15px; color:#64748b; font-size:0.9rem;">Opening administration console...</div>
</div>
<div class="app-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-shield-alt" style="margin-right: 8px;"></i>Admin</h2>
            <p><?php echo htmlspecialchars($admin_name); ?></p>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="<?php echo $active_nav === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="users.php" class="<?php echo $active_nav === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a>
            <a href="bookings.php" class="<?php echo $active_nav === 'bookings' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i> Bookings</a>
            <a href="ratings.php" class="<?php echo $active_nav === 'ratings' ? 'active' : ''; ?>"><i class="fas fa-star"></i> Ratings</a>
            <a href="services.php" class="<?php echo $active_nav === 'services' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Services</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?php echo FORMS_URL; ?>auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>
    <main class="main-content">
