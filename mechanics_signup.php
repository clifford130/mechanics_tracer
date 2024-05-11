<?php

$success = 0;
$user = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'connect.php';
    $Fullname = $_POST['Fullname'];
    $email = $_POST['email'];
    $phonenumber = $_POST['phonenumber'];
    $businessname = $_POST['businessname'];
    $Location = $_POST['Location'];
    $areaofexpertise = $_POST['areaofexpertise'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];

    $email = mysqli_real_escape_string($con, $email);
    $phone = mysqli_real_escape_string($con, $phonenumber);

    $sql = "SELECT * FROM mechanic WHERE email='$email' OR phonenumber='$phonenumber'";
    $result = mysqli_query($con, $sql);

    if ($result) {
        $num = mysqli_num_rows($result);
        if ($num > 0) {
            $user = 1;
        }
    }

    $sql = "SELECT * FROM mechanic WHERE phonenumber='$phonenumber'";
    $result = mysqli_query($con, $sql);
    if ($result) {
        $num = mysqli_num_rows($result);
        if ($num > 0) {
            $user = 1;
        }
    }

    if ($user) {
        $errorMessage = "Account exists, please login!";
    } else {
        if ($password === $cpassword) {
            if (validatePassword($password)) {
                $sql = "INSERT INTO mechanic(Fullname,email,phonenumber,businessname,Location,areaofexpertise, password) 
                            VALUES('$Fullname',
                                    '$email',
                                   '$phonenumber',
                                   '$businessname',
                                   '$Location',
                                   '$areaofexpertise',
                                  '$password')";
            } else {
             
                $errorMessage = "Password should be at least 8 characters long and contain at least one number, one uppercase letter, and one symbol.";
                exit(); 
            }
        } else {
            $errorMessage = "Passwords don't match.";
            exit();
        }
        $result = mysqli_query($con, $sql);
        if ($result) {
            $success = 1;
            header('location:login.php');
        } else {
            die(mysqli_error($con));
        }
        mysqli_close($con);
    }
}

function validatePassword($password)
{
    return preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}$/', $password);
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mechanic's sign up form</title>
    <link rel="stylesheet" href="driver_signup.css">
</head>

<body>
    <div class="driver">
        <h3>Mechanics signup</h3>
        <form action="mechanics_signup.php" method="post">
            
            <label for="name">Full name</label><br />
            <input type="text" name="Fullname" id="Fullname" placeholder="Enter your full name" class="input" required><br />
            <label for="Email">Email</label><br />
            <input type="email" name="email" id="email" placeholder="Enter your email" class="input" required><br />
            <label for="phone_number">Phone number</label><br />
            <input type="tel" name="phonenumber" id="phonenumber" placeholder="Phone number" required><br />
            <label for="phone_number">Business name</label><br />
            <input type="tel" name="businessname" id="businessname" placeholder="Business name" required><br />
            <label for="phone_number">Area of expertise</label><br />
            <input type="tel" name="areaofexpertise" id="areaofexpertise" placeholder="e.g., oil change" required><br />
            <label for="password">New password</label><br />
            <input type="password" name="password" id="password" class="input" placeholder="Enter new password" required><br />
            <label for="confirm_password">Confirm password</label><br />
            <input type="password" name="cpassword" id="cpassword" class="input" placeholder="Confirm your password" required><br />
           
            <input type="hidden" name="Location" id="Location"><br>
            <?php if ($user) {
                echo "<p style='color: red;'>$errorMessage</p>";
            } ?>
            <button type="button" class="submit" onclick="getLocation()">allow  Location access</button><br>
            <script>
                function getLocation() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(showPosition);
                    } else {
                        alert("Geolocation is not supported by this browser.");
                    }
                }

                function showPosition(position) {
                    var latitude = position.coords.latitude;
                    var longitude = position.coords.longitude;
                
                    alert("Latitude: " + latitude + "\nLongitude: " + longitude);
                    
                    document.getElementById('Location').value = latitude + ',' + longitude;
                }

                // JavaScript function for password validation
                function validatePassword() {
                    var password = document.getElementById("password").value;
                    var confirmPassword = document.getElementById("cpassword").value;

                    if (password.length < 8) {
                        alert("Password should be at least 8 characters long.");
                        return false;
                    }

                    var regex = /^(?=.*\d)(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}$/;
                    if (!regex.test(password)) {
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
            <button class="submit" onclick="return validatePassword()">Sign Up</button><br>
            <a href="driver_signup.php">Sign up as driver</a>
            <br>
            <a href="login.php">Login</a>
        </form>
    </div>
</body>

</html>
