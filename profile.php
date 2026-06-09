<?php 
session_start(); 

$is_logged_in = isset($_SESSION['user_name']); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Fresh Mart</title>
    <link rel="stylesheet" href="src/output.css">
</head>
<body class="flex flex-col min-h-screen text-gray-800 bg-gradient-to-r from-green-500 to-green-800">

    <main class="flex flex-col grow">
        
        <?php if (!$is_logged_in): ?>
            <h1 class="p-10 text-3xl font-bold text-center text-white sm:text-4xl sm:mb-18">welcome To Fresh Mart</h1>
            <div class="bg-white rounded-t-[50px] flex flex-col items-center justify-center gap-7 grow sm:rounded-t-[70px] ">
             <a href="signin.php" class="py-2 text-3xl text-center text-white transition-colors duration-300 bg-green-600 w-50 textfont-bold h-13 rounded-2xl hover:bg-green-600">
                Login
              </a>
             <a href="signup.php" class="py-2 text-3xl text-center text-white transition-colors duration-300 bg-green-600 w-50 textfont-bold h-13 rounded-2xl hover:bg-green-600">
                Sign Up
              </a>
              <a href="index.html" class="text-green-800 text hover:underline">
                Back to Home
              </a>
          
            
 
        <?php else: ?>
            <div class="w-full max-w-4xl p-8 mx-auto grow">
                <h1 class="mb-6 text-3xl font-bold text-center text-emerald-600">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>  
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                  <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                        <h3 class="mb-2 text-lg font-bold text-emerald-600">Account Settings</h3>
                    </div>
                    <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                        <h3 class="mb-2 text-lg font-bold text-emerald-600">My Orders</h3>
                        <p class="text-sm text-gray-500">Track or view your active grocery deliveries.</p>
                    </div>
                    <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                        <h3 class="mb-2 text-lg font-bold text-emerald-600">Delivery Address</h3>
                        <p class="text-sm text-gray-500">Update your current residence details.</p>
                    </div>
                    <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                        <a href="logout.php" class="text-sm font-semibold hover:underline">Log Out</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <section class="grid grid-cols-2 pt-5 pl-5 text-white bg-blue-950 sm:grid-cols-4 md:px-10">
    <div class="flex flex-col mb-5 sm:gap-3">
      <h1 class=" font-bold md:text-[25px]"> About Us</h1>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Our Story
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Careers
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Why Choose Us
      </span>
    </div>
    <div class="flex flex-col mb-5 sm:gap-3">
      <h1 class=" font-bold md:text-[25px]">Customer Care</h1>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Help Center
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Track Order
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Returns
      </span>
    </div>
    <div class="flex flex-col mb-5 sm:gap-3">
      <h1 class=" font-bold md:text-[25px]"> Shop</h1>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        All Products
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Deals
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        New Arrivals
      </span>
    </div>
    <div class="flex flex-col mb-5 sm:gap-3">
      <h1 class=" font-bold md:text-[25px]">Contact</h1>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        support@freshmart.com
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        090-1234-1532
      </span>
      <span class=" text-[12px] hover:text-gray-400 transition-colors duration-300 cursor-pointer md:text-[17px]">
        Mon-Sun: 8AM - 10PM
      </span>
    </div>
  </section>
  <hr class="border-gray-600 ">
  <footer class="py-4 text-sm text-center text-white bg-blue-950">
    &copy; 2024 Fresh Mart. All rights reserved.
  </footer>

</body>
</html>