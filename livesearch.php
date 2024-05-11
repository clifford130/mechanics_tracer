<?php
include 'connect.php'; 

if(isset($_POST['input'])){
    $input = '%' . $_POST['input'] . '%'; 

    $query = "SELECT * FROM mechanic WHERE Fullname LIKE ?";
    $stmt = mysqli_prepare($con, $query);

    // Checking if statement prepared well
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $input); 
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if($result){ 
            if(mysqli_num_rows($result) > 0){
                echo "<style>
                   
                    .mechanic-card {
                        border: 1px solid #ccc;
                        border-radius: 10px;
                        padding: 20px;
                        margin-top: 20px;
                        background-color: rgb(221, 156, 156);
                        width: 300px;
                        font-size: larger;
                    }
                    h6{
                        font-size:larger;
                    }
                </style>";
                echo "<div class='cards-container'>";
                while($row = mysqli_fetch_assoc($result)){
                    $email = $row['email']; 
                    $Fullname = $row['Fullname'];
                    $phonenumber = $row['phonenumber'];
                    $businessname = $row['businessname'];
                    $areaofexpertise = $row['areaofexpertise'];
                    echo "<div class='mechanic-card'>
                            <h3>$Fullname</h3>
                            <p>Email: $email</p>
                            <p>Business Name: $businessname</p>
                            <p>Area of Expertise: $areaofexpertise</p>
                            <p>Phone Number: <a href='tel:$phonenumber'>$phonenumber</a></p>
                          </div>";
                }
                echo "</div>";
            } else {
                echo "<h6>No data found</h6>";
            }
        } else {
            echo "<h6>Error executing query</h6>";
        }
    } else {
        echo "<h6>Error preparing statement</h6>";
    }
} else {
    echo "<h6>No input received</h6>";
}
?>
