<?php
$DEBUG_MODE = false;
if ($DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

session_start();

// ============================================================
// 1. Load DB connection safely
// ============================================================
$db_ok = true;
$db_error = "";

if (!file_exists(__DIR__ . '/db.php')) {
    $db_ok = false;
    $db_error = "db.php was not found in this folder.";
} else {
    include __DIR__ . '/db.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $db_ok = false;
        $db_error = "db.php did not create a valid $conn (mysqli) connection.";
    }
}

// ============================================================
// 2. Auth guard
// ============================================================
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: signin.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$allowed_tabs = ['overview', 'orders', 'add_product', 'catalog'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs) ? $_GET['tab'] : 'overview';

$msg = "";
$msg_type = "error";

// ============================================================
// HELPER: Detect Product table columns (flexible schema)
// ============================================================
$product_id_col = 'ProductID';
$product_name_col = 'Name';
$product_cat_col = 'Category';
$product_price_col = 'Price';
$product_weight_col = 'Weight';
$product_image_col = 'Image';
$has_in_stock = false;
$has_discount = false;

function safe_query($conn, $sql) {
    try {
        return $conn->query($sql);
    } catch (\mysqli_sql_exception $e) {
        return false;
    }
}

if ($db_ok) {
    $cols_res = safe_query($conn, "SHOW COLUMNS FROM Product");
    if ($cols_res) {
        while ($col = $cols_res->fetch_assoc()) {
            $cname = strtolower($col['Field']);
            if ($cname === 'id') $product_id_col = $col['Field'];
            if ($cname === 'productid') $product_id_col = $col['Field'];
            if ($cname === 'name') $product_name_col = $col['Field'];
            if ($cname === 'category') $product_cat_col = $col['Field'];
            if ($cname === 'price') $product_price_col = $col['Field'];
            if ($cname === 'weight') $product_weight_col = $col['Field'];
            if ($cname === 'image') $product_image_col = $col['Field'];
            if ($cname === 'in_stock') $has_in_stock = true;
            if ($cname === 'instock') { $has_in_stock = true; }
            if ($cname === 'discount') $has_discount = true;
            if ($cname === 'sale_price') $has_discount = true;
        }
    }
}

