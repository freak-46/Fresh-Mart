<?php 
// 1. Initialize the session machine at the very top
session_start(); 
include 'db.php';

$is_logged_in = isset($_SESSION['user_name']); 
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';

// Redirect if not logged in
if (!$is_logged_in) {
    header("Location: signin.php");
    exit();
}

// Fetch specific customer data
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'Not Provided';
$cart_count = 0;

// Get cart count
$count_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = " . intval($user_id);
$count_res = mysqli_query($conn, $count_query);
if ($count_res) {
    $count_row = mysqli_fetch_assoc($count_res);
    $cart_count = $count_row['total'] ? (int)$count_row['total'] : 0;
}

// ========== ORDERS TAB: Fetch real orders from DB ==========
$orders = [];
$orders_query = "SELECT * FROM orders WHERE user_id = " . intval($user_id) . " ORDER BY created_at DESC";
$orders_res = mysqli_query($conn, $orders_query);
if ($orders_res) {
    while ($row = mysqli_fetch_assoc($orders_res)) {
        $order_id = $row['id'];
        // Fetch order items
        $items_query = "SELECT * FROM order_items WHERE order_id = " . intval($order_id);
        $items_res = mysqli_query($conn, $items_query);
        $items = [];
        $total_items = 0;
        if ($items_res) {
            while ($item = mysqli_fetch_assoc($items_res)) {
                $items[] = $item;
                $total_items += (int)$item['quantity'];
            }
        }
        $row['items'] = $items;
        $row['total_items'] = $total_items;
        $orders[] = $row;
    }
}

// ========== ADDRESS TAB: Handle form submission ==========
$address_msg = '';
$address_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_address') {
        $address_type = mysqli_real_escape_string($conn, $_POST['address_type'] ?? 'Home');
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? $user_name);
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
        $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code'] ?? '');
        $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (empty($address) || empty($postal_code)) {
            $address_error = "Address and postal code are required.";
        } else {
            // If setting as default, unset other defaults
            if ($is_default) {
                mysqli_query($conn, "UPDATE addresses SET is_default = 0 WHERE user_id = " . intval($user_id));
            }
            $insert_sql = "INSERT INTO addresses (user_id, address_type, full_name, phone, postal_code, address, is_default) 
                           VALUES (" . intval($user_id) . ", '$address_type', '$full_name', '$phone', '$postal_code', '$address', $is_default)";
            if (mysqli_query($conn, $insert_sql)) {
                $address_msg = "Address added successfully!";
            } else {
                $address_error = "Error adding address: " . mysqli_error($conn);
            }
        }
    }

    if ($_POST['action'] === 'delete_address') {
        $addr_id = intval($_POST['address_id']);
        $del_sql = "DELETE FROM addresses WHERE id = $addr_id AND user_id = " . intval($user_id);
        if (mysqli_query($conn, $del_sql)) {
            $address_msg = "Address deleted successfully!";
        } else {
            $address_error = "Error deleting address.";
        }
    }

    if ($_POST['action'] === 'set_default_address') {
        $addr_id = intval($_POST['address_id']);
        mysqli_query($conn, "UPDATE addresses SET is_default = 0 WHERE user_id = " . intval($user_id));
        $upd_sql = "UPDATE addresses SET is_default = 1 WHERE id = $addr_id AND user_id = " . intval($user_id);
        if (mysqli_query($conn, $upd_sql)) {
            $address_msg = "Default address updated!";
        }
    }
}

// Fetch addresses
$addresses = [];
$addr_query = "SELECT * FROM addresses WHERE user_id = " . intval($user_id) . " ORDER BY is_default DESC, id DESC";
$addr_res = mysqli_query($conn, $addr_query);
if ($addr_res) {
    while ($row = mysqli_fetch_assoc($addr_res)) {
        $addresses[] = $row;
    }
}

