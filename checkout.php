<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch cart items for the summary panel
$cart_query = "SELECT c.*, p.Name, p.Price AS original_price, p.Image, p.Category 
               FROM cart c 
               INNER JOIN Product p ON c.Product_id = p.Id 
               WHERE c.user_id = $user_id";
$cart_result = mysqli_query($conn, $cart_query);

if (mysqli_num_rows($cart_result) === 0) {
    header("Location: index.php");
    exit();
}

$grand_total = 0;
$items_summary = [];
while ($row = mysqli_fetch_assoc($cart_result)) {
    $price_value = isset($row['original_price']) ? $row['original_price'] : (isset($row['Price']) ? $row['Price'] : 0);
    $clean_price = (float)str_replace(['¥', '$', ',', ' '], '', $price_value);
    $quantity = (int)$row['Quantity'];
    $grand_total += ($clean_price * $quantity);
    $items_summary[] = [
        'name' => $row['Name'],
        'quantity' => $quantity,
        'total' => $clean_price * $quantity
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Secure Settlement — Fresh Mart</title>
  <link rel="stylesheet" href="src/output.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-serif { font-family: 'Playfair Display', serif; }
  </style>
</head>
<body class="bg-[#FBFBFA] text-slate-800 antialiased min-h-screen flex flex-col">

  <!-- Minimalist Secure Header -->
  <header class="sticky top-0 z-50 px-6 py-4 bg-white border-b border-slate-100">
    <div class="flex items-center justify-between max-w-6xl mx-auto">
      <div class="flex items-center gap-2">
        <span class="text-xl">🔒</span>
        <h1 class="font-extrabold tracking-wider uppercase text-md text-emerald-950">Secure Checkout</h1>
      </div>
      <a href="cart.php" class="text-xs font-bold transition text-slate-400 hover:text-emerald-800">Return to Basket</a>
    </div>
  </header>

  <main class="grid flex-grow w-full max-w-6xl grid-cols-1 gap-8 px-4 py-12 mx-auto lg:grid-cols-12">
    
    <!-- Left Column: Shipping & Payment Forms (7 Columns) -->
    <div class="space-y-6 lg:col-span-7">
      <form action="place_order.php" method="POST" class="space-y-6">
        
        <!-- Delivery Address Panel -->
        <div class="bg-white border border-slate-100 p-8 rounded-[28px] shadow-sm">
          <h2 class="mb-6 font-serif text-lg font-black text-emerald-950">1. Delivery Destination</h2>
          
          <div class="space-y-4">
            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Full Name</label>
              <input type="text" name="full_name" required placeholder="John Doe" class="w-full px-4 py-3 text-sm transition border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-700">
            </div>

            <div>
              <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Street Address</label>
              <input type="text" name="address" required placeholder="123 Fresh Way, Chuo-ku" class="w-full px-4 py-3 text-sm transition border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-700">
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Postal Code</label>
                <input type="text" name="postal_code" required placeholder="100-0001" class="w-full px-4 py-3 text-sm transition border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-700">
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Phone Number</label>
                <input type="tel" name="phone" required placeholder="090-1234-5678" class="w-full px-4 py-3 text-sm transition border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-700">
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Options Panel -->
        <div class="bg-white border border-slate-100 p-8 rounded-[28px] shadow-sm">
          <h2 class="mb-6 font-serif text-lg font-black text-emerald-950">2. Settlement Method</h2>
          
          <div class="space-y-3">
            <label class="flex items-center gap-4 p-4 border cursor-pointer border-emerald-600 bg-emerald-50/30 rounded-xl">
              <input type="radio" name="payment_method" value="Cash on Delivery" checked class="text-emerald-800 focus:ring-emerald-700">
              <div>
                <p class="text-sm font-bold text-emerald-950">Cash on Delivery (COD)</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Pay with cash upon physical distribution arrival.</p>
              </div>
            </label>

            <label class="flex items-center gap-4 p-4 border cursor-not-allowed border-slate-100 rounded-xl opacity-60">
              <input type="radio" name="payment_method" value="Credit Card" disabled class="text-slate-300">
              <div>
                <p class="text-sm font-bold text-slate-500">Credit / Debit Card</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Online gateway processing currently offline.</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Master Submission Button -->
        <button type="submit" class="w-full py-4 text-xs font-bold tracking-widest text-white uppercase transition bg-orange-500 shadow-lg rounded-2xl hover:bg-orange-600 shadow-orange-500/10">
            Authorize Order Settlement (¥<?php echo number_format($grand_total); ?>)
        </button>
      </form>
    </div>

    <!-- Right Column: Order Manifest Summary (5 Columns) -->
    <div class="lg:col-span-5">
      <div class="bg-white border border-slate-100 p-6 rounded-[28px] shadow-sm sticky top-24">
        <h3 class="mb-4 text-xs font-bold tracking-widest uppercase text-slate-400">Provision Summary</h3>
        
        <div class="pr-2 overflow-y-auto divide-y divide-slate-100 max-h-64">
          <?php foreach ($items_summary as $item): ?>
            <div class="flex items-center justify-between py-3 text-xs">
              <div>
                <p class="font-bold text-slate-800"><?php echo htmlspecialchars($item['name']); ?></p>
                <p class="text-slate-400 mt-0.5">Quantity: <?php echo $item['quantity']; ?></p>
              </div>
              <span class="font-semibold text-slate-700">¥<?php echo number_format($item['total']); ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="flex items-center justify-between pt-4 mt-4 border-t border-slate-100">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Valuation:</span>
          <span class="text-xl font-black text-emerald-950">¥<?php echo number_format($grand_total); ?></span>
        </div>
      </div>
    </div>

  </main>
</body>
</html>