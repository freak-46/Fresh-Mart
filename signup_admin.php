<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_register'])) {
    $username = trim($_POST['user_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone_number']);
    $dob = trim($_POST['date_of_birth']);
    $secret_key = $_POST['secret_key'];

    // 🔒 Security Check: Protect the admin creation form with a master passcode
    if ($secret_key !== "MyStoreAdmin2026") {
        $error = "❌ Invalid Secret Admin Key! Registration denied.";
    } else {
        // Encrypt the password using your local system settings
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // Prepare the insert statement matching your exact database schema
        $sql = "INSERT INTO users (user_name, email, password, phone_number, date_of_birth, role, is_first_login) VALUES (?, ?, ?, ?, ?, 'admin', 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $username, $email, $hashed_password, $phone, $dob);
        
        if ($stmt->execute()) {
            echo "<script>alert('Admin registration successful! You can now log in.'); window.location.href='signin.php';</script>";
            exit();
        } else {
            $error = "❌ Database registration error: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Creation - Fresh Mart</title>
    <link rel="stylesheet" href="src/output.css">
</head>
<body class="flex items-center justify-center min-h-screen p-4 bg-gray-100">

    <div class="w-full max-w-lg p-8 bg-white border border-gray-200 shadow-xl rounded-3xl">
        <h2 class="mb-1 font-serif text-2xl font-bold text-blue-950">Create Admin Account 🛠️</h2>
        <p class="pb-3 mb-6 text-xs text-gray-400 border-b">Register an authenticated administrative user profile.</p>

        <?php if (isset($error)): ?>
            <div class="p-3 mb-4 text-sm font-semibold text-red-700 border border-red-100 bg-red-50 rounded-xl">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="signup_admin.php" method="POST" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Username</label>
                    <input type="text" name="user_name" required class="w-full px-3 border border-gray-300 outline-none h-11 rounded-xl focus:border-blue-900">
                </div>
                <div>
                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Email Address</label>
                    <input type="email" name="email" required class="w-full px-3 border border-gray-300 outline-none h-11 rounded-xl focus:border-blue-900">
                </div>
            </div>

            <div>
                <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Password</label>
                <input type="password" name="password" required pattern="(?=.*\d)(?=.*[A-Z]).{8,}" title="Must be at least 8 characters long, contain an uppercase letter, and a number" class="w-full px-3 border border-gray-300 outline-none h-11 rounded-xl focus:border-blue-900">
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Phone Number</label>
                    <input type="text" name="phone_number" placeholder="000-0000-0000" required class="w-full px-3 border border-gray-300 outline-none h-11 rounded-xl focus:border-blue-900">
                </div>
                <div>
                    <label class="block mb-1 text-xs font-bold text-gray-500 uppercase">Date of Birth</label>
                    <input type="text" name="date_of_birth" placeholder="YYYY-MM-DD" required class="w-full px-3 border border-gray-300 outline-none h-11 rounded-xl focus:border-blue-900">
                </div>
            </div>

            <div class="pt-2 border-t border-gray-100">
                <label class="block mb-1 text-xs font-bold text-red-600 uppercase">Secret Admin Passkey</label>
                <input type="password" name="secret_key" placeholder="Enter registration security token" required class="w-full px-3 border-2 border-red-100 outline-none bg-red-50/30 h-11 rounded-xl focus:border-red-500">
            </div>

            <button type="submit" name="admin_register" class="w-full bg-blue-950 text-white font-bold py-3.5 rounded-xl hover:bg-blue-900 transition mt-2 cursor-pointer">
                Register Admin Account
            </button>
        </form>
    </div>

</body>
</html>