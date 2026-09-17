<?php
session_start();
include 'db.php'; // Make sure this matches your db connection filename

if (!isset($_SESSION['user_id'])) {
    die("❌ Error: Please log in to your account first.");
}

$user_id = (int)$_SESSION['user_id']; // This will now dynamically be 1, 2, 3, etc.

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'])) {
    
    $product_id = (int)$_POST['product_id'];
    $weight     = mysqli_real_escape_string($conn, $_POST['weight']);
    $price      = mysqli_real_escape_string($conn, $_POST['price']);
    $quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    if ($quantity < 1) { $quantity = 1; }

    // 🌟 FIX 2: Capitalized 'Product_id', 'Weight', and 'Price' to match your phpMyAdmin screenshot exactly!
    $check_query = "SELECT * FROM cart WHERE user_id = $user_id AND Product_id = $product_id AND Weight = '$weight'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        // If it exists, update the quantity
        $update_query = "UPDATE cart SET Quantity = Quantity + $quantity WHERE user_id = $user_id AND Product_id = $product_id AND Weight = '$weight'";
        mysqli_query($conn, $update_query);
    } else {
        // If it's new, INSERT with exact column case matching your database layout
        $insert_query = "INSERT INTO cart (user_id, Product_id, Weight, Price, Quantity) VALUES ($user_id, $product_id, '$weight', '$price', $quantity)";
        if (!mysqli_query($conn, $insert_query)) {
            die("SQL Error: " . mysqli_error($conn));
        }
    }

    // Go back smoothly with a success tag attached
    if (isset($_SERVER['HTTP_REFERER'])) {
        // Checks if there's already a '?' query parameter in the previous URL
        $redirect_url = $_SERVER['HTTP_REFERER'];
        if (strpos($redirect_url, 'status=success') === false) {
            $separator = (strpos($redirect_url, '?') === false) ? '?' : '&';
            $redirect_url .= $separator . 'status=success';
        }
        header("Location: " . $redirect_url);
    } else {
        header("Location: index.php?status=success");
    }
    exit();
} else {
    echo "❌ Invalid request parameters.";
}
?>

