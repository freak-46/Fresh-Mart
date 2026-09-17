<?php
// 1. Initialize the session check at the very top
session_start();

// Include database connection file
include 'db.php';

// Guard Check: Force redirect if they aren't authenticated or if they are not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: signin.php");
    exit();
}

// 🚀 BACKEND PRODUCT UPLOAD ENGINE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    
    // Clean and capture string variables
    $product_name = mysqli_real_escape_string($conn, trim($_POST['product_name']));
    $category     = mysqli_real_escape_string($conn, $_POST['category']);
    $price        = (int)$_POST['price'];
    $weight       = mysqli_real_escape_string($conn, trim($_POST['weight']));
    
    // File upload array management
    $image_file = $_FILES['product_image'];
    $image_name = $image_file['name'];
    $image_tmp  = $image_file['tmp_name'];
    $image_size = $image_file['size'];
    $image_err  = $image_file['error'];
    
    $file_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp');
    
    if ($image_err !== 0) {
        $msg = "❌ Error uploading image file.";
    } elseif (!in_array($file_ext, $allowed_extensions)) {
        $msg = "❌ Invalid file type! Only JPG, JPEG, PNG, and WEBP files are allowed.";
    } elseif ($image_size > 5000000) { 
        $msg = "❌ File size is too massive! Keep images under 5MB.";
    } else {
        // 1. Build a clean unique filename
        $new_image_name = uniqid('prod_', true) . "." . $file_ext;
        
        // 2. Absolute path for PHP file system operation (Must remain absolute)
        $upload_destination_physical = __DIR__ . '/images/' . $new_image_name;
        
        // 3. 🌟 CLEAN WEB PATH: This is what saves into MySQL database!
        // Storing it as a purely relative address makes it easy for the browser to read.
        $upload_destination_db = 'images/' . $new_image_name;
        
        // Execute the stream copy engine fallback
        $moved = move_uploaded_file($image_tmp, $upload_destination_physical);
        if (!$moved) {
            $moved = copy($image_tmp, $upload_destination_physical);
        }
        
        if ($moved) {
            @chmod($upload_destination_physical, 0777);
            
            // Insert data into your Product table
            $sql = "INSERT INTO Product (Name, Category, Price, Weight, Image) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiss", $product_name, $category, $price, $weight, $upload_destination_db);
            
            if ($stmt->execute()) {
                echo "<script>alert('Product added successfully!'); window.location.href='admin_panel.php';</script>";
                exit();
            } else {
                $msg = "❌ Database error: Could not save product data. " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_details = error_get_last();
            $msg = "❌ System failure: " . ($error_details ? $error_details['message'] : "Cannot write file.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - Fresh Mart</title>
  <link rel="stylesheet" href="src/output.css">
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-gray-50 md:p-8">
  
  <div class="w-full max-w-lg p-6 bg-white border border-gray-100 shadow-xl rounded-3xl">
    <h2 class="mb-1 font-serif text-xl font-bold text-gray-800">Add New Item 🍇</h2>
    <p class="mb-6 text-xs text-gray-400">Populate new inventory data directly into your catalog.</p>

    <?php if (isset($msg)): ?>
        <div class="p-3 mb-4 text-sm font-semibold text-red-700 border border-red-100 bg-red-50 rounded-xl">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <form action="admin_panel.php" method="POST" enctype="multipart/form-data" class="space-y-4">
      
      <div>
        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Product Name</label>
        <input type="text" name="product_name" placeholder="e.g., Organic Bananas" required 
               class="w-full px-3 transition border border-gray-300 outline-none h-11 rounded-xl focus:border-emerald-600">
      </div>

      <div>
        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Category</label>
        <select name="category" required 
                class="w-full px-3 transition bg-white border border-gray-300 outline-none h-11 rounded-xl focus:border-emerald-600">
          <option value="">Select Category</option>
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
          <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Price (¥)</label>
          <input type="number" name="price" placeholder="298" required min="1"
                 class="w-full px-3 transition border border-gray-300 outline-none h-11 rounded-xl focus:border-emerald-600">
        </div>
        <div>
          <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Weight / Vol</label>
          <input type="text" name="weight" placeholder="e.g., 500g or 1L" required 
                 class="w-full px-3 transition border border-gray-300 outline-none h-11 rounded-xl focus:border-emerald-600">
        </div>
      </div>

      <div>
        <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Product Image</label>
        <input type="file" name="product_image" accept="image/*" required
               class="w-full text-sm text-gray-500 cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
      </div>

      <button type="submit" name="add_product" 
              class="w-full py-3 mt-4 font-bold text-white transition shadow-md cursor-pointer bg-emerald-600 rounded-xl hover:bg-emerald-700 shadow-emerald-50/50">
        Upload to Storefront
      </button>
    </form>
  </div>

</body>
</html>