<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>homepage</title>
  <link rel="stylesheet" href="homepagea.css">
  <style>
.typing-container {
    text-align: center;
    margin-top: 20px;
    color: white; 
  }

  .typing-text {
    font-family: sans-serif;
    font-size: large;
    white-space: nowrap;
    overflow: hidden;
    width: 100%;
    animation: typing 5s steps(40) infinite alternate;
  }
  button:hover{
    cursor: pointer;
  }

  @keyframes typing {
    from {
      width: 0;
    }

    to {
      width: 100%;
    }
  }
  </style>
</head>

<body style="background-color: white;">
  <section class="header">
    <nav>
      <a href="homepage.php"><img src="logo.jpg">mechanic tracer</a>
      <button class="menu-toggle">Menu</button>
      <div class="nav-links" id="navLinks">
        <ul>
          <li class="link"><a href="login.php" styyle="border: 1px solid black; height: 20px; width: 40px; font-size: larger; color: red;"><button  style="border-radius: 10px;padding-top: 5px;padding-bottom: 5px; padding-left: 3px; padding-right: 3px; background-color: blue; color: white; ">LOGIN</button></a></li>
          <li class="link"><a href="#services" stylre="border: 1px solid black; height: 20px; width: 40px; font-size: larger; color: red;"><button  style="border-radius: 10px;padding-top: 5px;padding-bottom: 5px; padding-left: 3px; padding-right: 3px; background-color: blue; color: white; ">SERVICES</button></a></li>

          <li class="link"><a href="#contact" stryle="border: 1px solid black; height: 20px; width: 40px; font-size: larger; color: red;"><button style="border-radius: 10px;padding-top: 5px;padding-bottom: 5px; padding-left: 3px; padding-right: 3px; background-color: blue; color: white; ">CONTACT US</button></a></li>
        </ul>
      </div>
    </nav>
    <div class="text-box">
      <h1>Find Trusted Mechanics Instantly</h1>
      <p style="font-size: larger;" color:white;>
     
      <p>Never worry about car trouble again. Mechanic Tracer connects you with reliable mechanics near you, saving you time and money.</p>
      <p>Whether you need a regular check-up or urgent repairs, we've got you covered. 
No more searching all over the internet or relying on word-of-mouth to find the right mechanic.
      With Mechanics Tracer, finding the right mechanic is quick and easy. Say goodbye to stress and hello to smooth sailing with your vehicle. Start your hassle-free journey with us today!"</p> <p>click the button below if you are a new user to register</p>
      

<!-- 
      <div class="typing-container">
        <div class="typing-text" id="typing-text-1"></div>
        <div class="typing-text" id="typing-text-2"></div>
        <div class="typing-text" id="typing-text-3"></div>
        <div class="typing-text" id="typing-text-4"></div>
      </div> -->
        <select name="signup" id="signup" class="hero-btn">
        <option value="mechanic" data-url="">Register </option>
          <option value="mechanic" data-url="/mechanics_tracer/mechanics_signup.php">mechanic </option>
          <option value="mechanic" data-url="/mechanics_tracer/driver_signup.php">driver</option>
        </select>

        <script>
          document.getElementById("signup").addEventListener("change", function(){
            var selectedOption=this.options[this.selectedIndex];
            var url=selectedOption.getAttribute("data-url");
            if(url){
              window.location.href=url;

            }
          });
        </script>

    </div>


  </section>

  <!--services-->

  <section class="campus" id="services">
    <div class="example">
      <h1> SAMPLE SERVICES PROVIDED  BY MECHANICS</h1>
      <div class="typing-container">
        <div class="typing-text" id="typing-text-1"></div>
        
      </div>
      <br>
      <div class="row">
        <div class="campus-col">
          <img src="mechanic1.jpg">
          <div class="layer">
            <h3>From oil changes to brake repairs, our mechanics can handle it all.</h3>
          </div>
        </div>
        <div class="campus-col">
          <img src="oil filter.jpeg">
          <div class="layer">
            <h3>Regular oil changes are essential for maintaining your vehicle's performance.</h3>
          </div>
        </div>
        <div class="campus-col">
          <img src="wheel alignment.jpeg">
          <div class="layer">
            <h3>Ensure smooth driving and longer tire life with proper wheel alignment.

</h3>
          </div>
        </div>
        
      </div>
    </div>

  </section>

  <section id="contact">
    <div class="contact-us">

      <h2 class="color: yellow;">contact-us</h2>
      <p>Have questions or need help? Get in touch with us!</p>
      <ul>
        <li><a href="tel:+254710698450">call</a></li>
        <li><a href="mailto:contactcenter@mechanicstracer@gmail.com"> email us</a></li>
      </ul>

  </section>

  <script>
   const texts = [
     "These are just a few. Sign up to access mechanics and have your vehicle repaired promptly"

      
   ];

   texts.forEach((text, index) => {
     const typingText = document.getElementById(`typing-text-${index + 1}`);
       typingText.textContent = text;
    });
    document.addEventListener("DOMContentLoaded", function() {
  const menuToggle = document.querySelector('.menu-toggle');
  const navLinks = document.querySelector('.nav-links ul');

  menuToggle.addEventListener('click', function() {
    navLinks.classList.toggle('active');
  });
});

  </script>
</body>

</html>
