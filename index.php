<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if db.php exists and loaded successfully
if (!file_exists('db.php')) {
    die('<div style="padding:20px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:8px;font-family:sans-serif;"><strong>Error:</strong> db.php not found. Please create the database connection file.</div>');
}

include 'db.php';

// Check if database connection is established
if (!isset($conn) || !$conn) {
    die('<div style="padding:20px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:8px;font-family:sans-serif;"><strong>Error:</strong> Database connection failed. Check db.php configuration.</div>');
}

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $count_query = "SELECT SUM(Quantity) AS total_items FROM cart WHERE user_id = $user_id";
    $count_result = mysqli_query($conn, $count_query);
    if ($count_result) {
        $count_data = mysqli_fetch_assoc($count_result);
        if ($count_data && $count_data['total_items'] !== null) {
            $cart_count = (int)$count_data['total_items'];
        }
    }
}

// Get actual product counts per category
$category_counts = array();
$cat_query = "SELECT Category, COUNT(*) as count FROM Product GROUP BY Category";
$cat_result = mysqli_query($conn, $cat_query);
if ($cat_result) {
    while ($cat_row = mysqli_fetch_assoc($cat_result)) {
        $category_counts[$cat_row['Category']] = (int)$cat_row['count'];
    }
}

// Check which columns exist in Product table
$has_description = false;
$check_desc = mysqli_query($conn, "SHOW COLUMNS FROM Product LIKE 'Description'");
if ($check_desc && mysqli_num_rows($check_desc) > 0) {
    $has_description = true;
}

