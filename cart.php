<?php
session_start();
include 'db.php';

// 1. Safety Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Check if Product table has discount column
$has_discount_col = false;
$check_discount = mysqli_query($conn, "SHOW COLUMNS FROM Product LIKE 'discount'");
if ($check_discount && mysqli_num_rows($check_discount) > 0) {
    $has_discount_col = true;
}

// Check if Product table has in_stock column
$has_stock_col = false;
$check_stock = mysqli_query($conn, "SHOW COLUMNS FROM Product LIKE 'in_stock'");
if ($check_stock && mysqli_num_rows($check_stock) > 0) {
    $has_stock_col = true;
}

// Handle quantity update via AJAX/POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_qty'])) {
    $cart_id = (int)$_POST['cart_id'];
    $new_qty = max(1, min(99, (int)$_POST['quantity']));

    $update = mysqli_query($conn, "UPDATE cart SET Quantity = $new_qty WHERE id = $cart_id AND user_id = $user_id");
    if ($update) {
        header("Location: cart.php?updated=1");
        exit();
    }
}

// Handle item removal
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];
    mysqli_query($conn, "DELETE FROM cart WHERE id = $remove_id AND user_id = $user_id");
    header("Location: cart.php?removed=1");
    exit();
}

// Fetch cart items with product details (including discount)
$cart_query = "SELECT c.id AS cart_row_id, c.Weight, c.Price, c.Quantity, 
                      p.Name, p.Image, p.Category, p.Id as product_id";
if ($has_discount_col) {
    $cart_query .= ", p.discount";
}
if ($has_stock_col) {
    $cart_query .= ", p.in_stock";
}
$cart_query .= " FROM cart c 
                INNER JOIN Product p ON c.Product_id = p.Id 
                WHERE c.user_id = $user_id 
                ORDER BY c.id DESC";

