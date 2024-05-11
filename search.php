<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('location:login.php');
}

include 'connect.php';
// Initializing an empty array to store search results
$searchResults = []; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $searchTerm = $_POST['search'];
   
    $sql = "SELECT * FROM mechanic WHERE Fullname LIKE '%$searchTerm%'";
    $result = mysqli_query($con, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $searchResults[] = $row;
        }
    }
}
?>

    
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="/search.css">
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
        }

        .user {
            text-align: right;
        }

        .search {
            text-align: center;
            margin-top: 0px;
        }

        .search button {
            border: none;
            background: white;
            cursor: pointer;
        }

        .search::placeholder {
            font-size: larger;
        }

        input {
            padding-left: 50px;
            padding-right: 50px;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        button {
            padding-left: 10px;
            padding-right: 10px;
            font-size: large;
            background-color: lightblue;
            border-radius: 20px;
        }
        
        button:hover{
           cursor:pointer;
           font-size: large;
           background-color:blue;
           border-radius:20px;
}

        .mechanic-card {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            background-color: #f9f9f9;
            width: 300px;;
            
        }

        .mechanic-card h3 {
            margin-bottom: 10px;
        }

        .mechanic-card p {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h3><img src="logo.jpg" style="height:30px; width:30px;" alt="">mechanics tracer</h3>
        <div class="user">
            <p style="font-size: larger; margin-bottom: 0;">Welcome <?php echo $_SESSION['email']; ?></p>
            <a href="logout.php" style="text-decoration: none;font-size: larger;"> <button>Logout</button></a>
        </div>
    </div>

    <div class="search">
        <form action="" method="post">
            <input type="search" name="search" id="search" placeholder="Search a mechanic">
            <button type="submit" name="submit"><i class='bx bx-search'></i></button>
        </form>
    </div>

    <?php if (!empty($searchResults)) : ?>
        <?php foreach ($searchResults as $mechanic) : ?>
            <div class="mechanic-card">
                <h3><?php echo $mechanic['Fullname']; ?></h3>
                <p>Business Name: <?php echo $mechanic['businessname']; ?></p>
                <p>Area of Expertise: <?php echo $mechanic['areaofexpertise']; ?></p>
                <p>Phone Number: <a href="tel:<?php echo $mechanic['phonenumber']; ?>"><?php echo $mechanic['phonenumber']; ?></a></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

