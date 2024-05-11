<?php
$login=0;
$invalid=0;
if($_SERVER['REQUEST_METHOD']=='POST'){
  include 'connect.php';
  $email=$_POST['email'];
  $password=$_POST['password'];
  // $hash=password_hash($password,PASSWORD_DEFAULT);
$sql = "SELECT d.email, d.password 
        FROM driver AS d 
        JOIN mechanic AS m ON d.email != m.email OR d.password != m.password 
        WHERE d.email = '$email' AND d.password ='$password' OR m.email = '$email' AND m.password ='$password'  ";

$result=mysqli_query($con, $sql);
if($result){
  $num=mysqli_num_rows($result);
  if($num>0){
    // echo"login successful";
    $login=1;
    session_start();
    $_SESSION['email']=$email;
    header('location:main.php');
  }else{
    // echo" account doesn't exist please create   new account!";
    $invalid=1;
   }
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title></title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
<div class="login">
  <h3>logIn</h3>
  <form class="form" action="login.php" method="post">
    <label for="Email">Email</label><br />
    <input type="email" name="email" id="email" placeholder="Enter your email" class="input" required><br />
    <label for="password">password</label><br />
    <input type="password" name="password" id="password" class="input" placeholder="enter your password" required><br />
    <?php if ($invalid) {
        echo "<p style='color: red;'>invallid password or email address</p>";
    } ?>
    <button>login</button> 
  </form> 
  <a href="driver_signup.php">signup as driver</a><br>
  <a href="mechanics_signup.php">signup as mechanic</a>
</div>
</body>
</html>