$cart_result = mysqli_query($conn, $cart_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Cart — Fresh Mart</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Plus Jakarta Sans', 'sans-serif'],
            serif: ['Playfair Display', 'serif'],
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .qty-btn { transition: all 0.15s ease; }
    .qty-btn:active { transform: scale(0.92); }
    .cart-item { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .cart-item.removing { opacity: 0; transform: translateX(20px); }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .toast-enter { animation: slideIn 0.4s ease forwards; }
    .discount-badge {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
  </style>
</head>
<body class="flex flex-col min-h-screen bg-[#FBFBFA] text-slate-800 antialiased">

  <!-- Header -->
  <header class="sticky top-0 z-50 border-b shadow-sm bg-white/80 backdrop-blur-md border-slate-100">
    <div class="flex items-center justify-between max-w-5xl p-4 mx-auto">
      <a href="index.php" class="flex items-center gap-3 group">
        <div class="p-2 text-white transition duration-300 shadow-sm bg-emerald-900 rounded-xl group-hover:scale-105">
          <span class="block text-lg">🌿</span>
        </div>
        <div>
          <h1 class="text-lg font-extrabold tracking-tight text-emerald-950">Fresh<span class="font-serif italic font-medium text-orange-500">Mart</span></h1>
        </div>
      </a>
      <div class="flex items-center gap-4">
        <a href="index.php" class="text-xs font-bold tracking-widest uppercase transition duration-300 text-slate-500 hover:text-emerald-800">
          ← Continue Shopping
        </a>
      </div>
    </div>
  </header>

  <!-- Toast Notifications -->
  <?php if (isset($_GET['updated'])): ?>
  <div class="fixed z-50 px-6 py-3 text-sm font-bold text-white transform -translate-x-1/2 rounded-full shadow-lg bg-emerald-600 toast-enter top-24 left-1/2">
    ✓ Quantity updated
  </div>
  <?php endif; ?>
  <?php if (isset($_GET['removed'])): ?>
  <div class="fixed z-50 px-6 py-3 text-sm font-bold text-white transform -translate-x-1/2 bg-orange-500 rounded-full shadow-lg toast-enter top-24 left-1/2">
    ✓ Item removed
  </div>
  <?php endif; ?>

  <!-- Main Cart -->
  <main class="flex-grow w-full max-w-4xl p-6 mx-auto md:py-12">
    <div class="pb-4 mb-8 border-b border-slate-100">
      <h2 class="font-serif text-3xl font-black tracking-tight text-emerald-950">Your Shopping Basket</h2>
      <p class="mt-1 text-xs text-slate-400">Review your selections before checkout. Discounts are applied automatically.</p>
    </div>

    <?php if (mysqli_num_rows($cart_result) == 0): ?>
      <div class="py-20 text-center bg-white border border-slate-100 shadow-sm rounded-[32px]">
        <span class="block mb-4 text-5xl">🛒</span>
        <p class="mb-2 text-lg font-bold text-slate-700">Your cart is empty</p>
        <p class="mb-6 text-sm text-slate-400">Looks like you haven't added anything yet.</p>
        <a href="index.php" class="inline-block px-8 py-3.5 font-bold text-xs uppercase tracking-widest text-white bg-orange-500 shadow-lg shadow-orange-500/20 rounded-xl hover:bg-orange-600 transition duration-200">
          Start Shopping
        </a>
      </div>
    <?php else: ?>

      <div class="bg-white border border-slate-100 shadow-sm rounded-[24px] overflow-hidden">
        <!-- Cart Header -->
        <div class="hidden grid-cols-12 gap-4 px-8 py-4 text-xs font-bold tracking-wider uppercase text-slate-400 bg-slate-50 md:grid">
          <div class="col-span-6">Product</div>
          <div class="col-span-2 text-center">Quantity</div>
          <div class="col-span-3 text-right">Price</div>
          <div class="col-span-1"></div>
        </div>

        <div class="divide-y divide-slate-100">
          <?php 
          $grand_total = 0;
          $total_savings = 0;
          $item_count = 0;
          while ($row = mysqli_fetch_assoc($cart_result)): 
            $item_count++;

            // Get discount
            $discount_pct = 0;
            if ($has_discount_col && isset($row['discount'])) {
                $discount_pct = (int)$row['discount'];
            }

            // Clean and calculate prices
            $original_price = (float)str_replace(['¥', '$', ',', ' '], '', $row['Price']);
            $discounted_price = $discount_pct > 0 ? round($original_price * (1 - $discount_pct/100)) : $original_price;
            $quantity = (int)$row['Quantity'];
            $item_total = $discounted_price * $quantity;
            $item_original_total = $original_price * $quantity;
            $item_savings = $item_original_total - $item_total;
            $grand_total += $item_total;
            $total_savings += $item_savings;

            $is_out_of_stock = false;
            if ($has_stock_col && isset($row['in_stock']) && (int)$row['in_stock'] === 0) {
                $is_out_of_stock = true;
            }
          ?>
            <div class="cart-item relative px-6 py-6 md:px-8 <?php echo $is_out_of_stock ? 'bg-red-50/50' : ''; ?>">
              <?php if ($is_out_of_stock): ?>
              <div class="absolute top-0 right-0 px-3 py-1 text-[10px] font-bold tracking-wider text-white uppercase bg-red-500 rounded-bl-lg">
                Out of Stock
              </div>
              <?php endif; ?>

              <div class="grid items-center grid-cols-1 gap-4 md:grid-cols-12 md:gap-4">

                <!-- Product Info -->
                <div class="flex items-center gap-4 md:col-span-6">
                  <div class="relative flex-shrink-0">
                    <img src="<?php echo htmlspecialchars($row['Image']); ?>" 
                         class="object-cover w-16 h-16 p-1 border rounded-xl border-slate-100 bg-slate-50 <?php echo $is_out_of_stock ? 'opacity-50 grayscale' : ''; ?>"
                         onerror="this.src='images/shopingcart.png';">
                    <?php if ($discount_pct > 0): ?>
                    <span class="absolute -top-2 -right-2 discount-badge text-white text-[9px] font-extrabold px-1.5 py-0.5 rounded-full shadow-sm">
                      -<?php echo $discount_pct; ?>%
                    </span>
                    <?php endif; ?>
                  </div>
                  <div class="min-w-0">
                    <h3 class="text-sm font-bold tracking-tight truncate text-slate-800"><?php echo htmlspecialchars($row['Name']); ?></h3>
                    <p class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase"><?php echo htmlspecialchars($row['Category']); ?></p>
                    <span class="inline-block mt-1 text-[10px] bg-emerald-50 border border-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md font-extrabold uppercase tracking-wide">
                      <?php echo htmlspecialchars($row['Weight']); ?>
                    </span>
                  </div>
                </div>

                <!-- Quantity Controls -->
                <div class="flex items-center justify-center md:col-span-2">
                  <form action="cart.php" method="POST" class="flex items-center">
                    <input type="hidden" name="cart_id" value="<?php echo $row['cart_row_id']; ?>">
                    <input type="hidden" name="update_qty" value="1">

                    <button type="button" onclick="changeQty(this, -1)" 
                            class="flex items-center justify-center w-8 h-8 border rounded-l-lg qty-btn text-slate-400 border-slate-200 hover:bg-slate-50 hover:text-slate-600">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <input type="number" name="quantity" value="<?php echo $quantity; ?>" min="1" max="99"
                           class="w-10 h-8 text-sm font-bold text-center border-t border-b outline-none text-slate-700 border-slate-200"
                           onchange="this.form.submit()">
                    <button type="button" onclick="changeQty(this, 1)"
                            class="flex items-center justify-center w-8 h-8 border rounded-r-lg qty-btn text-slate-400 border-slate-200 hover:bg-slate-50 hover:text-slate-600">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                  </form>
                </div>

                <!-- Price -->
                <div class="text-right md:col-span-3">
                  <?php if ($discount_pct > 0): ?>
                    <div class="font-sans text-base font-black text-orange-600 md:text-lg">
                      ¥<?php echo number_format($item_total); ?>
                    </div>
                    <div class="text-xs text-slate-400">
                      <span class="line-through">¥<?php echo number_format($item_original_total); ?></span>
                      <span class="ml-1 font-semibold text-emerald-600">Save ¥<?php echo number_format($item_savings); ?></span>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">
                      ¥<?php echo number_format($discounted_price); ?> each
                    </div>
                  <?php else: ?>
                    <div class="font-sans text-base font-black text-slate-800 md:text-lg">
                      ¥<?php echo number_format($item_total); ?>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">
                      ¥<?php echo number_format($original_price); ?> each
                    </div>
                  <?php endif; ?>
                </div>

                <!-- Remove -->
                <div class="flex justify-end md:col-span-1">
                  <a href="cart.php?remove=<?php echo $row['cart_row_id']; ?>" 
                     onclick="return confirm('Remove this item from your cart?')"
                     class="p-2 transition-colors rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50"
                     title="Remove item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </a>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>

        <!-- Cart Summary -->
        <div class="px-8 py-6 border-t border-slate-100 bg-slate-50/50">
          <div class="space-y-3">
            <div class="flex items-center justify-between text-sm">
              <span class="text-slate-500">Subtotal (<?php echo $item_count; ?> item<?php echo $item_count > 1 ? 's' : ''; ?>)</span>
              <span class="font-semibold text-slate-700">¥<?php echo number_format($grand_total + $total_savings); ?></span>
            </div>
            <?php if ($total_savings > 0): ?>
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium text-emerald-600">Discount Savings</span>
              <span class="font-bold text-emerald-600">-¥<?php echo number_format($total_savings); ?></span>
            </div>
            <?php endif; ?>
            <div class="flex items-center justify-between pt-3 border-t border-slate-200">
              <span class="text-sm font-bold tracking-wider uppercase text-slate-800">Order Total</span>
              <span class="text-2xl font-black text-emerald-950">¥<?php echo number_format($grand_total); ?></span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-col gap-3 mt-6 sm:flex-row">
            <a href="index.php" class="flex-1 px-6 py-3.5 text-xs font-bold tracking-widest text-center uppercase transition-colors border-2 rounded-xl border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50">
              ← Continue Shopping
            </a>
            <a href="checkout.php" class="flex-[2] px-6 py-3.5 text-xs font-bold tracking-widest text-center text-white uppercase transition-all bg-orange-500 shadow-lg rounded-xl hover:bg-orange-600 hover:shadow-orange-500/30 hover:-translate-y-0.5">
              Proceed to Checkout →
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <!-- Footer -->
  <footer class="mt-auto py-6 border-t border-emerald-900 bg-emerald-950 text-center text-[10px] text-emerald-400/50 tracking-wider">
    © 2026 Fresh Mart. All rights reserved.
  </footer>

  <script>
    // Auto-hide toast notifications
    setTimeout(() => {
      document.querySelectorAll('.toast-enter').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translate(-50%, -20px)';
        el.style.transition = 'all 0.3s ease';
        setTimeout(() => el.remove(), 300);
      });
    }, 2500);

    // Quantity controls
    function changeQty(btn, delta) {
      const form = btn.closest('form');
      const input = form.querySelector('input[name="quantity"]');
      let val = parseInt(input.value) || 1;
      val = Math.max(1, Math.min(99, val + delta));
      input.value = val;
      form.submit();
    }

    // Animate cart items on page load
    document.querySelectorAll('.cart-item').forEach((item, i) => {
      item.style.opacity = '0';
      item.style.transform = 'translateY(10px)';
      setTimeout(() => {
        item.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
        item.style.opacity = '1';
        item.style.transform = 'translateY(0)';
      }, i * 80);
    });
  </script>

</body>
</html>