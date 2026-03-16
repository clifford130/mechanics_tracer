<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/mechanics_tracer/forms/config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mechanic'){
    header("Location: /mechanics_tracer/forms/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT m.*, u.full_name AS mechanic_name FROM mechanics m JOIN users u ON m.user_id = u.id WHERE m.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mechanic = $stmt->get_result()->fetch_assoc();

if(!$mechanic) die("Mechanic profile not found.");
$mechanic_id = $mechanic['id'];

$ratingSummary = null;
$allRatings = [];
$rt = @$conn->query("SHOW TABLES LIKE 'ratings'");
if ($rt && $rt->num_rows) {
    $row = $conn->query("SELECT AVG(stars) AS avg_stars, COUNT(*) AS cnt FROM ratings WHERE mechanic_id = " . (int)$mechanic_id)->fetch_assoc();
    $ratingSummary = $row;
    $rq = $conn->prepare("
        SELECT r.stars, r.review, r.created_at, u.full_name AS driver_name
        FROM ratings r
        JOIN drivers d ON r.driver_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE r.mechanic_id = ?
        ORDER BY r.created_at DESC
    ");
    $rq->bind_param("i", $mechanic_id);
    $rq->execute();
    $allRatings = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Ratings | MechanicTracer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f6f8; min-height: 100vh; }
        .app-wrapper { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px; background: #0f172a; color: #e2e8f0;
            display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh;
        }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid #334155; }
        .sidebar-header h2 { font-size: 1.2rem; color: #fff; }
        .sidebar-header p { font-size: 0.85rem; color: #94a3b8; margin-top: 4px; }
        .nav-links { flex: 1; padding: 20px 0; }
        .nav-links a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: #cbd5e1; text-decoration: none; font-size: 0.95rem;
        }
        .nav-links a i { width: 22px; text-align: center; }
        .nav-links a:hover, .nav-links a.active { background: #1e293b; color: #fff; border-left: 4px solid #3b82f6; }
        .sidebar-footer { padding: 20px; border-top: 1px solid #334155; }
        .sidebar-footer a { display: flex; align-items: center; gap: 10px; color: #f87171; text-decoration: none; }

        /* Main */
        .main-content { flex: 1; padding: 28px 32px; }
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .top-bar h1 { font-size: 1.6rem; color: #0f172a; }
        .menu-toggle { display: none; background: none; border: none; font-size: 1.4rem; cursor: pointer; }

        /* Summary */
        .rating-summary { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 24px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .rating-stars { font-size: 2.5rem; color: #fbbf24; letter-spacing: 2px; }
        .rating-score { font-size: 2rem; font-weight: 700; color: #0f172a; }
        .rating-count { font-size: 0.95rem; color: #64748b; margin-top: 4px; }

        /* Stars bar */
        .stars-breakdown { display: flex; flex-direction: column; gap: 6px; margin-top: 6px; width: 100%; max-width: 320px; }
        .stars-row { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #475569; }
        .stars-bar-outer { flex: 1; height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .stars-bar-inner { height: 100%; background: #fbbf24; border-radius: 999px; transition: width 0.4s; }

        /* Reviews list */
        .reviews-list { display: grid; gap: 16px; }
        .review-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; }
        .review-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px; }
        .review-driver { font-weight: 700; color: #0f172a; }
        .review-date { font-size: 0.8rem; color: #94a3b8; }
        .review-stars { color: #fbbf24; font-size: 1.1rem; letter-spacing: 1px; }
        .review-text { color: #475569; font-size: 0.92rem; margin-top: 6px; line-height: 1.6; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state i { font-size: 3rem; color: #e2e8f0; margin-bottom: 16px; }
        .empty-state p { font-size: 1rem; color: #64748b; }

        @media (max-width: 768px) {
            .sidebar { position: fixed; z-index: 1000; transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle { display: block !important; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>
<div id="page-loader"></div>
<div class="app-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-wrench" style="margin-right:8px;"></i><?php echo htmlspecialchars($mechanic['garage_name']); ?></h2>
            <p>Mechanic Profile</p>
        </div>
        <nav class="nav-links">
            <a href="/mechanics_tracer/dashboard/mechanic_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="/mechanics_tracer/dashboard/mechanic_dashboard.php#bookings"><i class="fas fa-calendar-check"></i> Bookings</a>
            <a href="/mechanics_tracer/dashboard/mechanic_ratings.php" class="active"><i class="fas fa-star"></i> My Ratings</a>
            <a href="/mechanics_tracer/forms/profile/mechanic_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
        </nav>
        <div class="sidebar-footer">
            <a href="/mechanics_tracer/forms/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div>
                <button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>My Ratings</h1>
                <p style="color:#64748b;margin-top:4px;">See what drivers say about your service.</p>
            </div>
        </div>

        <?php if ($ratingSummary && (int)$ratingSummary['cnt'] > 0): ?>
        <!-- Summary card -->
        <div class="rating-summary">
            <div>
                <div class="rating-stars">
                    <?php
                    $avg = (float)$ratingSummary['avg_stars'];
                    $rounded = (int)round($avg);
                    echo str_repeat('★', $rounded) . str_repeat('☆', 5 - $rounded);
                    ?>
                </div>
                <div class="rating-score"><?php echo number_format($avg, 1); ?> <span style="font-size:1rem;color:#64748b;font-weight:400;">/ 5.0</span></div>
                <div class="rating-count"><?php echo (int)$ratingSummary['cnt']; ?> total rating<?php echo $ratingSummary['cnt'] != 1 ? 's' : ''; ?></div>
            </div>

            <!-- Stars breakdown -->
            <?php
            $breakdown = array_fill(1, 5, 0);
            foreach ($allRatings as $r) {
                $s = (int)$r['stars'];
                if ($s >= 1 && $s <= 5) $breakdown[$s]++;
            }
            ?>
            <div class="stars-breakdown">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <?php $pct = $ratingSummary['cnt'] > 0 ? round($breakdown[$i] / $ratingSummary['cnt'] * 100) : 0; ?>
                <div class="stars-row">
                    <span><?php echo $i; ?>★</span>
                    <div class="stars-bar-outer"><div class="stars-bar-inner" style="width:<?php echo $pct; ?>%"></div></div>
                    <span><?php echo $breakdown[$i]; ?></span>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- All reviews -->
        <div class="reviews-list">
            <?php foreach ($allRatings as $r): ?>
            <div class="review-card">
                <div class="review-card-header">
                    <span class="review-driver"><i class="fas fa-user" style="margin-right:6px;color:#94a3b8;"></i><?php echo htmlspecialchars($r['driver_name']); ?></span>
                    <span class="review-date"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></span>
                </div>
                <div class="review-stars"><?php echo str_repeat('★', (int)$r['stars']) . str_repeat('☆', 5 - (int)$r['stars']); ?></div>
                <?php if (!empty($r['review'])): ?>
                <p class="review-text"><?php echo nl2br(htmlspecialchars($r['review'])); ?></p>
                <?php else: ?>
                <p class="review-text" style="color:#94a3b8;font-style:italic;">No written review.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-star-half-alt"></i>
            <p>No ratings yet!</p>
            <p style="margin-top:8px;font-size:0.9rem;color:#94a3b8;">Complete jobs and drivers will rate your service here.</p>
        </div>
        <?php endif; ?>
    </main>
</div>
<script>
// Close sidebar on outside click
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('menuToggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});
</script>
<script src="/mechanics_tracer/assets/js/page_loader.js"></script>
</body>
</html>
