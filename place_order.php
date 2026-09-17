<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Capture full user input parameters from form submission cleanly
$payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
$full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
$address = mysqli_real_escape_string($conn, $_POST['address']);
$postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
$phone = mysqli_real_escape_string($conn, $_POST['phone']);

// 1. Gather Cart Context State
$cart_query = "SELECT c.*, p.Name, p.Price AS original_price 
               FROM cart c 
               INNER JOIN Product p ON c.Product_id = p.Id 
               WHERE c.user_id = $user_id";
$cart_result = mysqli_query($conn, $cart_query);

if (mysqli_num_rows($cart_result) === 0) {
    header("Location: index.php");
    exit();
}

$grand_total = 0;
$cart_items = [];
while ($row = mysqli_fetch_assoc($cart_result)) {
    $price_value = isset($row['original_price']) ? $row['original_price'] : (isset($row['Price']) ? $row['Price'] : 0);
    $clean_price = (float)str_replace(['¥', '$', ',', ' '], '', $price_value);
    $quantity = (int)$row['Quantity'];
    $grand_total += ($clean_price * $quantity);
    $cart_items[] = $row; 
}

$status = "Pending";

// 2. Insert master checkout statement row entry mapping 
$order_query = "INSERT INTO orders (user_id, total_amount, payment_method, status, created_at) 
                VALUES ($user_id, $grand_total, '$payment_method', '$status', NOW())";

if (mysqli_query($conn, $order_query)) {
    $order_id = mysqli_insert_id($conn);

    // 3. Populate sub-item array parameters into permanent data blocks
    foreach ($cart_items as $item) {
        $product_name = mysqli_real_escape_string($conn, $item['Name']);
        $price_value = isset($item['original_price']) ? $item['original_price'] : (isset($item['Price']) ? $item['Price'] : 0);
        $clean_price = (float)str_replace(['¥', '$', ',', ' '], '', $price_value);
        $quantity = (int)$item['Quantity'];

        $item_query = "INSERT INTO order_items (order_id, product_name, price_at_purchase, quantity) 
                       VALUES ($order_id, '$product_name', $clean_price, $quantity)";
        mysqli_query($conn, $item_query);
    }

    // 4. Flush user cart assignment data structures
    $clear_cart_query = "DELETE FROM cart WHERE user_id = $user_id";
    mysqli_query($conn, $clear_cart_query);

    echo "<script>
            alert('Order successfully verified and logged into provisioning arrays!');
            window.location.href = 'index.php';
          </script>";
    exit();
} else {
    echo "Processing Error: " . mysqli_error($conn);
}
?>