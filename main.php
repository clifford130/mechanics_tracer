<?php
if($_SERVER['REQUEST_METHOD']=='POST'){
  include 'connect.php';
}
session_start();
if(!isset($_SESSION['email'])){
    header('location:login.php');
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""/>
    <link rel="stylesheet" href="main.css">
    
    <style>
        *{
            margin:0;
            padding:0;
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
        .header h3{
          font-size:50px;
        }

        .user {
            text-align: right;
        }

        .search {
            text-align: center;
            margin-top: 0px;
            font-size: x-large;
            
        }
        button{
          padding-left:10px;
    padding-right:10px;/
    font-size: large;
    background-color:blue;
    border-radius:20px;
}
button:hover{
          padding-left:10px;
    padding-right:10px;/
    font-size: large;
    background-color:blue;
    border-radius:20px;
    cursor: pointer;
} 


        #map {
            height: 600px;
            width: 100%; 
            
        }
        </style>
</head>
<body>
    <div class="header" style="background-color: black;">
        <h3  style="color: white;"><img src="logo.jpg" style="height:30px; width:30px; color:yellow;" alt="">mechanics tracer</h3>
        
    <div class="search">
        <a href="/mechanics_tracer/ajax.php" style=" text-decoration: none;">search a mechanic</a>
        <p  style="color:white;">Tap the blue icons on the map to locate a mechanic</p>
        </form>
       </div>
        <div class="user">
            <p style="font-size: larger; margin-bottom: 0; color:white;"><?php echo $_SESSION['email']; ?></p>
            <a href="logout.php" style="text-decoration: none;font-size: larger;"><button>Logout</button></a>
        </div>
    </div>

     <!-- adding  location why the map is to be displayed  -->
    <div id="map"></div>

 <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
 integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
 crossorigin=""></script> 
 <script>
   
var rongo_lat = -0.755324;
var rongo_lng = 34.5999;
var kangaLat = -0.753254;
var kangaLng = 34.745140;

function handleError(error) {
  if (error.code === error.PERMISSION_DENIED) {
    alert("Location access denied. Please allow location permission to see your location on the map.");
  } else if (error.code === error.POSITION_UNAVAILABLE) {
    alert("Unable to determine your location. Please check your internet connection and try again.");
  } else {
    console.error("Unknown geolocation error:", error);
    alert("An unexpected error occurred while trying to access your location. Please try again later.");
  }
}

// Checking for internet conectivity
if (navigator.onLine) {
  if (navigator.geolocation.requestPermission) {
    navigator.geolocation.requestPermission()
      .then(function(permission) {
        if (permission === 'granted') {
          navigator.geolocation.getCurrentPosition(
            function(position) {
              var user_lat = position.coords.latitude;
              var user_lng = position.coords.longitude;

              var userMarker = L.marker([user_lat, user_lng]).addTo(map);
              userMarker.bindPopup("You are here!").openPopup();

              map.setView([user_lat, user_lng], 15);
              var johnmarker=L.marker([-0.817819, 34.611127]).addTo(map);
            },
            handleError
          );
        } else {
          // location
          alert("Location access denied. Please allow location permission to see your location on the map.");
        }
      })
      .catch(function(error) {
        console.error("Error requesting location permission:", error);
      });
  } else {
    // older browsers
    navigator.geolocation.getCurrentPosition(
      function(position) {
        var user_lat = position.coords.latitude;
        var user_lng = position.coords.longitude;

        var userMarker = L.marker([user_lat, user_lng]).addTo(map);
        userMarker.bindPopup("You are here!").openPopup();

        map.setView([user_lat, user_lng], 15);
      },
      handleError
    );
  }
} else {
  alert("Internet connection unavailable. Please connect to the internet to use location services.");
}

// Initializing the Leaflet map
var map = L.map('map', {
  center: [rongo_lat, rongo_lng],
  zoom: 15
});

// Adding tile layer to the map
var baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
});

baseLayer.addTo(map);
var kangaMarker = L.marker([kangaLat, kangaLng]).addTo(map);
kangaMarker.bindPopup("kanga vehicles autorepair").openPopup();

// mechanics array 
var mechanics = [
    {
        name: "John Doe",
        businessName: "kitere Vehicles Autorepair",
        phone: "+254710698451",
        lat: -0.83334,
        lng: 34.59998
    },
    {
        name: "Jane Smith",
        businessName: "Apex Auto Solutions",
        phone: "+254705763981",
        lat: -0.753254,
        lng: 34.745140
    },
     {
         name: "justine george",
         businessName: "Car Care Center",
         phone: "+254710698450",
         lat: -0.8257310851066734,
        lng: 34.60986774133074
    },
    {
        name: "Andrew Drake",
        businessName: "Vehicle Service Station",
        phone: "+25470576398",
        lat: -0.653254,
        lng: 34.735140
    },
   
];

mechanics.forEach(function(mechanic) {
    var marker = L.marker([mechanic.lat, mechanic.lng]).addTo(map);
    marker.bindPopup(`
        <b>${mechanic.businessName}</b><br>
        <p> ${mechanic.name}</p>
        <p> <a href="tel:${mechanic.phone}">${mechanic.phone}</a></p>
    `);
});




const redIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-icons/master/icons/red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });


function handleError(error) {
 
}

// Checking for internet
if (navigator.onLine) {
  
} else {
  alert("Internet connection unavailable. Please connect to the internet to use location services.");
}

 </script>
</body>
</html>