// Handle search
$search_results = array();
$search_query = '';
$is_searching = false;
$search_error = '';

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = mysqli_real_escape_string($conn, trim($_GET['search']));
    $is_searching = true;
    
    // Build search query based on available columns
    $search_sql = "SELECT * FROM Product WHERE Name LIKE '%$search_query%' OR Category LIKE '%$search_query%'";
    if ($has_description) {
        $search_sql .= " OR Description LIKE '%$search_query%'";
    }
    $search_result = mysqli_query($conn, $search_sql);
    if ($search_result) {
        while ($row = mysqli_fetch_assoc($search_result)) {
            $search_results[] = $row;
        }
    } else {
        $search_error = 'Search query failed: ' . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fresh Mart - Premium Groceries</title>
  
  <!-- Compiled Tailwind CSS File -->
  <link rel="stylesheet" href="dist/output.css">
  
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="font-sans antialiased bg-gray-50">

  <!-- Top Bar -->
  <div class="py-2.5 text-xs text-gray-300 bg-slate-900 relative overflow-hidden">
    <div class="absolute inset-0 bg-linear-to-r from-primary-900/20 via-transparent to-accent-900/20"></div>
    <div class="relative z-10 flex items-center justify-between px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="flex items-center gap-4">
        <span class="flex items-center gap-1.5 animate-fade-in">
          <i class="fas fa-truck-fast text-primary-400 animate-bounce-soft"></i>
          <span class="font-medium">Free delivery on orders over $50</span>
        </span>
        <span class="items-center hidden gap-1.5 sm:flex animate-fade-in" style="animation-delay: 0.2s">
          <i class="fas fa-phone text-accent-400"></i>
          <span class="font-medium">090-1234-1532</span>
        </span>
      </div>
      <div class="flex items-center gap-4">
        <a href="#" class="font-medium transition-colors hover:text-white underline-grow">Track Order</a>
        <a href="#" class="font-medium transition-colors hover:text-white underline-grow">Help Center</a>
      </div>
    </div>
  </div>

  <!-- Header -->
  <header class="sticky top-0 z-50 border-b border-gray-200/50 glass">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 lg:h-20">
        <a href="index.php" class="flex items-center gap-2.5 group shrink-0">
          <div class="flex items-center justify-center w-10 h-10 transition-all duration-500 shadow-lg bg-linear-to-br from-primary-500 to-primary-700 rounded-xl group-hover:shadow-primary-500/40 group-hover:scale-110 group-hover:rotate-3">
            <i class="text-lg text-white fas fa-leaf" style="animation: spin 12s linear infinite;"></i>
          </div>
          <div class="hidden sm:block">
            <h1 class="text-xl font-bold leading-tight font-display lg:text-2xl gradient-text">Fresh Mart</h1>
            <p class="text-[10px] text-gray-400 -mt-0.5 tracking-widest uppercase font-medium">Premium Groceries</p>
          </div>
        </a>

        <!-- Desktop Search -->
        <form action="index.php" method="GET" class="flex-1 hidden max-w-xl mx-4 md:flex lg:mx-12">
          <div class="w-full flex items-center bg-gray-100 rounded-full px-4 py-2.5 border border-transparent transition-all duration-500 search-glow focus-within:border-primary-400 focus-within:bg-white">
            <i class="mr-3 text-gray-400 transition-colors fas fa-search"></i>
            <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search for fresh products..." class="w-full text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none">
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-1.5 rounded-full text-sm font-medium transition-all duration-300 btn-press shadow-lg shadow-primary-500/20 hover:shadow-primary-500/40">Search</button>
          </div>
        </form>

        <div class="flex items-center gap-2 sm:gap-3 lg:gap-5 shrink-0">
          <a href="profile.php" class="flex items-center gap-2 text-gray-600 transition-colors duration-300 hover:text-primary-600 group">
            <div class="flex items-center justify-center transition-all duration-300 bg-gray-100 rounded-full w-9 h-9 group-hover:bg-primary-50 group-hover:scale-110">
              <i class="text-sm transition-transform fas fa-user group-hover:scale-110"></i>
            </div>
            <span class="hidden text-sm font-medium lg:inline">Account</span>
          </a>
          <a href="cart.php" class="relative flex items-center gap-2 text-gray-600 transition-colors duration-300 hover:text-primary-600 group">
            <div class="relative flex items-center justify-center transition-all duration-300 bg-gray-100 rounded-full w-9 h-9 group-hover:bg-primary-50 group-hover:scale-110">
              <i class="text-sm transition-transform fas fa-shopping-bag group-hover:scale-110"></i>
              <span id="cart-count" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-accent-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center animate-pulse-slow"><?php echo $cart_count; ?></span>
            </div>
            <span class="hidden text-sm font-medium lg:inline">Cart</span>
          </a>
        </div>
      </div>
    </div>
  </header>

<?php if ($is_searching) { ?>
  <!-- Search Results Section -->
  <section class="py-12 bg-gray-50 min-h-[60vh]">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="mb-8">
        <a href="index.php" class="inline-flex items-center gap-2 mb-4 text-sm text-gray-500 transition-colors hover:text-primary-600">
          <i class="fas fa-arrow-left"></i> Back to Home
        </a>
        <h2 class="text-2xl font-bold text-gray-900 font-display sm:text-3xl">
          Search Results for "<?php echo htmlspecialchars($search_query); ?>"
        </h2>
        <p class="mt-2 text-gray-500"><?php echo count($search_results); ?> product(s) found</p>
        <?php if ($search_error) { ?>
        <div class="p-4 mt-4 text-sm text-red-700 border border-red-200 bg-red-50 rounded-xl">
          <i class="mr-2 fas fa-exclamation-circle"></i><?php echo htmlspecialchars($search_error); ?>
        </div>
        <?php } ?>
      </div>

      <?php if (count($search_results) > 0) { ?>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 sm:gap-6">
        <?php foreach ($search_results as $product) { ?>
        <div class="flex flex-col overflow-hidden transition-all bg-white border border-gray-100 group rounded-2xl duration-400 hover:shadow-xl hover:-translate-y-2">
          <div class="relative overflow-hidden bg-gray-50 aspect-square">
            <img src="<?php echo htmlspecialchars($product['Image']); ?>"
                 alt="<?php echo htmlspecialchars($product['Name']); ?>"
                 class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110"
                 onerror="this.src='images/shoppingcart.png';">
            <div class="absolute inset-0 flex items-center justify-center transition-opacity duration-300 opacity-0 bg-black/40 group-hover:opacity-100">
              <a href="products_page.php?cat=<?php echo urlencode($product['Category']); ?>"
                 class="px-5 py-2 text-sm font-semibold text-gray-800 transition-all duration-300 bg-white rounded-full hover:bg-primary-50 btn-press">
                View Category
              </a>
            </div>
            <div class="absolute top-3 left-3">
              <span class="bg-white/90 backdrop-blur-sm text-gray-700 text-xs font-semibold px-2.5 py-1 rounded-lg shadow-sm">
                <?php echo htmlspecialchars($product['Category']); ?>
              </span>
            </div>
          </div>
          <div class="flex flex-col flex-grow p-4">
            <h3 class="mb-1 text-sm font-semibold text-gray-800 transition-colors line-clamp-2 group-hover:text-primary-600">
              <?php echo htmlspecialchars($product['Name']); ?>
            </h3>
            <?php if ($has_description && isset($product['Description']) && !empty($product['Description'])) { ?>
            <p class="mb-2 text-xs text-gray-500 line-clamp-2"><?php echo htmlspecialchars($product['Description']); ?></p>
            <?php } ?>
            <div class="flex items-baseline gap-2 mt-auto">
              <span class="text-lg font-bold text-gray-900">¥<?php echo number_format($product['Price'], 2); ?></span>
              <?php if (isset($product['Weight']) && !empty($product['Weight'])) { ?>
              <span class="text-xs text-gray-400"><?php echo htmlspecialchars($product['Weight']); ?></span>
              <?php } ?>
            </div>
          </div>
        </div>
        <?php } ?>
      </div>
      <?php } else { ?>
      <div class="flex flex-col items-center justify-center py-20">
        <div class="flex items-center justify-center w-24 h-24 mb-6 bg-gray-100 rounded-full animate-bounce-soft">
          <i class="text-3xl text-gray-400 fas fa-search"></i>
        </div>
        <h3 class="mb-2 text-xl font-bold text-gray-700 font-display">No Products Found</h3>
        <p class="max-w-md mb-6 text-sm text-center text-gray-500">We could not find any products matching "<?php echo htmlspecialchars($search_query); ?>". Try different keywords or browse categories below.</p>
        <a href="index.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-full font-semibold text-sm transition-all duration-300 btn-press inline-flex items-center gap-2 shadow-lg shadow-primary-500/20">
          <i class="text-xs fas fa-arrow-left"></i> Back to Home
        </a>
      </div>
      <?php } ?>
    </div>
  </section>
<?php } else { ?>

  <!-- Hero Section -->
  <section class="relative overflow-hidden bg-linear-to-br from-primary-700 via-primary-600 to-emerald-700 hero-pattern min-h-[85vh] flex items-center">
    <div class="absolute inset-0 bg-black/10"></div>
    <div class="absolute top-20 right-20 w-80 h-80 bg-white/5 blob-shape animate-float"></div>
    <div class="absolute w-56 h-56 bottom-20 left-20 bg-white/5 blob-shape-2 animate-float-delayed"></div>
    <div class="absolute w-32 h-32 rounded-full top-1/2 left-1/3 bg-accent-400/10 blur-3xl animate-pulse-slow"></div>
    <div class="absolute w-48 h-48 rounded-full bottom-1/4 right-1/4 bg-primary-400/10 blur-3xl animate-pulse-slow" style="animation-delay: 1.5s"></div>
    <div class="absolute w-2 h-2 rounded-full top-32 left-1/4 bg-white/30 animate-float" style="animation-duration: 4s;"></div>
    <div class="absolute w-3 h-3 rounded-full top-1/3 right-1/3 bg-white/20 animate-float-delayed" style="animation-duration: 5s;"></div>
    <div class="absolute w-2 h-2 rounded-full bottom-1/3 left-1/2 bg-accent-300/40 animate-float-slow" style="animation-duration: 6s;"></div>

    <div class="relative z-10 px-4 py-16 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24 lg:py-32">
      <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12">
        <div class="text-white">
          <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-full px-4 py-1.5 mb-6 sm:mb-8 border border-white/20 animate-fade-up">
            <span class="w-2.5 h-2.5 rounded-full bg-accent-400 animate-pulse"></span>
            <span class="text-sm font-medium">New Seasonal Arrivals</span>
          </div>
          <h1 class="mb-6 text-4xl font-bold leading-tight font-display sm:text-5xl lg:text-7xl animate-fade-up" style="animation-delay: 0.1s">
            Fresh Groceries<br>
            <span class="relative inline-block text-primary-200">
              Delivered to You
              <svg class="absolute left-0 w-full -bottom-2" viewBox="0 0 300 12" fill="none">
                <path d="M2 8C50 2 100 2 150 6C200 10 250 10 298 4" stroke="rgba(255,255,255,0.4)" stroke-width="3" stroke-linecap="round"/>
              </svg>
            </span>
          </h1>
          <p class="max-w-lg mb-8 text-base leading-relaxed sm:text-xl text-primary-100 animate-fade-up" style="animation-delay: 0.2s">
            Farm-fresh produce, premium quality meats, and artisanal dairy delivered straight from local farms to your doorstep.
          </p>

          <!-- Mobile Search Bar -->
          <form action="index.php" method="GET" class="flex mb-8 md:hidden animate-fade-up" style="animation-delay: 0.3s">
            <div class="flex items-center w-full px-4 py-3 transition-all duration-500 border bg-white/20 backdrop-blur-md rounded-2xl border-white/30 focus-within:bg-white/30 search-glow">
              <i class="mr-3 text-lg text-white/70 fas fa-search"></i>
              <input type="text" name="search" placeholder="Search fresh products..." class="w-full text-sm text-white bg-transparent outline-none placeholder-white/60">
              <button type="submit" class="flex items-center justify-center w-10 h-10 ml-2 transition-all duration-300 bg-white shadow-lg text-primary-700 rounded-xl btn-press">
                <i class="fas fa-search"></i>
              </button>
            </div>
          </form>

          <div class="flex flex-wrap gap-3 sm:gap-4 animate-fade-up" style="animation-delay: 0.4s">
            <a href="#categories" class="bg-white text-primary-700 px-6 sm:px-8 py-3.5 sm:py-4 rounded-full font-semibold text-sm hover:bg-primary-50 transition-all duration-300 shadow-xl shadow-black/10 active:scale-[0.96] inline-flex items-center gap-2 group">
              Shop Now <i class="text-xs transition-transform fas fa-arrow-right group-hover:translate-x-1"></i>
            </a>
            <a href="#deals" class="bg-white/10 backdrop-blur-sm text-white border border-white/30 px-6 sm:px-8 py-3.5 sm:py-4 rounded-full font-semibold text-sm hover:bg-white/20 transition-all duration-300 active:scale-[0.96] hover:border-white/50">
              View Deals
            </a>
          </div>
          <div class="flex flex-wrap items-center gap-4 mt-10 text-sm sm:gap-6 sm:mt-12 text-primary-200 animate-fade-up" style="animation-delay: 0.5s">
            <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm px-3 py-1.5 rounded-full">
              <i class="fas fa-check-circle text-accent-400"></i>
              <span class="font-medium">Free Shipping</span>
            </div>
            <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm px-3 py-1.5 rounded-full">
              <i class="fas fa-check-circle text-accent-400"></i>
              <span class="font-medium">Fresh Guarantee</span>
            </div>
            <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm px-3 py-1.5 rounded-full">
              <i class="fas fa-check-circle text-accent-400"></i>
              <span class="font-medium">24/7 Support</span>
            </div>
          </div>
        </div>
        <div class="relative hidden lg:block animate-fade-up" style="animation-delay: 0.3s">
          <div class="relative">
            <div class="absolute -inset-6 bg-white/10 rounded-3xl blur-2xl animate-pulse-slow"></div>
            <div class="absolute -inset-2 bg-linear-to-br from-primary-400/20 to-accent-400/20 rounded-3xl blur-xl"></div>
            <img src="https://images.unsplash.com/photo-1542838132-92c53300491e?w=800&h=600&fit=crop" alt="Fresh groceries" class="relative rounded-3xl shadow-2xl shadow-black/20 w-full object-cover h-[500px] border border-white/10">
            <div class="absolute p-4 bg-white shadow-2xl -bottom-8 -left-8 rounded-2xl animate-float" style="animation-delay: 1s;">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 bg-linear-to-br from-primary-100 to-primary-200 rounded-xl">
                  <i class="text-xl fas fa-carrot text-primary-600" style="animation: wiggle 3s ease-in-out infinite;"></i>
                </div>
                <div>
                  <p class="text-sm font-bold text-gray-800">Organic Veggies</p>
                  <p class="text-xs text-gray-500">100% Fresh</p>
                </div>
              </div>
            </div>
            <div class="absolute p-4 bg-white shadow-2xl -top-6 -right-6 rounded-2xl animate-float-delayed" style="animation-delay: 2.5s;">
              <div class="flex items-center gap-2">
                <div class="flex -space-x-2">
                  <img src="https://i.pravatar.cc/100?img=1" class="w-8 h-8 border-2 border-white rounded-full" alt="">
                  <img src="https://i.pravatar.cc/100?img=2" class="w-8 h-8 border-2 border-white rounded-full" alt="">
                  <img src="https://i.pravatar.cc/100?img=3" class="w-8 h-8 border-2 border-white rounded-full" alt="">
                </div>
                <div>
                  <p class="text-sm font-bold text-gray-800">2k+ Happy</p>
                  <p class="text-xs text-gray-500">Customers</p>
                </div>
              </div>
            </div>
            <div class="absolute p-3 shadow-xl bg-white/95 backdrop-blur-sm top-1/2 -right-10 rounded-xl animate-float-slow" style="animation-delay: 3s;">
              <div class="flex items-center gap-2">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-accent-100">
                  <i class="text-lg fas fa-star text-accent-500"></i>
                </div>
                <div>
                  <p class="text-lg font-bold text-gray-800">4.9</p>
                  <p class="text-[10px] text-gray-500">Rating</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="absolute bottom-0 left-0 right-0">
      <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" class="w-full h-16 sm:h-20">
        <path d="M0 120L60 110C120 100 240 80 360 70C480 60 600 60 720 65C840 70 960 80 1080 85C1200 90 1320 90 1380 90L1440 90V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="#f9fafb"/>
      </svg>
    </div>
  </section>

  <!-- Features Strip -->
  <section class="py-12 bg-gray-50">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="flex items-center gap-4 p-5 transition-all duration-500 bg-white shadow-sm rounded-2xl hover:shadow-lg hover:-translate-y-1 reveal stagger-1">
          <div class="flex items-center justify-center w-12 h-12 shrink-0 bg-linear-to-br from-primary-50 to-primary-100 rounded-xl">
            <i class="text-lg fas fa-truck text-primary-600"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Free Delivery</h3>
            <p class="text-xs text-gray-500">On orders $50+</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-5 transition-all duration-500 bg-white shadow-sm rounded-2xl hover:shadow-lg hover:-translate-y-1 reveal stagger-2">
          <div class="flex items-center justify-center w-12 h-12 shrink-0 bg-linear-to-br from-accent-50 to-accent-100 rounded-xl">
            <i class="text-lg fas fa-award text-accent-600"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Premium Quality</h3>
            <p class="text-xs text-gray-500">Certified organic</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-5 transition-all duration-500 bg-white shadow-sm rounded-2xl hover:shadow-lg hover:-translate-y-1 reveal stagger-3">
          <div class="flex items-center justify-center w-12 h-12 shrink-0 bg-linear-to-br from-blue-50 to-blue-100 rounded-xl">
            <i class="text-lg text-blue-600 fas fa-rotate-left"></i>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-gray-800">Easy Returns</h3>
            <p class="text-xs text-gray-500">30-day policy</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-5 transition-all duration-500 bg-white shadow-sm rounded-2xl hover:shadow-lg hover:-translate-y-1 reveal stagger-4">
          <div class="flex items-center justify-center w-12 h-12 shrink-0 bg-linear-to-br from-rose-50 to-rose-100 rounded-xl">
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

  <!-- Categories Section -->
  <section id="categories" class="py-16 bg-gray-50">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="mb-12 text-center reveal">
        <span class="inline-block mb-2 text-sm font-semibold tracking-wider uppercase text-primary-600">Browse Collection</span>
        <h2 class="text-3xl font-bold text-gray-900 font-display sm:text-4xl lg:text-5xl">Shop by Category</h2>
        <p class="max-w-md mx-auto mt-3 text-gray-500">Explore our wide range of fresh categories handpicked for quality</p>
        <div class="w-16 h-1 mx-auto mt-4 rounded-full bg-linear-to-r from-primary-400 to-accent-400"></div>
      </div>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 lg:gap-6">
        <?php
        $categories = array(
          array('name' => 'Fruits', 'emoji' => '🍎', 'from' => 'from-orange-50', 'to' => 'to-amber-50', 'border' => 'border-orange-100/50'),
          array('name' => 'Vegetables', 'emoji' => '🥕', 'from' => 'from-green-50', 'to' => 'to-emerald-50', 'border' => 'border-green-100/50'),
          array('name' => 'Dairy', 'emoji' => '🥛', 'from' => 'from-blue-50', 'to' => 'to-indigo-50', 'border' => 'border-blue-100/50'),
          array('name' => 'Spices', 'emoji' => '🌶️', 'from' => 'from-yellow-50', 'to' => 'to-amber-50', 'border' => 'border-yellow-100/50'),
          array('name' => 'Meat', 'emoji' => '🥩', 'from' => 'from-red-50', 'to' => 'to-rose-50', 'border' => 'border-red-100/50'),
          array('name' => 'Beverages', 'emoji' => '🥤', 'from' => 'from-purple-50', 'to' => 'to-violet-50', 'border' => 'border-purple-100/50'),
          array('name' => 'Snacks', 'emoji' => '🍪', 'from' => 'from-pink-50', 'to' => 'to-rose-50', 'border' => 'border-pink-100/50'),
          array('name' => 'Frozen', 'emoji' => '❄️', 'from' => 'from-cyan-50', 'to' => 'to-sky-50', 'border' => 'border-cyan-100/50'),
        );
        foreach ($categories as $i => $cat) {
          $count = isset($category_counts[$cat['name']]) ? $category_counts[$cat['name']] : 0;
        ?>
        <a href="products_page.php?cat=<?php echo urlencode($cat['name']); ?>" class="group reveal stagger-<?php echo ($i % 4) + 1; ?>">
          <div class="category-card relative p-6 overflow-hidden text-center border bg-linear-to-br <?php echo $cat['from']; ?> <?php echo $cat['to']; ?> rounded-3xl transition-all duration-500 card-lift <?php echo $cat['border']; ?>">
            <div class="absolute top-0 right-0 w-24 h-24 -mt-8 -mr-8 transition-transform duration-500 rounded-full bg-current opacity-5 group-hover:scale-[2.5]"></div>
            <div class="relative z-10 mb-4 text-5xl transition-all duration-500 group-hover:scale-[1.3] group-hover:-rotate-[8deg] group-hover:drop-shadow-lg"><?php echo $cat['emoji']; ?></div>
            <h3 class="relative z-10 mb-1 font-bold text-gray-800 transition-colors group-hover:text-primary-700"><?php echo $cat['name']; ?></h3>
            <p class="relative z-10 text-xs font-medium text-gray-500"><?php echo $count; ?> item<?php echo $count !== 1 ? 's' : ''; ?></p>
            <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-0 h-0.5 bg-linear-to-r from-primary-400 to-accent-400 transition-all duration-500 group-hover:w-1/2 rounded-full"></div>
          </div>
        </a>
        <?php } ?>
      </div>
    </div>
  </section>

  <!-- Deals Banner Section -->
  <section id="deals" class="relative py-20 overflow-hidden bg-linear-to-br from-slate-850 via-slate-900 to-primary-900 hero-pattern">
    <div class="absolute top-0 right-0 rounded-full w-96 h-96 bg-primary-600/10 blur-3xl animate-pulse-slow"></div>
    <div class="absolute bottom-0 left-0 rounded-full w-72 h-72 bg-accent-500/10 blur-3xl animate-pulse-slow" style="animation-delay: 2s"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-linear-to-r from-primary-500/5 to-accent-500/5 rounded-full blur-3xl"></div>

    <div class="relative z-10 px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="grid items-center gap-12 lg:grid-cols-2">
        <div class="reveal">
          <div class="inline-flex items-center gap-2 bg-accent-500/20 backdrop-blur-sm rounded-full px-4 py-1.5 mb-6 border border-accent-500/30">
            <i class="fas fa-fire text-accent-400 animate-pulse"></i>
            <span class="text-sm font-semibold text-accent-300">Limited Time Offer</span>
          </div>
          <h2 class="mb-4 text-3xl font-bold text-white font-display sm:text-4xl lg:text-5xl">
            Fresh Deals<br>
            <span class="gradient-text-accent">Every Single Day</span>
          </h2>
          <p class="max-w-lg mb-8 leading-relaxed text-gray-400">
            Discover incredible savings on farm-fresh produce, premium meats, and organic dairy. New deals added daily — do not miss out!
          </p>
          <div class="flex flex-wrap gap-4 mb-8">
            <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-4 text-center min-w-[80px]">
              <p class="text-2xl font-bold text-white" id="countdown-h">24</p>
              <p class="text-xs text-gray-400">Hours</p>
            </div>
            <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-4 text-center min-w-[80px]">
              <p class="text-2xl font-bold text-white" id="countdown-m">60</p>
              <p class="text-xs text-gray-400">Minutes</p>
            </div>
            <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-4 text-center min-w-[80px]">
              <p class="text-2xl font-bold text-white" id="countdown-s">45</p>
              <p class="text-xs text-gray-400">Seconds</p>
            </div>
          </div>
          <a href="#categories" class="bg-linear-to-r from-accent-500 to-accent-600 hover:from-accent-600 hover:to-accent-700 text-white px-8 py-3.5 rounded-full font-semibold text-sm transition-all duration-300 btn-press inline-flex items-center gap-2 shadow-lg shadow-accent-500/30 hover:shadow-accent-500/50 group">
            Explore Deals <i class="transition-transform fas fa-arrow-right group-hover:translate-x-1"></i>
          </a>
        </div>
        <div class="relative hidden lg:block reveal stagger-2">
          <div class="relative">
            <div class="absolute -inset-4 bg-linear-to-r from-accent-500/20 to-primary-500/20 rounded-3xl blur-2xl animate-pulse-slow"></div>
            <img src="https://images.unsplash.com/photo-1606787366850-de6330128bfc?w=600&h=450&fit=crop" alt="Fresh deals" class="relative rounded-3xl shadow-2xl shadow-black/30 w-full object-cover h-[400px] border border-white/10">
            <div class="absolute p-5 bg-white shadow-2xl -bottom-6 -right-6 rounded-2xl animate-float" style="animation-delay: 1s">
              <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-14 h-14 bg-linear-to-br from-accent-100 to-accent-200 rounded-xl">
                  <i class="text-2xl fas fa-percent text-accent-600"></i>
                </div>
                <div>
                  <p class="text-2xl font-bold text-gray-800">Up to 50%</p>
                  <p class="text-sm text-gray-500">Off on selected items</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Newsletter -->
  <section class="relative py-20 overflow-hidden bg-linear-to-br from-primary-700 via-primary-600 to-emerald-700 hero-pattern">
    <div class="absolute top-0 right-0 rounded-full w-96 h-96 bg-white/5 blur-3xl animate-pulse-slow"></div>
    <div class="absolute bottom-0 left-0 rounded-full w-72 h-72 bg-accent-400/10 blur-3xl animate-pulse-slow" style="animation-delay: 1.5s"></div>
    <div class="relative z-10 max-w-4xl px-4 mx-auto text-center sm:px-6 lg:px-8">
      <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-full px-4 py-1.5 mb-6 border border-white/20 animate-fade-up">
        <i class="text-sm fas fa-envelope text-accent-300"></i>
        <span class="text-sm font-medium text-white/90">Join 10,000+ subscribers</span>
      </div>
      <h2 class="mb-4 text-3xl font-bold text-white font-display sm:text-4xl lg:text-5xl animate-fade-up" style="animation-delay: 0.1s">Get Fresh Deals in Your Inbox</h2>
      <p class="max-w-lg mx-auto mb-8 text-primary-100 animate-fade-up" style="animation-delay: 0.2s">Subscribe to our newsletter and get exclusive discounts, seasonal recipes, and fresh arrivals delivered to your inbox weekly.</p>
      <form class="flex flex-col max-w-md gap-3 mx-auto sm:flex-row animate-fade-up" style="animation-delay: 0.3s">
        <input type="email" placeholder="Enter your email" class="flex-1 px-5 py-3.5 rounded-full bg-white/15 border border-white/25 text-white placeholder-white/50 outline-none focus:border-accent-400 focus:bg-white/20 transition-all duration-300 text-sm backdrop-blur-sm">
        <button type="submit" class="bg-accent-500 hover:bg-accent-600 text-white px-8 py-3.5 rounded-full font-semibold text-sm transition-all duration-300 btn-press whitespace-nowrap shadow-lg shadow-accent-500/30 hover:shadow-accent-500/50">Subscribe</button>
      </form>
      <p class="mt-4 text-xs text-primary-200/70 animate-fade-up" style="animation-delay: 0.4s">No spam, unsubscribe anytime. We respect your privacy.</p>
    </div>
  </section>

<?php } ?>

  <!-- Footer -->
  <footer class="text-gray-400 bg-slate-900">
    <div class="px-4 py-16 mx-auto max-w-7xl sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 gap-8 md:grid-cols-4 lg:grid-cols-5 lg:gap-12">
        <div class="col-span-2 mb-4 md:col-span-4 lg:col-span-1 lg:mb-0">
          <a href="index.php" class="flex items-center gap-2.5 mb-4 group">
            <div class="flex items-center justify-center transition-transform rounded-lg w-9 h-9 bg-linear-to-br from-primary-500 to-primary-700 group-hover:scale-110 group-hover:rotate-3">
              <i class="text-sm text-white fas fa-leaf"></i>
            </div>
            <div><h3 class="text-lg font-bold leading-tight text-white font-display">Fresh Mart</h3></div>
          </a>
          <p class="max-w-xs mb-4 text-sm text-gray-500">Your trusted partner for fresh, organic, and premium quality groceries delivered to your doorstep.</p>
          <div class="flex gap-3">
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white hover:scale-110"><i class="text-sm fab fa-facebook-f"></i></a>
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white hover:scale-110"><i class="text-sm fab fa-instagram"></i></a>
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white hover:scale-110"><i class="text-sm fab fa-twitter"></i></a>
            <a href="#" class="flex items-center justify-center text-gray-400 transition-all duration-300 rounded-lg w-9 h-9 bg-white/5 hover:bg-primary-600 hover:text-white hover:scale-110"><i class="text-sm fab fa-youtube"></i></a>
          </div>
        </div>
        <div>
          <h4 class="mb-4 text-sm font-semibold tracking-wider text-white uppercase">About Us</h4>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Our Story</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Careers</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Why Choose Us</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Sustainability</a></li>
          </ul>
        </div>
        <div>
          <h4 class="mb-4 text-sm font-semibold tracking-wider text-white uppercase">Customer Care</h4>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Help Center</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Track Order</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Returns & Refunds</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Shipping Info</a></li>
          </ul>
        </div>
        <div>
          <h4 class="mb-4 text-sm font-semibold tracking-wider text-white uppercase">Shop</h4>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">All Products</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Today's Deals</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">New Arrivals</a></li>
            <li><a href="#" class="text-sm transition-colors hover:text-primary-400 underline-grow">Best Sellers</a></li>
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
          <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300 underline-grow">Privacy Policy</a>
          <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300 underline-grow">Terms of Service</a>
          <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300 underline-grow">Cookie Policy</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
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

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    (function() {
      let hours = 23, minutes = 59, seconds = 45;
      const hEl = document.getElementById('countdown-h');
      const mEl = document.getElementById('countdown-m');
      const sEl = document.getElementById('countdown-s');
      if (!hEl || !mEl || !sEl) return;
      setInterval(() => {
        seconds--;
        if (seconds < 0) { seconds = 59; minutes--; }
        if (minutes < 0) { minutes = 59; hours--; }
        if (hours < 0) { hours = 23; }
        hEl.textContent = String(hours).padStart(2, '0');
        mEl.textContent = String(minutes).padStart(2, '0');
        sEl.textContent = String(seconds).padStart(2, '0');
      }, 1000);
    })();
  </script>
</body>
</html>