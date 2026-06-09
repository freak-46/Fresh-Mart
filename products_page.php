
<?php
include 'db.php';

$category_name = $_GET['cat'];

$query = "SELECT * FROM Product WHERE Category = '$category_name'";
$result = mysqli_query($conn, $query);

?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $category_name; ?> - Fresh Mart</title>
  <link rel="stylesheet" href="src/output.css">
</head>

<body>
 <header class="sticky top-0 z-50 bg-white shadow-md ">
    <div class="flex justify-end pr-6 md:flex md:justify-end md:mr-18 md:gap-20 md:mt-1 gap-9 ">
      <h3
        class="font-serif font-bold text-gray-500 transition-colors duration-300 cursor-pointer hover:text-green-600">
        Track</h3>
      <h3
        class="font-serif font-bold text-gray-500 transition-colors duration-300 cursor-pointer hover:text-green-600">
        Help</h3>
      <a href="profile.php" class="w-6 "><img src="images/user.png" alt="Profile" class="transition-all duration-200 ease-in-out hover:bg-green-600 rounded-4xl"> </a>
    </div>

    <hr class=" md:border-gray-300 md:opacity-40 md:mt-3">

    <div class=" md:flex md:justify- md:items-center md:justify-between lg:justify-around">
      <div class="flex items-center justify-between mx-2 my-4 ">


        <div class="flex items-center gap-3 ">
          <img src="/images/shopping-cart-realistic_1284-6011.jpg.avif" alt="Fresh Mart Logo" width="40">
          <h1 class=" font-serif text-[23px] text-green-600 lg:text-[28px]">Fresh Mart</h1>
        </div>
        <div class=" md:hidden">
          <img src="/images/cart.svg" alt="User Cart" width="30">
        </div>
      </div>
      <search
        class="flex items-center px-2 mx-2 overflow-hidden transition-colors duration-300 border-2 border-gray-300 rounded-lg md:w-150 focus-within:border-green-600">
        <input type="text" placeholder="Search for products..." class="w-full outline-none h-11">
        <img src="/images/search.svg" alt="Search" width="35"
          class="w-10 p-1 bg-green-600 rounded-lg cursor-pointer hover:opacity-85 hover:transition-opacity hover:duration-300">
      </search>
      <div class="hidden cursor-pointer md:block hover:scale-105 hover:transition-transform hover:duration-400">
        <img src="/images/cart.svg" alt="User Cart" width="30">
      </div>

    </div>
  </header>


   <h2 class="mt-5 mb-5 text-4xl text-center bg-orange-100 ">
    <?php echo ucfirst($category_name); ?>
   </h2>


 <div class="grid grid-cols-2 gap-5 mt-4 sm:grid-cols-3 sm:gap-6 md:grid-cols-4 md:gap-9 lg:grid-cols-5 lg:mx-20 lg:gap-6 ">
    <?php while($row = mysqli_fetch_assoc($result)) { ?>
      <div class="bg-orange-100 rounded-lg h-50 sm:h-60 md:h-70">
            <img src="../images/<?php echo $row['Image']; ?>" class="object-cover w-full rounded-lg h-30 md:h-40 sm:h-35 mix-blend-multiply">
            <h2 class="font-bold text-center md:my-5 sm:my-3"><?php echo $row['Name']; ?></h2>
            <button onclick="openModal('<?php echo $row['ID']; ?>', '<?php echo $row['Name']; ?>')" 
        class="flex items-center justify-center w-full py-2 font-semibold text-white transition duration-300 delay-150 bg-green-500 rounded-lg cursor-pointer hover:bg-green-400">
         Add to Cart
        </button>  
            
        </div>
        
    <?php } ?>
 </div>
 
 

 <section class="grid grid-cols-2 pt-5 pl-5 mt-5 text-white bg-blue-950 sm:grid-cols-4 md:px-10">
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

 <div id="unitModal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black bg-opacity-50">
    <div class="w-full max-w-sm p-6 bg-white shadow-xl rounded-2xl">
        <h3 id="modalProductName" class="mb-4 text-xl font-bold text-gray-800">Select Quantity</h3>
        
        <form action="add_to_cart.php" method="POST">
            <input type="hidden" name="product_id" id="modalProductId">
            
            <label class="block mb-2 text-sm font-medium text-gray-700">Choose Unit (kg/pcs):</label>
            <div class="flex gap-4 mb-6">
                <input type="number" name="quantity" value="1" min="1" class="w-20 p-2 border rounded-lg outline-green-500">
                <select name="unit" class="flex-1 p-2 border rounded-lg outline-green-500">
                    <option value="kg">Kilograms (kg)</option>
                    <option value="pcs">Pieces (pcs)</option>
                </select>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 py-2 font-semibold bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="flex-1 py-2 font-semibold text-white bg-green-500 rounded-lg shadow-md">Confirm</button>
            </div>
        </form>
    </div>
</div>
<script src="main.js"></script>
</body>
</html>

