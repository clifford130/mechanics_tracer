<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('location:login.php');
}

include 'connect.php';
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
           :root {
            --background-color: #fff; 
            --text-color: #000; 
        }

        body {
            background-color: var(--background-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }
        body.dark {
            --background-color: #333;
            --text-color: #fff;
        }
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
            padding-right: 10px;
            padding-top: 5px;
            padding-bottom: 5px;
        }

          .logout{
            padding-left: 10px;
            padding-right: 10px;
            font-size: large;
            background-color: blue;
            border-radius: 10px;
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
        button:hover{
            cursor:pointer;

        }
    </style>
</head>
<body>
    
    <div class="header">
        <h3><img src="logo.jpg" style="height:30px; width:30px;" alt="">mechanics tracer</h3>
        <div class="user">
        <button onclick="toggleTheme()"  class="button">Dark/light theme</button>
            <p style="font-size: larger; margin-bottom: 0;">Welcome <?php echo $_SESSION['email']; ?></p>
            <a href="logout.php" style="text-decoration: none;font-size: larger;"> <button class="logout">Logout</button></a>
        </div>
    </div>

    <div class="search">
        <input type="search" id="live-search" placeholder="Search a mechanic">
        <button id="search-button">
            <!-- <i class='bx bx-search'></i> -->
    </button>
    </div>

    <!-- search -->
    <div id="searchresult"></div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            //handles search input
            function search() {
                var input = $("#live-search").val();
                if (input !== "") {
                    $.ajax({
                        url: "/mechanics_tracer/livesearch.php",
                        method: "POST",
                        data: { input: input },
                        success: function(data) {
                            $("#searchresult").html(data);
                        }
                    });
                } else {
                // Clears search resultswhen no input
                    $("#searchresult").html(""); 
                }
            }

            // Call search function when key is released in the search input
            $("#live-search").keyup(function() {
                search();
            });

            $("#search-button").click(function(e) {
                e.preventDefault();
                search();
            });
        });
    </script>
    <script>
        function toggleTheme() {
            document.body.classList.toggle('dark'); 
            const isDarkMode = document.body.classList.contains('dark');
            localStorage.setItem('darkMode', isDarkMode);
        }

        const isDarkMode = localStorage.getItem('darkMode') === 'true';

        if (isDarkMode) {
            document.body.classList.add('dark');
        } else {
            document.body.classList.add('light');
        }
    </script>
</body>
</html>