// ============================================================
// 3. Product upload handler
// ============================================================
if ($db_ok && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = "Invalid or expired form submission. Please try again.";
    } else {
        $product_name = trim($_POST['product_name'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $weight       = trim($_POST['weight'] ?? '');
        $price        = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $allowed_categories = ['Fruits', 'Vegetables', 'Dairy', 'Meat', 'Spices', 'Beverages', 'Snacks', 'Frozen'];

        if ($product_name === '') {
            $msg = "Product name is required.";
        } elseif (!in_array($category, $allowed_categories, true)) {
            $msg = "Please select a valid category.";
        } elseif ($price === false) {
            $msg = "Please enter a valid price (whole number, greater than 0).";
        } elseif ($weight === '') {
            $msg = "Weight / volume is required.";
        } elseif (!isset($_FILES['product_image']) || $_FILES['product_image']['error'] === UPLOAD_ERR_NO_FILE) {
            $msg = "Please select a product image.";
        } else {
            $image_file = $_FILES['product_image'];
            $file_ext = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
            $allowed_ext_mime = [
                'jpg'  => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png'  => ['image/png'],
                'webp' => ['image/webp'],
            ];

            if ($image_file['error'] !== UPLOAD_ERR_OK) {
                $msg = "Error uploading image file. Code: " . $image_file['error'];
            } elseif (!isset($allowed_ext_mime[$file_ext])) {
                $msg = "Invalid file type. Only JPG, JPEG, PNG, and WEBP files are allowed.";
            } elseif ($image_file['size'] > 5000000) {
                $msg = "File size is too large. Keep images under 5MB.";
            } elseif (!is_uploaded_file($image_file['tmp_name'])) {
                $msg = "Invalid upload.";
            } else {
                $image_info = @getimagesize($image_file['tmp_name']);
                if ($image_info === false || !in_array($image_info['mime'], $allowed_ext_mime[$file_ext], true)) {
                    $msg = "The uploaded file is not a valid image.";
                } else {
                    $upload_dir = __DIR__ . '/images/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0755, true);
                    }
                    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                        $msg = "System failure: the 'images/' folder is missing or not writable.";
                    } else {
                        $new_image_name = bin2hex(random_bytes(16)) . "." . $file_ext;
                        $upload_destination_physical = $upload_dir . $new_image_name;
                        $upload_destination_db = 'images/' . $new_image_name;

                        if (!move_uploaded_file($image_file['tmp_name'], $upload_destination_physical)) {
                            $msg = "System failure: could not save uploaded file.";
                        } else {
                            @chmod($upload_destination_physical, 0644);
                            $stock_col_sql = $has_in_stock ? ", in_stock" : "";
                            $stock_val_sql = $has_in_stock ? ", 1" : "";
                            $discount_col_sql = $has_discount ? ", discount" : "";
                            $discount_val_sql = $has_discount ? ", ?" : "";
                            $sql = "INSERT INTO Product (Name, Category, Price, Weight, Image" . $stock_col_sql . $discount_col_sql . ") VALUES (?, ?, ?, ?, ?" . $stock_val_sql . $discount_val_sql . ")";
                            $stmt = $conn->prepare($sql);
                            if ($stmt === false) {
                                @unlink($upload_destination_physical);
                                $msg = "Database error: could not prepare query.";
                            } else {
                               // ✅ WITH THIS FIXED BLOCK:
                              $discount = filter_var($_POST['discount'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
                             if ($discount === false) {
                             $discount = 0;
                                           }

                            if ($has_discount) {
                            $stmt->bind_param("ssissi", $product_name, $category, $price, $weight, $upload_destination_db, $discount);
                            } else {
                              $stmt->bind_param("ssiss", $product_name, $category, $price, $weight, $upload_destination_db);
        }
                                if ($stmt->execute()) {
                                    $msg = "Product added to the storefront.";
                                    $msg_type = "success";
                                } else {
                                    @unlink($upload_destination_physical);
                                    $msg = "Database error: " . $stmt->error;
                                }
                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// 3.5 Order Status Update Handler
// ============================================================
if ($db_ok && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = "Invalid session token.";
    } else {
        $order_id = filter_var($_POST['order_id'] ?? '', FILTER_VALIDATE_INT);
        $new_status = trim($_POST['status'] ?? '');
        $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        if ($order_id && in_array($new_status, $allowed_statuses, true)) {
            $update_sql = "UPDATE orders SET status = ? WHERE id = ?";
            if ($stmt = $conn->prepare($update_sql)) {
                $stmt->bind_param("si", $new_status, $order_id);
                if ($stmt->execute()) {
                    $msg = "Order #$order_id status updated to " . ucfirst($new_status) . ".";
                    $msg_type = "success";
                } else {
                    $msg = "Database failure: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $msg = "Invalid parameters provided.";
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// 3.6 CATALOG MANAGER HANDLERS
// ============================================================
if ($db_ok && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_product'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = "Invalid session token.";
    } else {
        $product_id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        if ($product_id) {
            $img_sql = "SELECT " . $product_image_col . " FROM Product WHERE " . $product_id_col . " = ?";
            $img_stmt = $conn->prepare($img_sql);
            $img_stmt->bind_param("i", $product_id);
            $img_stmt->execute();
            $img_result = $img_stmt->get_result();
            $img_row = $img_result->fetch_assoc();
            $img_stmt->close();

            $del_sql = "DELETE FROM Product WHERE " . $product_id_col . " = ?";
            $del_stmt = $conn->prepare($del_sql);
            $del_stmt->bind_param("i", $product_id);
            if ($del_stmt->execute()) {
                if ($img_row && !empty($img_row[$product_image_col])) {
                    $img_path = __DIR__ . '/' . $img_row[$product_image_col];
                    if (file_exists($img_path)) {
                        @unlink($img_path);
                    }
                }
                $msg = "Product deleted successfully.";
                $msg_type = "success";
            } else {
                $msg = "Could not delete product: " . $del_stmt->error;
            }
            $del_stmt->close();
        } else {
            $msg = "Invalid product ID.";
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: admin_dashboard.php?tab=catalog" . (!empty($msg) ? "&msg=" . urlencode($msg) . "&msg_type=" . $msg_type : ""));
    exit();
}

if ($db_ok && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_stock']) && $has_in_stock) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg = "Invalid session token.";
    } else {
        $product_id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT);
        $current_stock = isset($_POST['current_stock']) ? (int)$_POST['current_stock'] : 1;
        $new_stock = $current_stock ? 0 : 1;
        if ($product_id) {
            $toggle_sql = "UPDATE Product SET in_stock = ? WHERE " . $product_id_col . " = ?";
            $toggle_stmt = $conn->prepare($toggle_sql);
            $toggle_stmt->bind_param("ii", $new_stock, $product_id);
            if ($toggle_stmt->execute()) {
                $status_text = $new_stock ? "back in stock" : "out of stock";
                $msg = "Product marked as $status_text.";
                $msg_type = "success";
            } else {
                $msg = "Could not update stock status: " . $toggle_stmt->error;
            }
            $toggle_stmt->close();
        } else {
            $msg = "Invalid product ID.";
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: admin_dashboard.php?tab=catalog" . (!empty($msg) ? "&msg=" . urlencode($msg) . "&msg_type=" . $msg_type : ""));
    exit();
}

if (isset($_GET['msg']) && empty($msg)) {
    $msg = $_GET['msg'];
    $msg_type = $_GET['msg_type'] ?? 'error';
}

// ============================================================
// 4. Stats
// ============================================================
$total_sales_val = "¥0";
$total_orders_cnt = 0;
$total_items_cnt  = 0;
$orders = [];
$orders_table_missing = false;
$products = [];
$products_table_missing = false;

if ($db_ok) {
    if ($res = safe_query($conn, "SELECT COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status != 'cancelled'")) {
        $row = $res->fetch_assoc();
        $total_orders_cnt = (int)($row['order_count'] ?? 0);
        $total_sales_val  = "¥" . number_format((float)($row['total'] ?? 0));
    } else {
        $orders_table_missing = true;
    }

    if ($res = safe_query($conn, "SELECT COUNT(*) as total FROM Product")) {
        $row = $res->fetch_assoc();
        $total_items_cnt = (int)($row['total'] ?? 0);
    } else {
        $products_table_missing = true;
    }

    if (!$orders_table_missing) {
        if ($res = safe_query($conn, "SELECT o.*, u.user_name FROM orders o JOIN Users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 10")) {
            while ($row = $res->fetch_assoc()) {
                $orders[] = $row;
            }
        }
    }

    if (!$products_table_missing) {
        $stock_select = $has_in_stock ? ", in_stock" : "";
        $discount_select = $has_discount ? ", discount" : "";
        $product_query = "SELECT " . $product_id_col . " as pid, " . $product_name_col . " as pname, " . $product_cat_col . " as pcat, " . $product_price_col . " as pprice, " . $product_weight_col . " as pweight, " . $product_image_col . " as pimage" . $stock_select . $discount_select . " FROM Product ORDER BY " . $product_id_col . " DESC";
        if ($res = safe_query($conn, $product_query)) {
            while ($row = $res->fetch_assoc()) {
                $products[] = $row;
            }
        }
    }

    // ===== NOTIFICATION DATA =====
    $unread_orders = 0;
    $latest_orders = [];
    if (!$orders_table_missing) {
        // Count pending orders from last check
        $last_check = $_SESSION['last_order_check'] ?? date('Y-m-d H:i:s', strtotime('-1 day'));
        $unread_sql = "SELECT COUNT(*) as unread FROM orders WHERE status = 'pending' AND created_at > ?";
        if ($unread_stmt = $conn->prepare($unread_sql)) {
            $unread_stmt->bind_param("s", $last_check);
            $unread_stmt->execute();
            $unread_res = $unread_stmt->get_result();
            $unread_row = $unread_res->fetch_assoc();
            $unread_orders = (int)($unread_row['unread'] ?? 0);
            $unread_stmt->close();
        }
        // Get latest pending orders for notification
        $latest_sql = "SELECT o.id, o.total_amount, o.created_at, u.user_name 
                       FROM orders o JOIN Users u ON o.user_id = u.id 
                       WHERE o.status = 'pending' 
                       ORDER BY o.created_at DESC LIMIT 5";
        if ($latest_res = safe_query($conn, $latest_sql)) {
            while ($lrow = $latest_res->fetch_assoc()) {
                $latest_orders[] = $lrow;
            }
        }
        $_SESSION['last_order_check'] = date('Y-m-d H:i:s');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fresh Mart — Admin Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          display: ['Fraunces', 'serif'],
          sans: ['Inter', 'sans-serif'],
          mono: ['IBM Plex Mono', 'monospace'],
        },
        colors: {
          canvas: '#F6F3EC',
          card: '#FFFFFF',
          ink: '#16281F',
          ink2: '#2A362F',
          evergreen: { DEFAULT: '#1F5C3F', dark: '#163D2A', light: '#E8EFE9' },
          sage: { DEFAULT: '#9AAE93', light: '#EEF0E9' },
          amber: { DEFAULT: '#E2872E', dark: '#B5661B', light: '#FBEBD8' },
          brick: { DEFAULT: '#B23B3B', light: '#F7E6E3' },
          steel: { DEFAULT: '#3E6B8A', light: '#E4EDF2' },
          plum: { DEFAULT: '#6B4E8A', light: '#EEE7F3' },
          line: '#E3DECF',
        },
        borderRadius: { xl2: '14px' },
      }
    }
  }
</script>
<style>
  body { background-color: #F6F3EC; font-family: 'Inter', system-ui, sans-serif; }
  ::selection { background: #E2872E; color: #16281F; }

  .eyebrow {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }

  .stamp {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 5px 12px 4px;
    border: 1.5px dashed currentColor;
    border-radius: 999px;
    transform: rotate(-1.2deg);
    white-space: nowrap;
  }
  .stamp::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: currentColor;
    flex-shrink: 0;
  }

  .nav-link { position: relative; transition: background-color .2s ease, color .2s ease; }
  .nav-link.active { background: rgba(226,135,46,0.12); color: #F6F3EC; }
  .nav-link.active::before {
    content: '';
    position: absolute;
    left: -1px; top: 8px; bottom: 8px;
    width: 3px;
    border-radius: 2px;
    background: #E2872E;
  }

  .card { transition: transform .25s cubic-bezier(.4,0,.2,1), box-shadow .25s cubic-bezier(.4,0,.2,1); }
  .card:hover { transform: translateY(-2px); box-shadow: 0 18px 36px -18px rgba(22,40,31,0.18); }

  .field { background: #FFFFFF; border: 1.5px solid #E3DECF; transition: border-color .2s ease, box-shadow .2s ease; }
  .field:focus { outline: none; border-color: #1F5C3F; box-shadow: 0 0 0 3px rgba(31,92,63,0.14); }

  .btn-primary { transition: background-color .2s ease, transform .15s ease; }
  .btn-primary:hover { background-color: #163D2A; }
  .btn-primary:active { transform: scale(0.98); }

  @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
  .slide-down { animation: slideDown .35s ease forwards; }
  @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
  .fade-out { animation: fadeOut .3s ease forwards; }

  ::-webkit-scrollbar { width: 8px; height: 8px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: #D8D1BE; border-radius: 4px; }
  ::-webkit-scrollbar-thumb:hover { background: #C3BA9F; }

  .product-card { transition: all .25s cubic-bezier(.4,0,.2,1); }
  .product-card:hover { transform: translateY(-3px); box-shadow: 0 20px 40px -15px rgba(22,40,31,0.15); }
  .product-card.out-of-stock { opacity: 0.65; }
  .product-card.out-of-stock .product-image { filter: grayscale(0.6); }

  .stock-toggle {
    position: relative;
    width: 44px;
    height: 24px;
    border-radius: 999px;
    background: #E3DECF;
    cursor: pointer;
    transition: background .2s ease;
    border: none;
    padding: 0;
  }
  .stock-toggle.active { background: #1F5C3F; }
  .stock-toggle::after {
    content: '';
    position: absolute;
    top: 2px; left: 2px;
    width: 20px; height: 20px;
    border-radius: 50%;
    background: white;
    transition: transform .2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
  }
  .stock-toggle.active::after { transform: translateX(20px); }

  .delete-btn {
    opacity: 0;
    transform: translateY(4px);
    transition: all .2s ease;
  }
  .product-card:hover .delete-btn {
    opacity: 1;
    transform: translateY(0);
  }

  @media (prefers-reduced-motion: reduce) {
    .card, .btn-primary, .slide-down, .fade-out, .product-card, .stock-toggle, .delete-btn { animation: none !important; transition: none !important; }
  }

  @media (max-width: 768px) {
    .delete-btn { opacity: 1; transform: none; }
  }
</style>

  <!-- Order Notification System -->
  <style>
    @keyframes notifySlideIn {
      from { transform: translateX(120%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    @keyframes notifyPulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(31, 92, 63, 0.4); }
      50% { box-shadow: 0 0 0 8px rgba(31, 92, 63, 0); }
    }
    @keyframes bellRing {
      0% { transform: rotate(0); }
      10% { transform: rotate(15deg); }
      20% { transform: rotate(-15deg); }
      30% { transform: rotate(10deg); }
      40% { transform: rotate(-10deg); }
      50% { transform: rotate(5deg); }
      60% { transform: rotate(-5deg); }
      100% { transform: rotate(0); }
    }
    .order-toast {
      animation: notifySlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }
    .order-toast.hiding {
      animation: fadeOut 0.3s ease forwards;
    }
    .notify-badge {
      animation: notifyPulse 2s infinite;
    }
    .bell-ringing {
      animation: bellRing 0.8s ease;
    }
    .order-toast-stack {
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 12px;
      max-width: 380px;
      pointer-events: none;
    }
    .order-toast-stack > * {
      pointer-events: auto;
    }
    .sidebar-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      background: #E2872E;
      color: #fff;
      font-family: 'IBM Plex Mono', monospace;
      font-size: 10px;
      font-weight: 600;
      line-height: 1;
    }
  </style>

</head>
<body class="flex flex-col min-h-screen font-sans text-ink md:flex-row">

<?php if (!$db_ok): ?>
<div class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-ink/70">
  <div class="max-w-md p-8 bg-white border border-line rounded-xl2">
    <p class="mb-2 eyebrow text-brick">Setup needed</p>
    <h2 class="mb-3 text-xl font-semibold font-display text-ink">Database not connected</h2>
    <p class="text-sm text-ink2/70"><?php echo htmlspecialchars($db_error); ?></p>
    <p class="mt-4 text-xs text-ink2/50">Check that <code class="px-1 rounded bg-canvas">db.php</code> is in the same folder and creates a valid <code class="px-1 rounded bg-canvas">$conn</code>.</p>
  </div>
</div>
<?php endif; ?>

<header class="flex items-center justify-between px-5 py-4 bg-ink md:hidden">
  <a href="index.php" class="flex items-baseline gap-2">
    <span class="text-lg font-semibold font-display text-canvas">Fresh Mart</span>
    <span class="eyebrow text-amber">Admin</span>
  </a>
  <button id="mobileMenuBtn" class="flex items-center justify-center w-10 h-10 rounded-lg text-canvas bg-white/10">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </button>
</header>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex-col justify-between hidden p-6 md:flex w-72 bg-ink shrink-0 md:static">
  <div>
    <div class="mb-10">
      <h1 class="text-2xl font-semibold tracking-tight font-display text-canvas">Fresh Mart</h1>
      <div class="inline-flex items-center gap-2 mt-3">
        <span class="w-1.5 h-1.5 rounded-full bg-amber animate-pulse"></span>
        <span class="eyebrow text-sage">Admin Console</span>
      </div>
    </div>
    <p class="px-4 mb-3 eyebrow text-white/30">Operations</p>
    <nav class="space-y-1">
      <a href="admin_dashboard.php?tab=overview"
         class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium <?php echo $active_tab === 'overview' ? 'active' : 'text-white/55 hover:text-canvas hover:bg-white/5'; ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
        Overview
      </a>
      <a href="admin_dashboard.php?tab=orders"
         class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium <?php echo $active_tab === 'orders' ? 'active' : 'text-white/55 hover:text-canvas hover:bg-white/5'; ?>">
        <div class="relative">
          <svg id="ordersBell" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
          <?php if ($unread_orders > 0): ?>
          <span id="ordersBadge" class="absolute -top-1.5 -right-1.5 sidebar-badge <?php echo $unread_orders > 0 ? 'notify-badge' : ''; ?>"><?php echo $unread_orders > 99 ? '99+' : $unread_orders; ?></span>
          <?php endif; ?>
        </div>
        Manage Orders
      </a>
      <a href="admin_dashboard.php?tab=catalog"
         class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium <?php echo $active_tab === 'catalog' ? 'active' : 'text-white/55 hover:text-canvas hover:bg-white/5'; ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="3"/></svg>
        Catalog Manager
      </a>
      <a href="admin_dashboard.php?tab=add_product"
         class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium <?php echo $active_tab === 'add_product' ? 'active' : 'text-white/55 hover:text-canvas hover:bg-white/5'; ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Add Product
      </a>
    </nav>
  </div>
  <div class="pt-5 mt-5 border-t border-white/10">
    <div class="flex items-center gap-3 px-2 py-2 mb-2">
      <div class="flex items-center justify-center font-mono text-sm font-semibold rounded-lg w-9 h-9 bg-amber/20 text-amber">
        <?php echo htmlspecialchars(strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1))); ?>
      </div>
      <div class="min-w-0">
        <p class="text-sm font-medium truncate text-canvas"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
        <p class="eyebrow text-white/30">Administrator</p>
      </div>
    </div>
    <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-sm font-medium transition rounded-lg text-brick hover:bg-brick/10">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</aside>

<div id="mobileOverlay" class="fixed inset-0 z-30 hidden bg-ink/60 md:hidden"></div>

<main class="flex-1 overflow-y-auto">
  <div class="max-w-5xl px-5 py-8 mx-auto md:px-12 md:py-12">

    <!-- ================== TAB: OVERVIEW ================== -->
    <?php if ($active_tab === 'overview'): ?>
      <div class="mb-10">
        <p class="mb-2 eyebrow text-evergreen">Admin / Overview</p>
        <h2 class="text-3xl font-semibold tracking-tight font-display md:text-4xl text-ink">Dashboard Overview</h2>
        <p class="mt-2 text-sm text-ink2/60"><?php echo date('l, F j, Y'); ?> — live summary of your storefront.</p>
      </div>

      <div class="grid grid-cols-1 gap-4 mb-10 sm:grid-cols-3">
        <div class="p-6 border card bg-card border-line rounded-xl2">
          <p class="mb-4 eyebrow text-ink2/40">Gross Sales</p>
          <p class="text-3xl font-semibold font-display text-ink"><?php echo htmlspecialchars($total_sales_val); ?></p>
          <p class="mt-2 text-xs text-ink2/50">All time</p>
        </div>
        <div class="p-6 border card bg-card border-line rounded-xl2">
          <p class="mb-4 eyebrow text-ink2/40">Orders Processed</p>
          <p class="text-3xl font-semibold font-display text-ink"><?php echo $total_orders_cnt; ?></p>
          <p class="mt-2 text-xs text-ink2/50">All time</p>
        </div>
        <div class="p-6 border card bg-card border-line rounded-xl2">
          <p class="mb-4 eyebrow text-ink2/40">Items in Catalog</p>
          <p class="text-3xl font-semibold font-display text-ink"><?php echo $total_items_cnt; ?></p>
          <p class="mt-2 text-xs text-ink2/50">Currently listed</p>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="admin_dashboard.php?tab=add_product" class="card p-7 bg-evergreen rounded-xl2 group">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center justify-center rounded-lg w-11 h-11 bg-white/15">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F6F3EC" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F6F3EC" stroke-width="2" class="transition opacity-40 group-hover:opacity-100 group-hover:translate-x-1"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </div>
          <h3 class="text-lg font-semibold font-display text-canvas">Add New Product</h3>
          <p class="mt-1 text-sm text-canvas/60">Upload inventory to your storefront</p>
        </a>
        <a href="admin_dashboard.php?tab=catalog" class="card p-7 bg-steel rounded-xl2 group">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center justify-center rounded-lg w-11 h-11 bg-white/15">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F6F3EC" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F6F3EC" stroke-width="2" class="transition opacity-40 group-hover:opacity-100 group-hover:translate-x-1"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </div>
          <h3 class="text-lg font-semibold font-display text-canvas">Catalog Manager</h3>
          <p class="mt-1 text-sm text-canvas/60">View, delete & manage stock status</p>
        </a>
        <a href="admin_dashboard.php?tab=orders" class="border card p-7 bg-card border-line rounded-xl2 group">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center justify-center rounded-lg w-11 h-11 bg-evergreen-light">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1F5C3F" stroke-width="2"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16281F" stroke-width="2" class="transition opacity-30 group-hover:opacity-100 group-hover:translate-x-1"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
          </div>
          <h3 class="text-lg font-semibold font-display text-ink">Manage Orders</h3>
          <p class="mt-1 text-sm text-ink2/55">Review and process shipments</p>
        </a>
      </div>
    <?php endif; ?>

    <!-- ================== TAB: ORDERS ================== -->
    <?php if ($active_tab === 'orders'): ?>
      <div class="mb-8">
        <p class="mb-2 eyebrow text-evergreen">Admin / Orders</p>
        <h2 class="text-3xl font-semibold tracking-tight font-display md:text-4xl text-ink">Incoming Orders</h2>
        <p class="mt-2 text-sm text-ink2/60">Review checkouts, manage fulfillment state, and audit purchase logs.</p>
      </div>

      <?php if (!empty($msg)): ?>
      <div id="toastMsg" class="flex items-start gap-3 p-4 mb-6 border rounded-lg slide-down <?php echo $msg_type === 'success' ? 'bg-evergreen-light border-evergreen/20' : 'bg-brick-light border-brick/20'; ?>">
        <div class="flex items-center justify-center w-7 h-7 mt-0.5 rounded-md shrink-0 <?php echo $msg_type === 'success' ? 'bg-evergreen text-white' : 'bg-brick text-white'; ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
            <?php if ($msg_type === 'success'): ?><polyline points="20 6 9 17 4 12"/><<?php else: ?><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/><?php endif; ?>
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold <?php echo $msg_type === 'success' ? 'text-evergreen-dark' : 'text-brick'; ?>"><?php echo $msg_type === 'success' ? 'Success' : 'Error'; ?></p>
          <p class="mt-0.5 text-xs <?php echo $msg_type === 'success' ? 'text-evergreen-dark/70' : 'text-brick/80'; ?>"><?php echo htmlspecialchars($msg); ?></p>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($orders_table_missing): ?>
      <div class="p-4 mb-6 border rounded-lg bg-amber-light border-amber/30">
        <p class="text-sm font-semibold text-amber-dark">No "orders" table found yet</p>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
        <div class="overflow-hidden border bg-card border-line rounded-xl2 lg:col-span-7 h-fit">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-line bg-canvas">
                  <th class="p-4 eyebrow text-ink2/40">Order</th>
                  <th class="p-4 eyebrow text-ink2/40">Customer</th>
                  <th class="p-4 eyebrow text-ink2/40">Total</th>
                  <th class="p-4 eyebrow text-ink2/40">Status</th>
                  <th class="p-4 text-right eyebrow text-ink2/40">Action</th>
                </tr>
              </thead>
              <tbody class="text-sm divide-y divide-line">
                <?php if (empty($orders)): ?>
                <tr>
                  <td colspan="5" class="p-10 text-sm text-center text-ink2/40">No records found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $order):
                  $status = strtolower($order['status'] ?? 'pending');
                  $isSelected = isset($_GET['view_order']) && (int)$_GET['view_order'] === (int)$order['id'];
                ?>
                <tr class="transition cursor-pointer <?php echo $isSelected ? 'bg-sage-light/50' : 'hover:bg-canvas/60'; ?>" onclick="window.location.href='admin_dashboard.php?tab=orders&view_order=<?php echo $order['id']; ?>'">
                  <td class="p-4 font-mono text-[13px] font-semibold text-ink">#<?php echo htmlspecialchars($order['id'] ?? ''); ?></td>
                  <td class="p-4 font-medium text-ink2 truncate max-w-[120px]"><?php echo htmlspecialchars($order['user_name'] ?? ''); ?></td>
                  <td class="p-4 font-mono text-[13px] font-semibold text-ink">¥<?php echo number_format((float)($order['total_amount'] ?? 0)); ?></td>
                  <td class="p-4">
                    <?php
                    $row_status = strtolower($order['status'] ?? 'pending');
                    $row_badge_map = [
                      'pending' => ['bg-amber/15 text-amber-dark border-amber/30', 'Pending'],
                      'processing' => ['bg-steel/15 text-steel border-steel/30', 'Processing'],
                      'shipped' => ['bg-plum/15 text-plum border-plum/30', 'Shipped'],
                      'delivered' => ['bg-evergreen/15 text-evergreen-dark border-evergreen/30', 'Delivered'],
                      'cancelled' => ['bg-brick/15 text-brick border-brick/30', 'Cancelled']
                    ];
                    $row_badge = $row_badge_map[$row_status] ?? ['bg-amber/15 text-amber-dark border-amber/30', ucfirst($row_status)];
                    ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider border rounded-full <?php echo $row_badge[0]; ?>">
                      <span class="w-1.5 h-1.5 rounded-full <?php echo $row_status === 'cancelled' ? 'bg-brick' : ($row_status === 'delivered' ? 'bg-evergreen' : 'bg-current'); ?>"></span>
                      <?php echo $row_badge[1]; ?>
                    </span>
                  </td>
                  <td class="p-4 text-right">
                    <span class="text-xs font-semibold text-evergreen hover:underline">Inspect →</span>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="lg:col-span-5">
          <?php 
          $selected_id = filter_var($_GET['view_order'] ?? '', FILTER_VALIDATE_INT);
          $selected_order = null;
          $items = [];
          if ($selected_id && $db_ok) {
              $q = "SELECT o.*, u.user_name FROM orders o JOIN Users u ON o.user_id = u.id WHERE o.id = $selected_id LIMIT 1";
              if ($res = safe_query($conn, $q)) {
                  $selected_order = $res->fetch_assoc();
              }
              if ($selected_order) {
                  $iq = "SELECT * FROM order_items WHERE order_id = $selected_id";
                  if ($ires = safe_query($conn, $iq)) {
                      while($irow = $ires->fetch_assoc()) { $items[] = $irow; }
                  }
              }
          }
          ?>
          <?php if ($selected_order): 
            $current_status = strtolower($selected_order['status'] ?? 'pending');
            $stamp_map = [
              'pending' => 'text-amber', 'processing' => 'text-steel',
              'shipped' => 'text-plum', 'delivered' => 'text-evergreen', 'cancelled' => 'text-brick'
            ];
            $stamp_color = $stamp_map[$current_status] ?? 'text-amber';
          ?>
            <div class="sticky p-6 space-y-6 border bg-card border-line rounded-xl2 top-6 slide-down">
              <div class="flex items-center justify-between pb-4 border-b border-line">
                <div>
                  <h3 class="font-semibold text-md font-display text-ink">Order #<?php echo $selected_order['id']; ?></h3>
                  <p class="text-xs text-ink2/40 mt-0.5">Placed by: <?php echo htmlspecialchars($selected_order['user_name']); ?></p>
                </div>
                <span class="stamp <?php echo $stamp_color; ?>"><?php echo htmlspecialchars($current_status); ?></span>
              </div>
              <form action="admin_dashboard.php?tab=orders&view_order=<?php echo $selected_order['id']; ?>" method="POST" class="space-y-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="order_id" value="<?php echo $selected_order['id']; ?>">
                <label class="block eyebrow text-ink2/50">Change Status</label>
                <div class="flex gap-2">
                  <select name="status" class="flex-1 h-10 px-3 text-xs rounded-lg cursor-pointer field">
                    <option value="pending" <?php if($current_status === 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="processing" <?php if($current_status === 'processing') echo 'selected'; ?>>Processing</option>
                    <option value="shipped" <?php if($current_status === 'shipped') echo 'selected'; ?>>Shipped</option>
                    <option value="delivered" <?php if($current_status === 'delivered') echo 'selected'; ?>>Delivered</option>
                    <option value="cancelled" <?php if($current_status === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                  </select>
                  <button type="submit" name="update_status" class="px-4 text-xs font-semibold transition rounded-lg text-canvas bg-evergreen hover:bg-evergreen-dark">Update</button>
                </div>
              </form>
              <div class="space-y-3">
                <h4 class="eyebrow text-ink2/50">Manifest Allocation</h4>
                <div class="pr-1 overflow-y-auto divide-y divide-line max-h-48">
                  <?php if (empty($items)): ?>
                    <p class="py-3 text-xs italic text-ink2/40">No layout summary tracked for items.</p>
                  <?php else: ?>
                    <?php foreach ($items as $item): ?>
                      <div class="py-2.5 flex justify-between text-xs">
                        <div>
                          <p class="font-medium text-ink"><?php echo htmlspecialchars($item['product_name']); ?></p>
                          <p class="text-ink2/40 mt-0.5">Qty: <?php echo $item['quantity']; ?> × ¥<?php echo number_format($item['price_at_purchase']); ?></p>
                        </div>
                        <span class="font-mono font-semibold text-ink2">¥<?php echo number_format($item['quantity'] * $item['price_at_purchase']); ?></span>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="flex items-center justify-between pt-4 text-sm font-semibold border-t border-line">
                <span class="text-ink2/50">Settled Assessment:</span>
                <span class="font-mono text-ink">¥<?php echo number_format((float)$selected_order['total_amount']); ?></span>
              </div>
            </div>
          <?php else: ?>
            <div class="sticky p-8 text-sm text-center border border-dashed border-line rounded-xl2 text-ink2/40 top-6">
              Select an order record from the left table view to manage tracking status or view item manifests.
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- ================== TAB: CATALOG MANAGER ================== -->
    <?php if ($active_tab === 'catalog'): ?>
      <div class="mb-8">
        <p class="mb-2 eyebrow text-evergreen">Admin / Catalog</p>
        <h2 class="text-3xl font-semibold tracking-tight font-display md:text-4xl text-ink">Catalog Manager</h2>
        <p class="mt-2 text-sm text-ink2/60">View all listed products, toggle stock availability, or remove items from the storefront.</p>
      </div>

      <?php if (!empty($msg)): ?>
      <div id="toastMsg" class="flex items-start gap-3 p-4 mb-6 border rounded-lg slide-down <?php echo $msg_type === 'success' ? 'bg-evergreen-light border-evergreen/20' : 'bg-brick-light border-brick/20'; ?>">
        <div class="flex items-center justify-center w-7 h-7 mt-0.5 rounded-md shrink-0 <?php echo $msg_type === 'success' ? 'bg-evergreen text-white' : 'bg-brick text-white'; ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
            <?php if ($msg_type === 'success'): ?><polyline points="20 6 9 17 4 12"/><<?php else: ?><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/><?php endif; ?>
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-sm font-semibold <?php echo $msg_type === 'success' ? 'text-evergreen-dark' : 'text-brick'; ?>"><?php echo $msg_type === 'success' ? 'Success' : 'Error'; ?></p>
          <p class="mt-0.5 text-xs <?php echo $msg_type === 'success' ? 'text-evergreen-dark/70' : 'text-brick/80'; ?>"><?php echo htmlspecialchars($msg); ?></p>
        </div>
        <button onclick="document.getElementById('toastMsg').remove()" class="text-ink2/30 hover:text-ink2/60">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <?php endif; ?>

      <?php if ($products_table_missing): ?>
      <div class="p-4 mb-6 border rounded-lg bg-amber-light border-amber/30">
        <p class="text-sm font-semibold text-amber-dark">No "Product" table found yet</p>
      </div>
      <?php endif; ?>

      <?php if ($db_ok && !$products_table_missing && empty($products)): ?>
      <div class="p-4 mb-6 border rounded-lg bg-amber-light border-amber/30">
        <p class="text-sm font-semibold text-amber-dark">No products returned from query</p>
        <p class="mt-1 text-xs text-amber-dark/70">Detected columns: ID=<?php echo $product_id_col; ?>, Name=<?php echo $product_name_col; ?>, in_stock exists: <?php echo $has_in_stock ? 'YES' : 'NO'; ?></p>
        <p class="mt-1 text-xs text-amber-dark/70">Total items from overview count: <?php echo $total_items_cnt; ?></p>
        <p class="mt-1 text-xs text-amber-dark/70">Try running: SELECT * FROM Product in your database to verify data exists.</p>
      </div>
      <?php endif; ?>

      <!-- Stats Bar -->
      <div class="flex flex-wrap items-center gap-4 mb-6">
        <div class="flex items-center gap-2 px-4 py-2 border rounded-lg bg-card border-line">
          <span class="w-2 h-2 rounded-full bg-evergreen"></span>
          <span class="text-xs font-medium text-ink2"><?php echo count(array_filter($products, fn($p) => ($p['in_stock'] ?? 1) == 1)); ?> In Stock</span>
        </div>
        <div class="flex items-center gap-2 px-4 py-2 border rounded-lg bg-card border-line">
          <span class="w-2 h-2 rounded-full bg-brick"></span>
          <span class="text-xs font-medium text-ink2"><?php echo count(array_filter($products, fn($p) => ($p['in_stock'] ?? 1) == 0)); ?> Out of Stock</span>
        </div>
        <div class="flex items-center gap-2 px-4 py-2 border rounded-lg bg-card border-line">
          <span class="text-xs font-medium text-ink2/50">Total: <?php echo count($products); ?> items</span>
        </div>
      </div>

      <!-- Product Grid -->
      <?php if (empty($products)): ?>
      <div class="p-12 text-center border border-dashed border-line rounded-xl2">
        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-canvas">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#9AAE93" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <p class="text-sm font-medium text-ink2">No products in catalog</p>
        <p class="mt-1 text-xs text-ink2/50">Add your first product to get started.</p>
        <a href="admin_dashboard.php?tab=add_product" class="inline-flex items-center gap-2 px-4 py-2 mt-4 text-xs font-semibold text-white transition rounded-lg bg-evergreen hover:bg-evergreen-dark">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Add Product
        </a>
      </div>
      <?php else: ?>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($products as $product): 
          $is_in_stock = $has_in_stock ? ((int)($product['in_stock'] ?? 1) === 1) : true;
          $stock_class = $is_in_stock ? '' : 'out-of-stock';
          $stock_label = $is_in_stock ? 'In Stock' : 'Out of Stock';
          $stock_color = $is_in_stock ? 'text-evergreen' : 'text-brick';
          $img_path = !empty($product['pimage']) ? htmlspecialchars($product['pimage']) : '';
          $product_id_val = $product['pid'];
          $product_name_val = $product['pname'];
          $product_cat_val = $product['pcat'];
          $product_weight_val = $product['pweight'];
          $product_price_val = $product['pprice'];
        ?>
        <div class="product-card <?php echo $stock_class; ?> relative overflow-hidden border bg-card border-line rounded-xl2">

          <!-- Delete Button -->
          <form action="admin_dashboard.php?tab=catalog" method="POST" class="absolute z-10 delete-btn top-3 right-3" onsubmit="return confirm('Delete &quot;<?php echo addslashes(htmlspecialchars($product_name_val)); ?>&quot;? This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="product_id" value="<?php echo $product_id_val; ?>">
            <button type="submit" name="delete_product" class="flex items-center justify-center w-8 h-8 text-white transition rounded-lg shadow-sm bg-brick hover:bg-brick/90" title="Delete product">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </form>

          <!-- Product Image -->
          <div class="relative overflow-hidden product-image h-44 bg-canvas">
            <?php if ($img_path && file_exists(__DIR__ . '/' . $product['pimage'])): ?>
              <img src="<?php echo $img_path; ?>" alt="<?php echo htmlspecialchars($product_name_val); ?>" class="object-cover w-full h-full">
            <?php else: ?>
              <div class="flex items-center justify-center w-full h-full">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#C3BA9F" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
              </div>
            <?php endif; ?>
            <div class="absolute bottom-3 left-3">
              <span class="stamp <?php echo $stock_color; ?> text-[10px]" style="transform: rotate(-1deg);"><?php echo $stock_label; ?></span>
            </div>
          </div>

          <!-- Product Info -->
          <div class="p-4">
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="min-w-0">
                <h3 class="text-sm font-semibold truncate text-ink"><?php echo htmlspecialchars($product_name_val); ?></h3>
                <p class="text-xs text-ink2/50 mt-0.5"><?php echo htmlspecialchars($product_cat_val); ?> · <?php echo htmlspecialchars($product_weight_val); ?></p>
              </div>
            </div>
            <div class="flex items-center justify-between pt-3 border-t border-line">
              <div>
                <?php if (!empty($product['discount']) && (int)$product['discount'] > 0): 
                  $discounted = round((int)$product_price_val * (1 - (int)$product['discount']/100));
                ?>
                  <span class="font-mono text-sm font-semibold text-ink">¥<?php echo number_format($discounted); ?></span>
                  <span class="ml-1 font-mono text-xs line-through text-ink2/30">¥<?php echo number_format((int)$product_price_val); ?></span>
                  <span class="text-[10px] font-bold text-brick bg-brick-light px-1.5 py-0.5 rounded ml-1">-<?php echo (int)$product['discount']; ?>%</span>
                <?php else: ?>
                  <span class="font-mono text-sm font-semibold text-ink">¥<?php echo number_format((int)$product_price_val); ?></span>
                <?php endif; ?>
              </div>

              <?php if ($has_in_stock): ?>
              <form action="admin_dashboard.php?tab=catalog" method="POST" class="flex items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="product_id" value="<?php echo $product_id_val; ?>">
                <input type="hidden" name="current_stock" value="<?php echo $is_in_stock ? 1 : 0; ?>">
                <span class="text-[10px] font-medium text-ink2/40 uppercase tracking-wider">Stock</span>
                <button type="submit" name="toggle_stock" class="stock-toggle <?php echo $is_in_stock ? 'active' : ''; ?>" title="Click to toggle stock status"></button>
              </form>
              <?php else: ?>
              <span class="text-[10px] text-ink2/30 uppercase tracking-wider">No stock tracking</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- ================== TAB: ADD PRODUCT ================== -->
    <?php if ($active_tab === 'add_product'): ?>
      <div class="mb-8">
        <p class="mb-2 eyebrow text-evergreen">Admin / Add Product</p>
        <h2 class="text-3xl font-semibold tracking-tight font-display md:text-4xl text-ink">Add New Item</h2>
        <p class="mt-2 text-sm text-ink2/60">Populate new inventory directly into your live catalog.</p>
      </div>

      <div class="w-full max-w-xl border p-7 md:p-9 bg-card border-line rounded-xl2">
        <?php if (!empty($msg)): ?>
        <div id="toastMsg" class="flex items-start gap-3 p-4 mb-6 border rounded-lg slide-down <?php echo $msg_type === 'success' ? 'bg-evergreen-light border-evergreen/20' : 'bg-brick-light border-brick/20'; ?>">
          <div class="flex items-center justify-center w-7 h-7 mt-0.5 rounded-md shrink-0 <?php echo $msg_type === 'success' ? 'bg-evergreen text-white' : 'bg-brick text-white'; ?>">
            <?php if ($msg_type === 'success'): ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            <?php else: ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            <?php endif; ?>
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold <?php echo $msg_type === 'success' ? 'text-evergreen-dark' : 'text-brick'; ?>"><?php echo $msg_type === 'success' ? 'Added' : 'Error'; ?></p>
            <p class="mt-0.5 text-xs <?php echo $msg_type === 'success' ? 'text-evergreen-dark/70' : 'text-brick/80'; ?>"><?php echo htmlspecialchars($msg); ?></p>
          </div>
          <button onclick="document.getElementById('toastMsg').remove()" class="text-ink2/30 hover:text-ink2/60">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <?php endif; ?>

        <form action="admin_dashboard.php?tab=add_product" method="POST" enctype="multipart/form-data" class="space-y-5">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div>
            <label class="block mb-2 eyebrow text-ink2/50">Product Name</label>
            <input type="text" name="product_name" placeholder="e.g., Organic Bananas" required maxlength="150" class="w-full h-12 px-4 text-sm rounded-lg field text-ink placeholder-ink2/30">
          </div>
          <div>
            <label class="block mb-2 eyebrow text-ink2/50">Category</label>
            <select name="category" required class="w-full h-12 px-4 text-sm rounded-lg cursor-pointer field text-ink">
              <option value="">Select category</option>
              <option value="Fruits">Fruits</option>
              <option value="Vegetables">Vegetables</option>
              <option value="Dairy">Dairy</option>
              <option value="Meat">Meat</option>
              <option value="Spices">Spices</option>
              <option value="Beverages">Beverages</option>
              <option value="Snacks">Snacks</option>
              <option value="Frozen">Frozen</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block mb-2 eyebrow text-ink2/50">Price (¥)</label>
              <input type="number" name="price" placeholder="298" required min="1" step="1" class="w-full h-12 px-4 font-mono text-sm rounded-lg field text-ink placeholder-ink2/30">
            </div>
            <div>
              <label class="block mb-2 eyebrow text-ink2/50">Weight / Vol</label>
              <input type="text" name="weight" placeholder="500g or 1L" required maxlength="50" class="w-full h-12 px-4 text-sm rounded-lg field text-ink placeholder-ink2/30">
            </div>
          </div>
          <div>
            <label class="block mb-2 eyebrow text-ink2/50">Discount (%)</label>
            <div class="flex items-center gap-3">
              <input type="number" name="discount" placeholder="0" min="0" max="99" step="1" value="0" class="flex-1 h-12 px-4 font-mono text-sm rounded-lg field text-ink placeholder-ink2/30">
              <span class="text-sm font-medium text-ink2/40">% off</span>
            </div>
            <p class="mt-1.5 text-[11px] text-ink2/30">Leave at 0 for no discount. Enter 1-99 for percentage off.</p>
          </div>
          <div>
            <label class="block mb-2 eyebrow text-ink2/50">Product Image</label>
            <div class="relative p-6 text-center transition border-2 border-dashed rounded-lg cursor-pointer border-line hover:border-evergreen/50" onclick="document.getElementById('product_image').click()">
              <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/webp" required class="hidden" onchange="previewImage(this)">
              <div id="uploadPreview" class="flex items-center justify-center w-16 h-16 mx-auto mb-3 overflow-hidden rounded-lg bg-canvas">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9AAE93" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              </div>
              <p class="text-sm font-medium text-ink2">Click to upload image</p>
              <p class="mt-1 eyebrow text-ink2/35">JPG · PNG · WEBP — up to 5MB</p>
            </div>
          </div>
          <button type="submit" name="add_product" class="btn-primary w-full py-3.5 mt-2 font-semibold text-sm text-canvas bg-evergreen rounded-lg flex items-center justify-center gap-2">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Add to Storefront
          </button>
        </form>
      </div>
    <?php endif; ?>

  </div>
</main>

<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    sidebar.classList.toggle('hidden');
    sidebar.classList.toggle('flex');
    overlay.classList.toggle('hidden');
  }
  document.getElementById('mobileMenuBtn').addEventListener('click', toggleSidebar);
  document.getElementById('mobileOverlay').addEventListener('click', toggleSidebar);

  function previewImage(input) {
    const preview = document.getElementById('uploadPreview');
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.innerHTML = '<img src="' + e.target.result + '" class="object-cover w-full h-full">';
      }
      reader.readAsDataURL(input.files[0]);
    }
  }

  const toast = document.getElementById('toastMsg');
  if (toast) {
    setTimeout(() => {
      toast.classList.add('fade-out');
      setTimeout(() => toast.remove(), 300);
    }, 5000);
  }
</script>


<!-- Order Notification Toast Stack -->
<div id="toastStack" class="order-toast-stack"></div>

<script>
// ===== ORDER NOTIFICATION SYSTEM =====
(function() {
  const unreadCount = <?php echo (int)$unread_orders; ?>;
  const latestOrders = <?php echo json_encode($latest_orders); ?>;
  const toastStack = document.getElementById('toastStack');
  const bell = document.getElementById('ordersBell');

  // Sound for new order (using Web Audio API - no external file needed)
  function playNotificationSound() {
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return;
      const ctx = new AudioContext();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(523, ctx.currentTime);
      osc.frequency.setValueAtTime(659, ctx.currentTime + 0.1);
      osc.frequency.setValueAtTime(784, ctx.currentTime + 0.2);
      gain.gain.setValueAtTime(0.15, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.5);
    } catch(e) {}
  }

  // Create a toast notification
  function showOrderToast(order) {
    if (!toastStack) return;

    const toast = document.createElement('div');
    toast.className = 'order-toast';
    toast.innerHTML = `
      <div class="flex items-start gap-3 p-4 bg-white border shadow-lg border-evergreen/20 rounded-xl2" style="box-shadow: 0 10px 40px -10px rgba(31,92,63,0.25);">
        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-lg bg-evergreen-light">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1F5C3F" stroke-width="2">
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
            <path d="m3.3 7 8.7 5 8.7-5"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-ink">New Order Received!</p>
          <p class="mt-0.5 text-xs text-ink2/60 truncate">Order #${order.id} — ${order.user_name || 'Customer'}</p>
          <p class="mt-1 font-mono text-sm font-semibold text-evergreen">¥${parseInt(order.total_amount).toLocaleString()}</p>
        </div>
        <button onclick="this.closest('.order-toast').remove()" class="flex-shrink-0 text-ink2/30 hover:text-ink2/60 mt-0.5">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    `;

    toastStack.appendChild(toast);
    playNotificationSound();

    if (bell) {
      bell.classList.remove('bell-ringing');
      void bell.offsetWidth;
      bell.classList.add('bell-ringing');
    }

    setTimeout(() => {
      toast.classList.add('hiding');
      setTimeout(() => toast.remove(), 300);
    }, 8000);
  }

  // Show toasts for unread orders on page load
  if (latestOrders && latestOrders.length > 0) {
    latestOrders.slice(0, 3).forEach((order, index) => {
      setTimeout(() => showOrderToast(order), index * 600);
    });
  }

  // Real-time polling every 30 seconds
  let lastOrderId = latestOrders.length > 0 ? latestOrders[0].id : 0;

  function checkNewOrders() {
    fetch('check_new_orders.php?last_id=' + lastOrderId)
      .then(r => r.json())
      .then(data => {
        if (data.orders && data.orders.length > 0) {
          data.orders.forEach((order, i) => {
            setTimeout(() => showOrderToast(order), i * 400);
          });
          lastOrderId = data.orders[data.orders.length - 1].id;
          const badge = document.getElementById('ordersBadge');
          if (badge) {
            let count = parseInt(badge.textContent) || 0;
            count += data.orders.length;
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.add('notify-badge');
          }
        }
      })
      .catch(() => {});
  }

  setInterval(checkNewOrders, 30000);

})();
</script>

</body>
</html>