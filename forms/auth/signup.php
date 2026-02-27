
<?php
// signup.php

$errors = [];
$success = "";

// Database connection
// $conn = new mysqli("localhost", "root", "", "mechanic_tracer");

// if ($conn->connect_error) {
//     die("Database connection failed: " . $conn->connect_error);
// }
// require "db_connect.php";
require_once("../config.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($full_name)) $errors[] = "Full name is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $errors[] = "Email already registered";
    }

    // Insert if no errors
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (full_name,email,phone,password) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);

        if ($stmt->execute()) {
            $success = "Account created successfully. You can now login.";
            header("Location: login.php");
            $_POST = []; // clear form
        } else {
            $errors[] = "Something went wrong. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up | MechanicTracer</title>
<style>
  /* Base Styles */
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
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
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
    box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
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

  /* Media Queries for Responsiveness */
  @media (max-width: 480px) {
    .auth-card { padding: 20px; }
    .auth-card h1 { font-size: 20px; }
    input { font-size: 13px; padding: 10px 12px; }
    .btn-primary { font-size: 14px; padding: 10px; }
  }

  @media (max-width: 360px) {
    .auth-card { padding: 15px; }
    .auth-card h1 { font-size: 18px; }
    input { font-size: 12px; padding: 8px 10px; }
    .btn-primary { font-size: 13px; padding: 8px; }
  }

  .error-box p {
  margin:5px 0;
  color:#c0392b;
  font-size:0.9rem;
}

/* Success box */
.success-box {
  background:#e6ffe6;
  border:1px solid #2ecc71;
  padding:10px;
  border-radius:8px;
  margin-bottom:15px;
  color:#2d7a46;
  font-size:0.9rem;
}
</style>
</head>
<body>

<div class="auth-container">
  <div class="auth-card">
    <h1>Sign Up</h1>
    <p class="subtitle">Create your account to access MechanicTracer</p>

      <!-- ERROR MESSAGES -->
  <?php if (!empty($errors)): ?>
    <div class="error-box">
      <?php foreach ($errors as $error): ?>
        <p>❌ <?= $error ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- SUCCESS MESSAGE -->
  <?php if (!empty($success)): ?>
    <div class="success-box">
      <?= $success ?>
    </div>
  <?php endif; ?>

    <form action="signup.php" method="POST">
      <input type="text" name="full_name" placeholder="Full Name" value="<?= $_POST['full_name'] ?? '' ?>" required>
      <input type="email" name="email" placeholder="Email" value="<?= $_POST['email'] ?? '' ?>" required>
      <input type="text" name="phone" placeholder="Phone Number" value="<?= $_POST['phone'] ?? '' ?>" required>
      <input type="password" name="password" placeholder="Password" required>
      <input type="password" name="confirm_password" placeholder="Confirm Password" required>

      <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <p class="auth-link">
      Already have an account? <a href="login.php">Login</a>
    </p>
  </div>
</div>

</body>
</html>