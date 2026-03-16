<?php
session_start();
include "../config.php";
require_once __DIR__ . "/../../admin/includes/rate_limit.php";

$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Rate limiting (protects against brute force)
    $rateErr = rate_limit_check();
    if ($rateErr !== null) {
        $error = $rateErr;
    } elseif(empty(trim($_POST['email'] ?? '')) || empty($_POST['password'] ?? '')){
        $error = "Please fill in all fields.";
    } else {

        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows == 1){
            $user = $result->fetch_assoc();

            if(password_verify($password, $user['password'])){

                $userStatus = $user['status'] ?? 'active';
                if ($userStatus === 'suspended') {
                    rate_limit_record_failure();
                    $error = "Your account has been suspended. Please contact support.";
                } elseif ($userStatus === 'deleted') {
                    rate_limit_record_failure();
                    $error = "This account no longer exists. Please contact support if you believe this is an error.";
                } else {
                    rate_limit_clear_success();

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_completed'] = $user['profile_completed'] ?? 1;

                // Redirect by role
                if($user['role'] == "admin"){
                    header("Location: " . ADMIN_URL . "dashboard.php");
                }
                elseif($user['role'] == "pending"){
                    header("Location: ".FORMS_URL."profile/role_selection.php");
                }
                elseif($user['role'] == "driver" && ($user['profile_completed'] ?? 0) == 0){
                    header("Location: ".BASE_URL."forms/profile/driver_profile.php");
                }
                elseif($user['role'] == "mechanic" && ($user['profile_completed'] ?? 0) == 0){
                    header("Location: ".BASE_URL."forms/profile/mechanic_profile.php");
                }
                elseif($user['role'] == "driver"){
                    header("Location: ".BASE_URL."dashboard/driver_dashboard.php");
                }
                elseif($user['role'] == "mechanic"){
                    header("Location: ".BASE_URL."dashboard/mechanic_dashboard.php");
                }
                else {
                    header("Location: " . BASE_URL);
                }

                }
            } else {
                rate_limit_record_failure();
                $error = "Incorrect password.";
            }

        } else {
            rate_limit_record_failure();
            $error = "Account not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | MechanicTracer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/mechanics_tracer/assets/css/ux_enhancements.css">
    <script src="/mechanics_tracer/assets/js/ux_enhancements.js"></script>
    <style>
        /* Reuse same styles as signup */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f5f6fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .auth-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .auth-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            text-align: center;
        }

        .auth-card h1 {
            margin-bottom: 10px;
            font-size: 24px;
            color: #2c3e50;
        }

        .subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        form {
            display: flex;
            flex-direction: column;
        }

        input {
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid #dcdde1;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .btn-primary {
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #3498db;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .auth-link {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 15px;
        }

        .auth-link a {
            color: #3498db;
            text-decoration: none;
        }

        .auth-link a:hover {
            text-decoration: underline;
        }

        /* Media Queries */
        @media (max-width: 480px) {
            .auth-card {
                padding: 20px;
            }

            .auth-card h1 {
                font-size: 20px;
            }

            input {
                font-size: 13px;
                padding: 10px 12px;
            }

            .btn-primary {
                font-size: 14px;
                padding: 10px;
            }
        }

        @media (max-width: 360px) {
            .auth-card {
                padding: 15px;
            }

            .auth-card h1 {
                font-size: 18px;
            }

            input {
                font-size: 12px;
                padding: 8px 10px;
            }

            .btn-primary {
                font-size: 13px;
                padding: 8px;
            }
        }

        .error {
            background: #ffe5e5;
            color: #b00020;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div id="system-initial-loader">
        <div class="loader-logo"><i class="fas fa-wrench" style="margin-right:10px;"></i>MechanicTracer</div>
        <div class="loader-bar-container"><div class="loader-bar-fill"></div></div>
        <div style="margin-top:15px; color:#64748b; font-size:0.9rem;">Starting system...</div>
    </div>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Login</h1>
            <p class="subtitle">Access your account</p>


            <!-- ✅ Error displayed inside form -->
            <?php if($error!=""): ?>
            <div class="error">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            <form action="login.php" method="POST" onsubmit="MT_Loader.showButton(this.querySelector('button[type=submit]'))">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <p class="auth-link">
                Don't have an account? <a href="signup.php">Sign Up</a>
            </p>
        </div>
    </div>

</body>

</html>