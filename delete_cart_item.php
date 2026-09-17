<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

if (isset($_GET['id'])) {
    // 🌟 FIX: Added the missing underscore to $_GET
    $cart_id = (int)$_GET['id'];
    $user_id = (int)$_SESSION['user_id'];

    // Security check: ensure the item belongs to the logged-in user before deleting!
    $delete_query = "DELETE FROM cart WHERE id = $cart_id AND user_id = $user_id";
    mysqli_query($conn, $delete_query);
}

// Redirect right back to the updated cart page cleanly
header("Location: cart.php");
exit();
?>