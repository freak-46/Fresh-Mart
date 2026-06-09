<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $p_id = $_POST['product_id'];
    $qty = $_POST['quantity'];
    $unit = $_POST['unit'];

    // Create the cart session if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Add the item to the session array
    // We use the product_id as the key to make it easy to update quantities later
    $_SESSION['cart'][$p_id] = [
        'quantity' => $qty,
        'unit' => $unit
    ];

    // Send the user back to the products page
    header("Location: products_page.php?status=success");
    exit();
}
?>