// ========== SETTINGS TAB: Handle profile update ==========
$settings_msg = '';
$settings_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_name = mysqli_real_escape_string($conn, $_POST['user_name'] ?? $user_name);
    $new_email = mysqli_real_escape_string($conn, $_POST['email'] ?? $user_email);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    // Update basic info
    $upd_sql = "UPDATE users SET user_name = '$new_name', email = '$new_email' WHERE id = " . intval($user_id);
    if (mysqli_query($conn, $upd_sql)) {
        $_SESSION['user_name'] = $new_name;
        $_SESSION['email'] = $new_email;
        $user_name = $new_name;
        $user_email = $new_email;
        $settings_msg = "Profile updated successfully!";
    } else {
        $settings_error = "Error updating profile: " . mysqli_error($conn);
    }

    // Update password if provided
    if (!empty($current_password) && !empty($new_password)) {
        $check_sql = "SELECT password FROM users WHERE id = " . intval($user_id);
        $check_res = mysqli_query($conn, $check_sql);
        $user_row = mysqli_fetch_assoc($check_res);

        // Check password (assuming plain text or hashed - adjust as needed)
        if ($user_row && ($user_row['password'] === $current_password || password_verify($current_password, $user_row['password']))) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pass_sql = "UPDATE users SET password = '$hashed' WHERE id = " . intval($user_id);
            if (mysqli_query($conn, $pass_sql)) {
                $settings_msg .= " Password updated!";
            }
        } else {
            $settings_error = "Current password is incorrect.";
        }
    }
}

// Helper function for status styling
function getStatusStyle($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'delivered':
            return ['bg-green-50 text-green-700', 'fa-check', 'bg-green-50 rounded-xl'];
        case 'shipped':
        case 'out for delivery':
            return ['bg-amber-50 text-amber-700', 'fa-truck', 'bg-amber-50 rounded-xl'];
        case 'processing':
            return ['bg-blue-50 text-blue-700', 'fa-cog', 'bg-blue-50 rounded-xl'];
        case 'cancelled':
            return ['bg-rose-50 text-rose-700', 'fa-times', 'bg-rose-50 rounded-xl'];
        default:
            return ['bg-gray-50 text-gray-700', 'fa-clock', 'bg-gray-50 rounded-xl'];
    }
}

function getStatusProgress($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'delivered': return 100;
        case 'shipped':
        case 'out for delivery': return 75;
        case 'processing': return 50;
        case 'confirmed': return 25;
        default: return 10;
    }
}

