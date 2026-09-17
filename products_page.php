<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if db.php exists and loaded successfully
if (!file_exists('db.php')) {
    die('<div style="padding:20px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:8px;font-family:sans-serif;"><strong>Error:</strong> db.php not found. Please create the database connection file.</div>');
}

include 'db.php';

// Check if database connection is established
if (!isset($conn) || !$conn) {
    die('<div style="padding:20px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:8px;font-family:sans-serif;"><strong>Error:</strong> Database connection failed. Check db.php configuration.</div>');
}

session_start();

$category_name = isset($_GET['cat']) ? mysqli_real_escape_string($conn, $_GET['cat']) : 'General';

// Handle search
$search_query = '';
$is_searching = false;
$search_error = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = mysqli_real_escape_string($conn, trim($_GET['search']));
    $is_searching = true;
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'featured';
$order_by = '';
switch ($sort) {
    case 'price_low':
        $order_by = 'ORDER BY Price ASC';
        break;
    case 'price_high':
        $order_by = 'ORDER BY Price DESC';
        break;
    case 'rating':
        $order_by = 'ORDER BY RAND()';
        break;
    case 'featured':
    default:
        $order_by = 'ORDER BY Id DESC';
        break;
}

// Check which columns exist
$has_stock_col = false;
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM Product LIKE 'in_stock'");
if ($check_col && mysqli_num_rows($check_col) > 0) {
    $has_stock_col = true;
}

$has_discount_col = false;
$check_discount = mysqli_query($conn, "SHOW COLUMNS FROM Product LIKE 'discount'");
if ($check_discount && mysqli_num_rows($check_discount) > 0) {
    $has_discount_col = true;
}

// Build query
if ($is_searching) {
    $query = "SELECT * FROM Product WHERE Name LIKE '%$search_query%' OR Category LIKE '%$search_query%' $order_by";
} else {
    $query = "SELECT * FROM Product WHERE Category = '$category_name' $order_by";
}

$result = mysqli_query($conn, $query);
if (!$result) {
    $search_error = 'Query failed: ' . mysqli_error($conn);
}

