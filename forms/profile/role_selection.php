<?php
session_start();

// handle role selection
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $role = $_POST['role'] ?? '';

    if($role === "driver" || $role === "mechanic"){
        $_SESSION['selected_role'] = $role;

        if($role === "driver"){
            header("Location: driver_profile.php");
        } else {
            header("Location: mechanic_profile.php");
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Select Role</title>
<style>
  body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: #f4f6f8;
  }
  .role-container {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
  }
  .role-card {
    background: #fff;
    padding: 30px 20px;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    text-align: center;
    max-width: 400px;
    width: 100%;
  }
  .role-card h1 { font-size: 1.8rem; margin-bottom: 10px; }
  .role-card p { color: #555; margin-bottom: 25px; }

  .role-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-around;
    gap: 15px;
  }

  .role-btn {
    flex: 1 1 120px;
    padding: 15px;
    border-radius: 10px;
    border: none;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.2s, background 0.2s;
  }
  .role-btn img { width: 45px; margin-bottom: 8px; }
  .role-btn:hover { transform: scale(1.05); }

  .driver { background: #3498db; color: #fff; }
  .mechanic { background: #2c3e50; color: #fff; }

  @media (max-width: 480px) {
    .role-buttons { flex-direction: column; }
    .role-btn { width: 100%; }
  }
</style>
</head>
<body>
  <div class="role-container">
    <div class="role-card">
      <h1>Welcome! Select Your Role</h1>
      <p>To get started, tell us who you are.</p>

      <!-- SAME DESIGN, now inside a form -->
      <form method="POST">
        <div class="role-buttons">
          <button type="submit" name="role" value="driver" class="role-btn driver">
            <img src="icons/driver.svg" alt="Driver Icon">Driver
          </button>

          <button type="submit" name="role" value="mechanic" class="role-btn mechanic">
            <img src="icons/mechanic.svg" alt="Mechanic Icon">Mechanic
          </button>
        </div>
      </form>

    </div>
  </div>
</body>
</html>