function formatDate($dateStr) {
    if (empty($dateStr)) return 'N/A';
    $dt = new DateTime($dateStr);
    return $dt->format('F j, Y');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - Fresh Mart</title>
    <link rel="stylesheet" href="dist/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .tab-active { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #a7f3d0; }
        .input-focus:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1); }
        .badge-pulse { animation: pulse-badge 2s infinite; }
        @keyframes pulse-badge { 0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,0.4); } 50% { box-shadow: 0 0 0 6px rgba(245,158,11,0); } }
        .float-anim { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-20px); } }
        .blob { border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; }
        .glass { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); }
        .gradient-text { background: linear-gradient(135deg, #059669 0%, #10b981 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-pattern { background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.15) 1px, transparent 0); background-size: 20px 20px; }
        .btn-press:active { transform: scale(0.98); }
        .underline-anim { position: relative; }
        .underline-anim::after { content: ''; position: absolute; width: 0; height: 1px; bottom: -2px; left: 0; background: currentColor; transition: width 0.3s; }
        .underline-anim:hover::after { width: 100%; }
    </style>
</head>
<body class="flex flex-col min-h-screen font-sans antialiased bg-gray-50">

    <!-- Top Bar -->
    <div class="py-2 text-xs text-gray-300 bg-slate-900">
        <div class="flex items-center justify-between px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1"><i class="fas fa-truck-fast text-emerald-400"></i> Free delivery on orders over $50</span>
                <span class="items-center hidden gap-1 sm:flex"><i class="fas fa-phone text-emerald-400"></i> 090-1234-1532</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="#" class="transition-colors hover:text-white underline-anim">Track Order</a>
                <a href="#" class="transition-colors hover:text-white underline-anim">Help Center</a>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-50 border-b glass border-gray-200/50">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                <a href="index.php" class="flex items-center gap-2.5 group">
                    <div class="flex items-center justify-center w-10 h-10 transition-shadow shadow-lg bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl shadow-emerald-500/30 group-hover:shadow-emerald-500/50">
                        <i class="text-lg text-white fas fa-leaf"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold leading-tight font-display lg:text-2xl gradient-text">Fresh Mart</h1>
                        <p class="text-[10px] text-gray-400 -mt-0.5 tracking-widest uppercase font-medium">Premium Groceries</p>
                    </div>
                </a>

                <div class="flex items-center gap-3 lg:gap-5">
                    <a href="index.php" class="items-center hidden gap-2 text-gray-600 transition-colors sm:flex hover:text-emerald-600 group">
                        <div class="flex items-center justify-center transition-colors bg-gray-100 rounded-full w-9 h-9 group-hover:bg-emerald-50">
                            <i class="text-sm fas fa-home"></i>
                        </div>
                        <span class="hidden text-sm font-medium lg:inline">Home</span>
                    </a>
                    <a href="cart.php" class="relative flex items-center gap-2 text-gray-600 transition-colors hover:text-emerald-600 group">
                        <div class="relative flex items-center justify-center transition-colors bg-gray-100 rounded-full w-9 h-9 group-hover:bg-emerald-50">
                            <i class="text-sm fas fa-shopping-bag"></i>
                            <?php if ($cart_count > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center badge-pulse"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="hidden text-sm font-medium lg:inline">Cart</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">

        <!-- Dashboard Hero -->
        <section class="relative overflow-hidden bg-gradient-to-br from-slate-800 via-slate-900 to-emerald-900">
            <div class="absolute inset-0 hero-pattern opacity-20"></div>
            <div class="absolute top-0 right-0 rounded-full w-96 h-96 bg-emerald-600/10 blur-3xl"></div>
            <div class="absolute bottom-0 left-0 rounded-full w-72 h-72 bg-amber-500/10 blur-3xl"></div>

            <div class="relative z-10 px-4 py-12 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-16">
                <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center w-16 h-16 shadow-lg bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl shadow-emerald-500/30">
                            <span class="text-2xl font-bold text-white"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-widest bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Verified Customer</span>
                            </div>
                            <h1 class="text-2xl font-bold text-white font-display sm:text-3xl">Hello, <?php echo htmlspecialchars($user_name); ?>!</h1>
                            <p class="text-sm text-gray-400 mt-0.5"><?php echo htmlspecialchars($user_email); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="index.php" class="px-5 py-2.5 rounded-xl text-xs font-bold text-gray-300 bg-white/5 border border-white/10 hover:bg-white/10 hover:text-white transition-all duration-300 inline-flex items-center gap-2">
                            <i class="text-xs fas fa-store"></i> Return Storefront
                        </a>
                        <a href="logout.php" class="px-5 py-2.5 rounded-xl text-xs font-bold text-rose-400 bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 transition-all duration-300 inline-flex items-center gap-2">
                            <i class="text-xs fas fa-sign-out-alt"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Portal Content -->
        <div class="px-4 py-10 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 lg:flex-row">

                <!-- Sidebar Navigation -->
                <nav class="w-full lg:w-72 shrink-0">
                    <div class="p-2 bg-white border border-gray-100 shadow-sm rounded-2xl">
                        <p class="px-4 pt-4 pb-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Account Hub</p>

                        <a href="profile.php?tab=orders" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-300 mb-1 <?php echo $active_tab === 'orders' ? 'tab-active text-emerald-700' : 'text-gray-500 hover:bg-gray-50'; ?>">
                            <div class="w-9 h-9 rounded-lg <?php echo $active_tab === 'orders' ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-400'; ?> flex items-center justify-center transition-colors">
                                <i class="text-xs fas fa-box"></i>
                            </div>
                            <div>
                                <span class="block">My Orders</span>
                                <span class="text-[10px] text-gray-400 font-normal">Track & manage</span>
                            </div>
                            <?php if ($active_tab === 'orders'): ?>
                            <i class="ml-auto text-xs fas fa-chevron-right text-emerald-400"></i>
                            <?php endif; ?>
                        </a>

                        <a href="profile.php?tab=address" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-300 mb-1 <?php echo $active_tab === 'address' ? 'tab-active text-emerald-700' : 'text-gray-500 hover:bg-gray-50'; ?>">
                            <div class="w-9 h-9 rounded-lg <?php echo $active_tab === 'address' ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-400'; ?> flex items-center justify-center transition-colors">
                                <i class="text-xs fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <span class="block">Addresses</span>
                                <span class="text-[10px] text-gray-400 font-normal">Delivery locations</span>
                            </div>
                            <?php if ($active_tab === 'address'): ?>
                            <i class="ml-auto text-xs fas fa-chevron-right text-emerald-400"></i>
                            <?php endif; ?>
                        </a>

                        <a href="profile.php?tab=settings" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-300 <?php echo $active_tab === 'settings' ? 'tab-active text-emerald-700' : 'text-gray-500 hover:bg-gray-50'; ?>">
                            <div class="w-9 h-9 rounded-lg <?php echo $active_tab === 'settings' ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-400'; ?> flex items-center justify-center transition-colors">
                                <i class="text-xs fas fa-cog"></i>
                            </div>
                            <div>
                                <span class="block">Settings</span>
                                <span class="text-[10px] text-gray-400 font-normal">Profile & security</span>
                            </div>
                            <?php if ($active_tab === 'settings'): ?>
                            <i class="ml-auto text-xs fas fa-chevron-right text-emerald-400"></i>
                            <?php endif; ?>
                        </a>
                    </div>

                    <!-- Quick Stats Card -->
                    <div class="p-5 mt-4 text-white shadow-lg bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-2xl shadow-emerald-500/20">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-xl">
                                <i class="text-sm fas fa-shopping-bag"></i>
                            </div>
                            <div>
                                <p class="text-xs text-emerald-100">Cart Items</p>
                                <p class="text-xl font-bold"><?php echo $cart_count; ?></p>
                            </div>
                        </div>
                        <a href="cart.php" class="inline-flex items-center gap-1 text-xs font-semibold transition-colors text-white/80 hover:text-white">
                            View Cart <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </nav>

                <!-- Main Dashboard Area -->
                <div class="flex-1">

                    <!-- ORDERS TAB -->
                    <?php if ($active_tab === 'orders'): ?>
                    <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl sm:p-8">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 font-display sm:text-2xl">My Orders</h2>
                                <p class="mt-1 text-sm text-gray-400">Track your deliveries and view order history</p>
                            </div>
                            <div class="flex items-center justify-center w-12 h-12 bg-emerald-50 rounded-xl">
                                <i class="fas fa-box text-emerald-600"></i>
                            </div>
                        </div>

                        <?php if (empty($orders)): ?>
                        <!-- No Orders State -->
                        <div class="py-16 text-center">
                            <div class="flex items-center justify-center w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full">
                                <i class="text-2xl text-gray-400 fas fa-box-open"></i>
                            </div>
                            <h3 class="mb-2 text-lg font-semibold text-gray-700">No orders yet</h3>
                            <p class="mb-6 text-sm text-gray-400">Start shopping to see your orders here.</p>
                            <a href="index.php" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white transition-all bg-emerald-600 rounded-xl hover:bg-emerald-700">
                                <i class="fas fa-store"></i> Browse Products
                            </a>
                        </div>
                        <?php else: ?>

                            <?php 
                            // Separate active and past orders
                            $active_orders = [];
                            $past_orders = [];
                            foreach ($orders as $order) {
                                $status = strtolower($order['status'] ?? '');
                                if (in_array($status, ['confirmed', 'processing', 'shipped', 'out for delivery'])) {
                                    $active_orders[] = $order;
                                } else {
                                    $past_orders[] = $order;
                                }
                            }
                            ?>

                            <!-- Active Orders -->
                            <?php foreach ($active_orders as $order): 
                                $style = getStatusStyle($order['status']);
                                $progress = getStatusProgress($order['status']);
                                $items_text = [];
                                foreach ($order['items'] as $item) {
                                    $items_text[] = ($item['product_name'] ?? 'Item') . ' x' . ($item['quantity'] ?? 1);
                                }
                                $items_display = implode(', ', array_slice($items_text, 0, 2));
                                if (count($items_text) > 2) $items_display .= '...';
                            ?>
                            <div class="mb-6 overflow-hidden border border-gray-100 rounded-2xl card-hover">
                                <div class="flex items-center justify-between px-5 py-3 border-b bg-gradient-to-r from-amber-50 to-orange-50 border-amber-100/50">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span class="text-xs font-bold tracking-wider uppercase text-amber-700"><?php echo htmlspecialchars($order['status'] ?? 'Processing'); ?></span>
                                    </div>
                                    <span class="text-[10px] text-amber-600 font-medium">Order #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></span>
                                </div>
                                <div class="p-5">
                                    <div class="flex flex-col gap-4 mb-5 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-xs font-medium tracking-wider text-gray-400 uppercase">Order ID</p>
                                            <p class="font-mono text-sm font-bold text-gray-900">#<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></p>
                                        </div>
                                        <div class="text-left sm:text-right">
                                            <p class="text-xs font-medium tracking-wider text-gray-400 uppercase">Date</p>
                                            <p class="text-sm font-semibold text-gray-700"><?php echo formatDate($order['created_at']); ?></p>
                                        </div>
                                        <div class="text-left sm:text-right">
                                            <p class="text-xs font-medium tracking-wider text-gray-400 uppercase">Total</p>
                                            <p class="text-lg font-bold text-gray-900">$<?php echo number_format($order['total_amount'] ?? 0, 2); ?></p>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="mb-5">
                                        <div class="flex items-center justify-between text-[10px] text-gray-400 mb-2">
                                            <span class="font-semibold <?php echo $progress >= 25 ? 'text-emerald-600' : ''; ?>">Confirmed</span>
                                            <span class="font-semibold <?php echo $progress >= 50 ? 'text-emerald-600' : ''; ?>">Processing</span>
                                            <span class="font-semibold <?php echo $progress >= 75 ? 'text-emerald-600' : ''; ?>">Shipped</span>
                                            <span class="font-semibold <?php echo $progress >= 100 ? 'text-emerald-600' : ''; ?>">Delivered</span>
                                        </div>
                                        <div class="h-2 overflow-hidden bg-gray-100 rounded-full">
                                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-1000" style="width: <?php echo $progress; ?>%;"></div>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                                        <div class="flex items-center justify-center w-12 h-12 bg-white rounded-lg shadow-sm shrink-0">
                                            <i class="text-lg fas fa-carrot text-emerald-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($items_display); ?></p>
                                            <p class="text-xs text-gray-400 mt-0.5"><?php echo $order['total_items']; ?> items total</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Past Orders -->
                            <?php if (!empty($past_orders)): ?>
                            <h3 class="mb-4 text-sm font-bold tracking-wider text-gray-400 uppercase">Order History</h3>
                            <div class="space-y-3">
                                <?php foreach ($past_orders as $order): 
                                    $style = getStatusStyle($order['status']);
                                ?>
                                <div class="flex items-center gap-4 p-4 transition-colors border border-gray-100 rounded-xl hover:border-gray-200">
                                    <div class="flex items-center justify-center w-12 h-12 <?php echo $style[2]; ?> shrink-0">
                                        <i class="text-sm <?php echo $style[1] . ' ' . explode(' ', $style[0])[1]; ?>"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <p class="text-sm font-bold text-gray-900">#<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></p>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $style[0]; ?>"><?php echo htmlspecialchars($order['status'] ?? 'Pending'); ?></span>
                                        </div>
                                        <p class="text-xs text-gray-400"><?php echo formatDate($order['created_at']); ?> · <?php echo $order['total_items']; ?> items · $<?php echo number_format($order['total_amount'] ?? 0, 2); ?></p>
                                    </div>
                                    <button class="text-gray-400 transition-colors hover:text-emerald-600" onclick="alert('Order details feature coming soon!')">
                                        <i class="text-sm fas fa-chevron-right"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- ADDRESS TAB -->
                    <?php if ($active_tab === 'address'): ?>
                    <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl sm:p-8">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 font-display sm:text-2xl">Delivery Addresses</h2>
                                <p class="mt-1 text-sm text-gray-400">Manage where your groceries get delivered</p>
                            </div>
                            <div class="flex items-center justify-center w-12 h-12 bg-emerald-50 rounded-xl">
                                <i class="fas fa-map-marker-alt text-emerald-600"></i>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if ($address_msg): ?>
                        <div class="p-4 mb-6 text-sm font-medium text-emerald-700 border border-emerald-200 rounded-xl bg-emerald-50">
                            <i class="fas fa-check-circle mr-2"></i><?php echo $address_msg; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($address_error): ?>
                        <div class="p-4 mb-6 text-sm font-medium text-rose-700 border border-rose-200 rounded-xl bg-rose-50">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $address_error; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Saved Addresses -->
                        <?php if (!empty($addresses)): ?>
                            <?php foreach ($addresses as $addr): ?>
                            <div class="relative p-5 mb-4 border-2 <?php echo $addr['is_default'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-gray-100 bg-white'; ?> rounded-2xl transition-all hover:border-gray-200">
                                <div class="absolute flex gap-2 top-4 right-4">
                                    <?php if (!$addr['is_default']): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="set_default_address">
                                        <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                        <button type="submit" class="flex items-center justify-center w-8 h-8 text-gray-400 transition-colors bg-white rounded-lg shadow-sm hover:text-emerald-600" title="Set as default">
                                            <i class="text-xs fas fa-star"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this address?');">
                                        <input type="hidden" name="action" value="delete_address">
                                        <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                        <button type="submit" class="flex items-center justify-center w-8 h-8 text-gray-400 transition-colors bg-white rounded-lg shadow-sm hover:text-rose-500">
                                            <i class="text-xs fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="text-sm fas fa-home text-emerald-600"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($addr['address_type'] ?? 'Home'); ?></p>
                                            <?php if ($addr['is_default']): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm leading-relaxed text-gray-600"><?php echo nl2br(htmlspecialchars($addr['address'] ?? '')); ?></p>
                                        <p class="mt-1 text-xs text-gray-400"><?php echo htmlspecialchars($addr['postal_code'] ?? ''); ?> · <?php echo htmlspecialchars($addr['phone'] ?? ''); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="py-8 mb-6 text-center border border-dashed border-gray-200 rounded-2xl">
                            <i class="mb-2 text-2xl text-gray-300 fas fa-map-marker-alt"></i>
                            <p class="text-sm text-gray-400">No saved addresses yet.</p>
                        </div>
                        <?php endif; ?>

                        <!-- Add New Address Form -->
                        <div class="p-6 border border-gray-100 rounded-2xl">
                            <h3 class="flex items-center gap-2 mb-5 text-sm font-bold text-gray-900">
                                <i class="text-xs fas fa-plus-circle text-emerald-500"></i> Add New Address
                            </h3>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add_address">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block mb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Address Type</label>
                                        <select name="address_type" class="w-full px-4 text-sm font-medium transition-all border border-gray-200 outline-none h-11 rounded-xl input-focus bg-gray-50/50">
                                            <option value="Home">Home</option>
                                            <option value="Work">Work</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block mb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Full Name</label>
                                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user_name); ?>" class="w-full px-4 text-sm font-medium transition-all border border-gray-200 outline-none h-11 rounded-xl input-focus bg-gray-50/50">
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block mb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Phone</label>
                                        <input type="text" name="phone" placeholder="090-1234-1532" class="w-full px-4 text-sm font-medium transition-all border border-gray-200 outline-none h-11 rounded-xl input-focus bg-gray-50/50">
                                    </div>
                                    <div>
                                        <label class="block mb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Postal / ZIP Code</label>
                                        <input type="text" name="postal_code" placeholder="e.g., 816-0811" class="w-full px-4 text-sm font-medium transition-all border border-gray-200 outline-none h-11 rounded-xl input-focus bg-gray-50/50" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="block mb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Street Address</label>
                                    <textarea name="address" rows="2" placeholder="e.g., 1-1 Kasuga-koen, Kasuga City, Fukuoka" class="w-full px-4 py-3 text-sm font-medium transition-all border border-gray-200 outline-none rounded-xl input-focus bg-gray-50/50 resize-none" required></textarea>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="is_default" id="is_default" class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <label for="is_default" class="text-sm text-gray-600">Set as default address</label>
                                </div>
                                <div class="pt-2">
                                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white transition-all duration-300 shadow-lg bg-emerald-600 rounded-xl hover:bg-emerald-700 btn-press shadow-emerald-500/20">
                                        <i class="text-xs fas fa-save"></i> Save Address
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SETTINGS TAB -->
                    <?php if ($active_tab === 'settings'): ?>
                    <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl sm:p-8">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 font-display sm:text-2xl">Account Settings</h2>
                                <p class="mt-1 text-sm text-gray-400">Manage your profile and security preferences</p>
                            </div>
                            <div class="flex items-center justify-center w-12 h-12 bg-emerald-50 rounded-xl">
                                <i class="fas fa-cog text-emerald-600"></i>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if ($settings_msg): ?>
                        <div class="p-4 mb-6 text-sm font-medium text-emerald-700 border border-emerald-200 rounded-xl bg-emerald-50">
                            <i class="fas fa-check-circle mr-2"></i><?php echo $settings_msg; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($settings_error): ?>
                        <div class="p-4 mb-6 text-sm font-medium text-rose-700 border border-rose-200 rounded-xl bg-rose-50">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $settings_error; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <!-- Profile Info Card -->
                            <div class="p-6 mb-6 border border-gray-100 rounded-2xl">
                                <h3 class="flex items-center gap-2 mb-5 text-sm font-bold text-gray-900">
                                    <i class="text-xs fas fa-id-card text-emerald-500"></i> Profile Information
                                </h3>
                                <div class="space-y-4">
                                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                                        <div class="flex items-center justify-center w-12 h-12 shadow-md bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl">
                                            <span class="text-lg font-bold text-white"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Username</label>
                                            <input type="text" name="user_name" value="<?php echo htmlspecialchars($user_name); ?>" class="w-full px-3 py-2 text-sm font-bold text-gray-900 transition-all border border-gray-200 outline-none rounded-lg input-focus bg-white">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                                        <div class="flex items-center justify-center w-12 h-12 bg-blue-50 rounded-xl">
                                            <i class="text-sm text-blue-500 fas fa-envelope"></i>
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Email Address</label>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" class="w-full px-3 py-2 text-sm font-semibold text-gray-700 transition-all border border-gray-200 outline-none rounded-lg input-focus bg-white">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                                        <div class="flex items-center justify-center w-12 h-12 bg-purple-50 rounded-xl">
                                            <i class="text-sm text-purple-500 fas fa-shield-alt"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Account Status</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                                <p class="text-sm font-semibold text-gray-700">Active & Verified</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Section -->
                            <div class="p-6 mb-6 border border-gray-100 rounded-2xl">
                                <h3 class="flex items-center gap-2 mb-5 text-sm font-bold text-gray-900">
                                    <i class="text-xs fas fa-lock text-emerald-500"></i> Change Password
                                </h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block mb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Current Password</label>
                                        <input type="password" name="current_password" placeholder="Enter current password" class="w-full px-4 text-sm font-medium transition-all border border-gray-200 outline-none h-11 rounded-xl input-focus bg-gray-50/50">
                                    </div>
                                    <div>
                                        <label class="block mb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">New Password</label>
                                        <input type="password" name="new_password" placeholder="Enter new password" class="w-full px-4 text-sm font-medium transition-all border border-gray-200 outline-none h-11 rounded-xl input-focus bg-gray-50/50">
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-gray-400">Leave password fields empty if you don't want to change it.</p>
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white transition-all duration-300 shadow-lg bg-emerald-600 rounded-xl hover:bg-emerald-700 btn-press shadow-emerald-500/20">
                                    <i class="text-xs fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="mt-auto text-gray-400 bg-slate-900">
        <div class="px-4 py-12 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                <div class="flex flex-col gap-2">
                    <h3 class="mb-1 text-sm font-bold tracking-wide text-white uppercase">About Us</h3>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">Our Story</a>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">Careers</a>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">Why Choose Us</a>
                </div>
                <div class="flex flex-col gap-2">
                    <h3 class="mb-1 text-sm font-bold tracking-wide text-white uppercase">Customer Care</h3>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">Help Center</a>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">Track Order</a>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">Returns</a>
                </div>
                <div class="flex flex-col gap-2">
                    <h3 class="mb-1 text-sm font-bold tracking-wide text-white uppercase">Shop</h3>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">All Products</a>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">Deals</a>
                    <a href="#" class="text-xs text-gray-400 transition-colors hover:text-emerald-400 underline-anim w-fit">New Arrivals</a>
                </div>
                <div class="flex flex-col gap-2">
                    <h3 class="mb-1 text-sm font-bold tracking-wide text-white uppercase">Contact</h3>
                    <span class="text-xs text-gray-400">support@freshmart.com</span>
                    <span class="text-xs text-gray-400">090-1234-1532</span>
                    <span class="text-xs text-gray-400">Mon-Sun: 8AM - 10PM</span>
                </div>
            </div>
        </div>
        <div class="border-t border-white/5">
            <div class="flex flex-col items-center justify-between gap-3 px-4 py-5 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:flex-row">
                <p class="text-xs text-gray-500">&copy; 2026 Fresh Mart. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300">Privacy Policy</a>
                    <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>