// Get cart count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $count_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id";
    $count_res = mysqli_query($conn, $count_query);
    if ($count_res) {
        $count_row = mysqli_fetch_assoc($count_res);
        $cart_count = $count_row['total'] ? (int)$count_row['total'] : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $is_searching ? 'Search Results' : htmlspecialchars($category_name); ?> - Fresh Mart</title>
  <!-- Compiled Tailwind CSS (CLI) -->
  <link rel="stylesheet" href="dist/output.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="font-sans antialiased bg-gray-50">

  <!-- Top Bar -->
  <div class="py-2.5 text-xs text-gray-300 bg-slate-900 relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-primary-900/20 via-transparent to-accent-900/20"></div>
    <div class="relative z-10 flex items-center justify-between px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="flex items-center gap-4">
        <span class="flex items-center gap-1.5">
          <i class="fas fa-truck-fast text-primary-400"></i>
          <span class="font-medium">Free delivery on orders over $50</span>
        </span>
        <span class="items-center hidden gap-1.5 sm:flex">
          <i class="fas fa-phone text-accent-400"></i>
          <span class="font-medium">090-1234-1532</span>
        </span>
      </div>
      <div class="flex items-center gap-4">
        <a href="#" class="font-medium transition-colors hover:text-white underline-anim">Track Order</a>
        <a href="#" class="font-medium transition-colors hover:text-white underline-anim">Help Center</a>
      </div>
    </div>
  </div>

  <!-- Header -->
  <header class="sticky top-0 z-50 border-b glass border-gray-200/50">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 lg:h-20">
        <a href="index.php" class="flex items-center gap-2.5 group">
          <div class="flex items-center justify-center w-10 h-10 transition-shadow transition-transform shadow-lg bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl shadow-primary-500/30 group-hover:shadow-primary-500/50 group-hover:scale-105">
            <i class="text-lg text-white fas fa-leaf"></i>
          </div>
          <div>
            <h1 class="text-xl font-bold leading-tight font-display lg:text-2xl gradient-text">Fresh Mart</h1>
            <p class="text-[10px] text-gray-400 -mt-0.5 tracking-widest uppercase font-medium">Premium Groceries</p>
          </div>
        </a>

        <!-- Desktop Search -->
        <form action="products_page.php" method="GET" class="flex-1 hidden max-w-xl mx-8 md:flex lg:mx-12">
          <div class="w-full flex items-center bg-gray-100 rounded-full px-4 py-2.5 transition-all duration-300 border border-transparent focus-within:border-primary-300 focus-within:bg-white focus-within:shadow-md focus-within:shadow-primary-500/10 search-glow">
            <i class="mr-3 text-gray-400 fas fa-search"></i>
            <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search for fresh products..." class="w-full text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none">
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-1.5 rounded-full text-sm font-medium transition-all duration-300 btn-press">Search</button>
          </div>
        </form>

        <!-- Right Actions -->
        <div class="flex items-center gap-3 lg:gap-5">
          <a href="index.php" class="items-center hidden gap-2 text-gray-600 transition-colors sm:flex hover:text-primary-600 group">
            <div class="flex items-center justify-center transition-colors bg-gray-100 rounded-full w-9 h-9 group-hover:bg-primary-50">
              <i class="text-sm fas fa-home"></i>
            </div>
            <span class="hidden text-sm font-medium lg:inline">Home</span>
          </a>
          <a href="profile.php" class="items-center hidden gap-2 text-gray-600 transition-colors sm:flex hover:text-primary-600 group">
            <div class="flex items-center justify-center transition-colors bg-gray-100 rounded-full w-9 h-9 group-hover:bg-primary-50">
              <i class="text-sm fas fa-user"></i>
            </div>
            <span class="hidden text-sm font-medium lg:inline">Account</span>
          </a>
          <a href="cart.php" class="relative flex items-center gap-2 text-gray-600 transition-colors hover:text-primary-600 group">
            <div class="relative flex items-center justify-center transition-colors bg-gray-100 rounded-full w-9 h-9 group-hover:bg-primary-50">
              <i class="text-sm fas fa-shopping-bag"></i>
              <span id="cart-count" class="absolute -top-1 -right-1 w-5 h-5 bg-accent-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center"><?php echo $cart_count; ?></span>
            </div>
            <span class="hidden text-sm font-medium lg:inline">Cart</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Category Hero Banner -->
  <section class="relative overflow-hidden bg-gradient-to-br from-primary-700 via-primary-600 to-emerald-700 hero-pattern">
    <div class="absolute inset-0 bg-black/10"></div>
    <div class="absolute w-64 h-64 top-5 right-20 bg-white/5 blob animate-float"></div>
    <div class="absolute w-40 h-40 bottom-5 left-20 bg-white/5 blob animate-float-delayed" style="animation-delay: 2.5s; border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;"></div>

    <div class="relative z-10 px-4 py-16 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-20 lg:py-24">
      <div class="text-center">
        <nav class="flex items-center justify-center gap-2 mb-6 text-sm text-primary-200">
          <a href="index.php" class="transition-colors hover:text-white underline-anim">Home</a>
          <i class="text-xs fas fa-chevron-right text-primary-300"></i>
          <span class="font-medium text-white"><?php echo $is_searching ? 'Search Results' : htmlspecialchars($category_name); ?></span>
        </nav>
        <h1 class="mb-4 text-4xl font-bold text-white font-display sm:text-5xl lg:text-6xl">
          <?php echo $is_searching ? 'Search Results' : ucfirst(htmlspecialchars($category_name)); ?>
        </h1>
        <p class="max-w-xl mx-auto text-lg text-primary-100">
          <?php if ($is_searching) { ?>
            Found <?php echo $result ? mysqli_num_rows($result) : 0; ?> result(s) for "<?php echo htmlspecialchars($search_query); ?>"
          <?php } else { ?>
            Discover our handpicked selection of premium <?php echo strtolower(htmlspecialchars($category_name)); ?> sourced from the finest local farms.
          <?php } ?>
        </p>
        <?php if ($search_error) { ?>
        <div class="inline-block p-3 mt-4 text-sm text-red-100 border bg-red-500/20 border-red-400/30 rounded-xl">
          <i class="mr-2 fas fa-exclamation-circle"></i><?php echo htmlspecialchars($search_error); ?>
        </div>
        <?php } ?>
        <div class="flex items-center justify-center gap-6 mt-8 text-sm text-primary-200">
          <div class="flex items-center gap-2">
            <i class="fas fa-check-circle text-accent-400"></i>
            <span>100% Organic</span>
          </div>
          <div class="flex items-center gap-2">
            <i class="fas fa-check-circle text-accent-400"></i>
            <span>Farm Fresh</span>
          </div>
          <div class="flex items-center gap-2">
            <i class="fas fa-check-circle text-accent-400"></i>
            <span>Same Day Delivery</span>
          </div>
        </div>
      </div>
    </div>

    <div class="absolute bottom-0 left-0 right-0">
      <svg viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-12 sm:h-16">
        <path d="M0 80L60 72C120 64 240 48 360 40C480 32 600 32 720 36C840 40 960 48 1080 52C1200 56 1320 56 1380 56L1440 56V80H1380C1320 80 1200 80 1080 80C960 80 840 80 720 80C600 80 480 80 360 80C240 80 120 80 60 80H0Z" fill="#f9fafb"/>
      </svg>
    </div>
  </section>

  <!-- Products Grid -->
  <main class="flex-grow pt-8 pb-16">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <!-- Mobile Search Bar -->
      <form action="products_page.php" method="GET" class="flex mb-6 md:hidden">
        <div class="flex items-center w-full px-4 py-3 transition-all duration-300 bg-white border border-gray-200 shadow-sm rounded-2xl search-glow focus-within:border-primary-400 focus-within:shadow-lg">
          <i class="mr-3 text-lg text-gray-400 fas fa-search"></i>
          <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search products..." class="w-full text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none">
          <button type="submit" class="flex items-center justify-center w-10 h-10 ml-2 text-white transition-all duration-300 shadow-md bg-primary-600 rounded-xl btn-press shadow-primary-500/20">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>

      <!-- Section Header -->
      <div class="flex flex-col gap-4 mb-8 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <span class="inline-block mb-1 text-sm font-semibold tracking-wider uppercase text-primary-600">Browse Products</span>
          <h2 class="text-2xl font-bold text-gray-900 font-display sm:text-3xl">
            <?php if ($is_searching) { echo 'Search Results'; } else { echo ucfirst(htmlspecialchars($category_name)) . ' Collection'; } ?>
          </h2>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-500">
            <?php echo $result ? mysqli_num_rows($result) : 0; ?> product(s)
          </span>
          <div class="w-px h-6 bg-gray-300"></div>
          <form action="products_page.php" method="GET" id="sortForm">
            <?php if ($is_searching) { ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
            <?php } else { ?>
            <input type="hidden" name="cat" value="<?php echo htmlspecialchars($category_name); ?>">
            <?php } ?>
            <select name="sort" onchange="document.getElementById('sortForm').submit();" class="sort-select px-4 py-2.5 text-sm text-gray-700 transition-all duration-300 bg-white border border-gray-200 rounded-xl outline-none cursor-pointer focus:border-primary-400 focus:ring-2 focus:ring-primary-400/20 hover:border-gray-300 shadow-sm">
              <option value="featured" <?php if ($sort === 'featured') echo 'selected'; ?>>Sort by: Featured</option>
              <option value="price_low" <?php if ($sort === 'price_low') echo 'selected'; ?>>Price: Low to High</option>
              <option value="price_high" <?php if ($sort === 'price_high') echo 'selected'; ?>>Price: High to Low</option>
              <option value="rating" <?php if ($sort === 'rating') echo 'selected'; ?>>Best Rated</option>
            </select>
          </form>
        </div>
      </div>

      <!-- Product Cards Grid -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 sm:gap-6">
        <?php
        $has_products = false;
        if ($result) {
            while($row = mysqli_fetch_assoc($result)) {
              $is_in_stock = true;
              if ($has_stock_col && isset($row['in_stock'])) {
                  $is_in_stock = (int)$row['in_stock'] === 1;
              }
              $stock_overlay_class = $is_in_stock ? '' : 'opacity-60 grayscale';
              $has_products = true;
        ?>
        <div class="flex flex-col overflow-hidden bg-white border border-gray-100 product-card group rounded-2xl card-hover reveal">
          <div class="relative overflow-hidden bg-gray-50 aspect-square <?php echo $stock_overlay_class; ?>">
            <img src="<?php echo htmlspecialchars($row['Image']); ?>"
                 alt="<?php echo htmlspecialchars($row['Name']); ?>"
                 class="object-cover w-full h-full product-img"
                 onerror="this.src='images/shopingcart.png';">

            <?php if (!$is_in_stock) { ?>
            <div class="absolute inset-0 flex items-center justify-center bg-black/50">
              <span class="px-4 py-2 text-xs font-bold tracking-wider text-white uppercase bg-red-500 rounded-full">Out of Stock</span>
            </div>
            <?php } else { ?>
            <div class="absolute inset-0 flex items-center justify-center transition-opacity duration-300 opacity-0 bg-black/40 group-hover:opacity-100">
              <button type="button"
                      onclick="document.getElementById('form-<?php echo $row['Id']; ?>').submit();"
                      class="bg-white text-gray-800 px-6 py-2.5 rounded-full font-semibold text-sm hover:bg-primary-50 transition-all duration-300 btn-press transform translate-y-4 group-hover:translate-y-0">
                <i class="mr-2 fas fa-cart-plus"></i>Add to Cart
              </button>
            </div>
            <?php } ?>

            <button class="absolute flex items-center justify-center w-8 h-8 text-gray-400 transition-all duration-300 transform translate-x-2 rounded-full shadow-sm opacity-0 top-3 right-3 bg-white/90 backdrop-blur-sm hover:text-rose-500 group-hover:opacity-100 group-hover:translate-x-0">
              <i class="text-sm fas fa-heart"></i>
            </button>

            <div class="absolute bottom-3 left-3">
              <span class="bg-white/90 backdrop-blur-sm text-gray-700 text-xs font-semibold px-2.5 py-1 rounded-lg shadow-sm">
                <?php echo htmlspecialchars($row['Weight']); ?>
              </span>
            </div>
            <?php if (!$is_in_stock) { ?>
            <div class="absolute top-3 left-3">
              <span class="bg-red-500/90 backdrop-blur-sm text-white text-[10px] font-bold px-2.5 py-1 rounded-lg shadow-sm uppercase tracking-wider">Out of Stock</span>
            </div>
            <?php } ?>
          </div>

          <div class="flex flex-col flex-grow p-4">
            <h3 class="mb-1 text-sm font-semibold text-gray-800 transition-colors line-clamp-2 group-hover:text-primary-600">
              <?php echo htmlspecialchars($row['Name']); ?>
            </h3>

            <div class="flex items-center gap-1 mb-3">
              <div class="flex text-accent-400 text-[10px]">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star-half-alt"></i>
              </div>
              <span class="text-[10px] text-gray-400">(<?php echo rand(50, 500); ?>)</span>
            </div>

            <?php
            $original_price = (float)$row['Price'];
            $discount_val = 0;
            if ($has_discount_col) {
                if (isset($row['discount'])) $discount_val = (int)$row['discount'];
                elseif (isset($row['Discount'])) $discount_val = (int)$row['Discount'];
                elseif (isset($row['DISCOUNT'])) $discount_val = (int)$row['DISCOUNT'];
            }
            $discount_pct = $discount_val > 0 ? $discount_val : 0;
            $sale_price = $discount_pct > 0 ? $original_price * (1 - $discount_pct/100) : $original_price;
            ?>
            <div class="mb-2">
              <div class="flex items-baseline gap-2">
                <span class="text-lg font-bold text-gray-900">¥<?php echo number_format($sale_price, 2); ?></span>
                <?php if ($discount_pct > 0) { ?>
                  <span class="text-xs text-gray-400 line-through">¥<?php echo number_format($original_price, 2); ?></span>
                <?php } ?>
              </div>
              <?php if ($discount_pct > 0) { ?>
                <span class="inline-block mt-1 text-[10px] font-bold text-rose-500 bg-rose-50 px-1.5 py-0.5 rounded">-<?php echo $discount_pct; ?>% OFF</span>
              <?php } ?>
            </div>

            <form action="add_to_cart.php" method="POST" id="form-<?php echo $row['Id']; ?>" class="mt-auto" <?php if(!$is_in_stock) echo 'style="pointer-events:none;opacity:0.5;"'; ?>>
              <input type="hidden" name="product_id" value="<?php echo $row['Id']; ?>">
              <input type="hidden" name="weight" value="<?php echo htmlspecialchars($row['Weight']); ?>">
              <input type="hidden" name="price" value="<?php echo htmlspecialchars($row['Price']); ?>">

              <div class="flex items-center gap-2">
                <div class="flex items-center flex-1 overflow-hidden border border-gray-200 rounded-lg">
                  <button type="button"
                          onclick="decrementQty(this)"
                          class="flex items-center justify-center w-8 text-xs text-gray-500 transition-colors h-9 hover:bg-gray-50">
                    <i class="fas fa-minus"></i>
                  </button>
                  <input type="number"
                         name="quantity"
                         value="1"
                         min="1"
                         max="99"
                         class="w-full text-sm font-semibold text-center text-gray-700 outline-none quantity-input h-9"
                         onchange="validateQty(this)">
                  <button type="button"
                          onclick="incrementQty(this)"
                          class="flex items-center justify-center w-8 text-xs text-gray-500 transition-colors h-9 hover:bg-gray-50">
                    <i class="fas fa-plus"></i>
                  </button>
                </div>
                <button type="submit"
                        class="flex items-center justify-center w-10 text-white transition-all duration-300 rounded-lg shadow-lg h-9 bg-primary-600 hover:bg-primary-700 btn-press shadow-primary-500/20">
                  <i class="text-sm fas fa-cart-plus"></i>
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php
            }
        }
        ?>

        <?php if (!$has_products) { ?>
        <div class="flex flex-col items-center justify-center py-20 col-span-full">
          <div class="flex items-center justify-center w-24 h-24 mb-4 bg-gray-100 rounded-full">
            <i class="text-3xl text-gray-400 fas fa-box-open"></i>
          </div>
          <h3 class="mb-2 text-xl font-bold text-gray-700 font-display">No Products Found</h3>
          <p class="mb-6 text-sm text-gray-500">
            <?php if ($is_searching) { echo 'No products match your search.'; } else { echo 'We could not find any products in this category.'; } ?>
          </p>
          <a href="index.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-full font-semibold text-sm transition-all duration-300 btn-press inline-flex items-center gap-2 shadow-lg shadow-primary-500/20">
            <i class="text-xs fas fa-arrow-left"></i> Back to Home
          </a>
        </div>
        <?php } ?>
      </div>
    </div>
  </main>

  <!-- Features Strip -->
  <section class="py-10 bg-white border-t border-gray-100">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 gap-6 md:grid-cols-4">
        <div class="flex items-center gap-4 p-4 transition-colors rounded-2xl hover:bg-gray-50">
          <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-primary-50 rounded-xl">
            <i class="text-lg fas fa-truck text-primary-600"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Free Delivery</h3>
            <p class="text-xs text-gray-500">On orders $50+</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 transition-colors rounded-2xl hover:bg-gray-50">
          <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-accent-50 rounded-xl">
            <i class="text-lg fas fa-award text-accent-600"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Premium Quality</h3>
            <p class="text-xs text-gray-500">Certified organic</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 transition-colors rounded-2xl hover:bg-gray-50">
          <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-blue-50 rounded-xl">
            <i class="text-lg text-blue-600 fas fa-rotate-left"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Easy Returns</h3>
            <p class="text-xs text-gray-500">30-day policy</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 transition-colors rounded-2xl hover:bg-gray-50">
          <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-rose-50 rounded-xl">
            <i class="text-lg fas fa-headset text-rose-600"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-800">24/7 Support</h3>
            <p class="text-xs text-gray-500">Always here</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="text-gray-400 bg-slate-900">
    <div class="px-4 py-16 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 gap-8 md:grid-cols-4 lg:grid-cols-5 lg:gap-12">
        <div class="col-span-2 mb-4 md:col-span-4 lg:col-span-1 lg:mb-0">
          <a href="index.php" class="flex items-center gap-2.5 mb-4">
            <div class="flex items-center justify-center rounded-lg w-9 h-9 bg-gradient-to-br from-primary-500 to-primary-700">
              <i class="text-sm text-white fas fa-leaf"></i>
            </div>
            <h3 class="text-lg font-bold leading-tight text-white font-display">Fresh Mart</h3>
          </a>
          <p class="max-w-xs mb-4 text-sm text-gray-500">Your trusted partner for fresh, organic, and premium quality groceries delivered to your doorstep.</p>
          <div class="flex gap-3">
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white"><i class="text-sm fab fa-facebook-f"></i></a>
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white"><i class="text-sm fab fa-instagram"></i></a>
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white"><i class="text-sm fab fa-twitter"></i></a>
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white"><i class="text-sm fab fa-youtube"></i></a>
          </div>
        </div>
        <div>
          <h4 class="mb-4 text-sm font-semibold tracking-wider text-white uppercase">About Us</h4>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Our Story</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Careers</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Why Choose Us</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Sustainability</a></li>
          </ul>
        </div>
        <div>
          <h4 class="mb-4 text-sm font-semibold tracking-wider text-white uppercase">Customer Care</h4>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Help Center</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Track Order</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Returns & Refunds</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Shipping Info</a></li>
          </ul>
        </div>
        <div>
          <h4 class="mb-4 text-sm font-semibold tracking-wider text-white uppercase">Shop</h4>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">All Products</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Today's Deals</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">New Arrivals</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-anim">Best Sellers</a></li>
          </ul>
        </div>
        <div>
          <h4 class="mb-4 text-sm font-semibold tracking-wider text-white uppercase">Contact</h4>
          <ul class="space-y-3">
            <li class="flex items-center gap-2 text-sm"><i class="text-xs fas fa-envelope text-primary-500"></i><span>support@freshmart.com</span></li>
            <li class="flex items-center gap-2 text-sm"><i class="text-xs fas fa-phone text-primary-500"></i><span>090-1234-1532</span></li>
            <li class="flex items-start gap-2 text-sm"><i class="mt-1 text-xs fas fa-clock text-primary-500"></i><span>Mon-Sun: 8AM - 10PM</span></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="border-t border-white/5">
      <div class="flex flex-col items-center justify-between gap-4 px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:flex-row">
        <p class="text-xs text-gray-500">&copy; 2026 Fresh Mart. All rights reserved.</p>
        <div class="flex items-center gap-6">
          <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300">Privacy Policy</a>
          <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300">Terms of Service</a>
          <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300">Cookie Policy</a>
        </div>
      </div>
    </div>
  </footer>

  <?php if (isset($_GET['status']) && $_GET['status'] === 'success') { ?>
  <div id="toastAlert" class="fixed z-50 flex items-center max-w-sm gap-3 px-6 py-4 font-semibold text-white shadow-2xl top-24 right-5 bg-emerald-600 rounded-2xl" style="animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-full bg-white/20">
      <i class="text-sm fas fa-check"></i>
    </div>
    <div>
      <p class="text-sm font-bold">Added to Cart!</p>
      <p class="text-xs text-emerald-100">Item added to your basket successfully.</p>
    </div>
    <button onclick="dismissToast()" class="ml-2 transition-colors text-white/70 hover:text-white">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <script>
    function dismissToast() {
      const toast = document.getElementById('toastAlert');
      if (toast) {
        toast.style.animation = 'fadeOut 0.4s ease forwards';
        setTimeout(() => toast.remove(), 400);
      }
    }
    setTimeout(dismissToast, 4000);
    if (window.history.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.delete('status');
      window.history.replaceState({ path: url.href }, '', url.href);
    }
  </script>
  <?php } ?>

  <script>
    function incrementQty(btn) {
      const input = btn.parentElement.querySelector('input[type="number"]');
      let val = parseInt(input.value) || 1;
      if (val < 99) input.value = val + 1;
    }
    function decrementQty(btn) {
      const input = btn.parentElement.querySelector('input[type="number"]');
      let val = parseInt(input.value) || 1;
      if (val > 1) input.value = val - 1;
    }
    function validateQty(input) {
      let val = parseInt(input.value) || 1;
      if (val < 1) input.value = 1;
      if (val > 99) input.value = 99;
    }

    const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
  </script>
</body>
</html>