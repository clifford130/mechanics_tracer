<?php
$success = 0;
$user = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'connect.php';
    $Fullname = $_POST['Fullname'];
    $email = $_POST['email'];
    $phonenumber = $_POST['phonenumber'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];

    // Check if the email or phone number already exists in the database
    $sql = "SELECT * FROM driver WHERE email='$email' OR phonenumber='$phonenumber'";
    $result = mysqli_query($con, $sql);
    if ($result) {
        $num = mysqli_num_rows($result);
        if ($num > 0) {
            // Either email or phone number already exists
            $user = 1;
        } else {
            // Email and phone number are unique, proceed with registration
            if ($password === $cpassword) {
                $sql = "INSERT INTO driver(Fullname,email,phonenumber,password) 
                        VALUES('$Fullname',
                               '$email',
                               '$phonenumber',
                               '$password')";
                $result = mysqli_query($con, $sql);
                if ($result) {
                    $success = 1;
                    header('location:login.php');
                    exit; // exit script after redirection
                } else {
                    die(mysqli_error($con));
                }
            } else {
                // Passwords do not match
                // You might want to handle this case appropriately
            }
        }
    } else {
        die(mysqli_error($con));
    }

    mysqli_close($con);
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Driver Signup</title>
    <link rel="stylesheet" href="driver_signup.css">
    <script>
        function validateForm() {
            var password = document.getElementById("password").value;
            var confirmPassword = document.getElementById("cpassword").value;

            if (password.length < 8) {
                alert("Password should be at least 8 characters long.");
                return false;
            }

            var hasNumber = /\d/.test(password);
            var hasUppercase = /[A-Z]/.test(password);
            var hasSymbol = /[^A-Za-z0-9]/.test(password);

            if (!(hasNumber && hasUppercase && hasSymbol)) {
                alert("Password should contain at least one number, one uppercase letter, and one symbol.");
                return false;
            }

            if (password !== confirmPassword) {
                alert("Passwords don't match.");
                return false;
            }

            return true;
        }
    </script>
</head>

<body>

    <div class="driver">
        <h3>Driver Signup Form</h3>
        <form action="driver_signup.php" method="post" onsubmit="return validateForm()">

            <label for="name">Full name</label><br />
            <input type="text" name="Fullname" id="Fullname" placeholder="Enter your full name" required><br />

            <label for="Email">Email</label><br />
            <input type="email" name="email" id="email" placeholder="Enter your email" required><br />

            <label for="phone_number">Phone number</label><br />
            <input type="tel" name="phonenumber" id="phonenumber" placeholder="Phone number" required><br />

            <label for="password">Password</label><br />
            <input type="password" name="password" id="password" placeholder="Enter new password" required><br />

            <label for="confirm_password">Confirm password</label><br />
            <input type="password" name="cpassword" id="cpassword" placeholder="Confirm your password" required><br />

            <button>Sign Up</button><br>
            <?php if ($user) {
        echo "<p style='color: red;'>Account exists, please login!</p>";
    } ?>

            <br>
            <a href="mechanics_signup.php">Sign up as mechanic</a>
            <br>
            <a href="login.php">Login</a>
        </form>
    </div>
</body>

</html>
