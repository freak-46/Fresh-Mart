<?php
session_start();
include 'db.php';

// Check if the cart is empty
$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Cart - Fresh Mart</title>
    <link rel="stylesheet" href="output.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto p-6">
        <h1 class="text-3xl font-bold mb-6 text-green-600">Your Shopping Cart</h1>

        <?php if (empty($cart_items)): ?>
            <div class="bg-white p-10 rounded-xl shadow-sm text-center">
                <p class="text-gray-500 mb-4">Your cart is empty!</p>
                <a href="products_page.php?cat=fruits" class="text-green-500 font-bold underline">Go Shopping</a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <?php 
                foreach ($cart_items as $p_id => $details): 
                    // Fetch product details for each item in the cart
                    $p_query = "SELECT * FROM Product WHERE ID = '$p_id'";
                    $p_result = mysqli_query($conn, $p_query);
                    $product = mysqli_fetch_assoc($p_result);
                ?>
                    <div class="flex items-center p-4 border-b last:border-0">
                        <img src="../images/<?php echo $product['Image']; ?>" class="w-16 h-16 object-cover rounded-lg">
                        <div class="ml-4 flex-1">
                            <h3 class="font-bold"><?php echo $product['Name']; ?></h3>
                            <p class="text-gray-500 text-sm">
                                <?php echo $details['quantity']; ?> <?php echo $details['unit']; ?>
                            </p>
                        </div>
                        <a href="remove_item.php?id=<?php echo $p_id; ?>" class="text-red-400 hover:text-red-600 px-3">
                            Remove
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <a href="products_page.php?cat=fruits" class="text-gray-600 font-semibold">← Continue Shopping</a>
                <button class="bg-green-500 text-white px-8 py-3 rounded-full font-bold shadow-lg hover:bg-green-600 transition">
                    Checkout
                </button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>