<?php
// 1. Initialize the session machine at the very top
session_start();

include 'db.php';

if (($_SERVER['REQUEST_METHOD'] == 'POST') && isset($_POST['login_submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Raw text password from form

    // 2. Look up the user by email only (using lowercase "users" to match your DB)
    $sql = "SELECT user_name, password FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    $result = $stmt->get_result();

    // 3. Check if the user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify if the typed password matches the encrypted database hash
        if (password_verify($password, $user['password'])) {
            
            // SUCCESS! Set the session tracking variables
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['email'] = $email; 
            
            // Jump straight over to your profile dashboard
            header("Location: profile.php");
            exit(); // Ensures the rest of the file completely stops execution
            
        } else {
            echo "<script>alert('Incorrect password!'); window.location.href='signin.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('No account found with that email!'); window.location.href='signin.php';</script>";
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SignIn - Fresh Mart</title>
    <link rel="stylesheet" href="src/output.css">
</head>
<body class="flex flex-col justify-between min-h-screen font-sans bg-gray-50">

    <main class="flex items-center justify-center p-4 grow">
        
        <div class="w-full max-w-sm p-8 bg-white border border-gray-100 shadow-xl rounded-3xl">
            
            <h1 class="mb-8 text-3xl font-extrabold text-center text-emerald-600">Welcome</h1>
            
            <form action="signin.php" method="POST" class="flex flex-col gap-4">
                
                <div>
                    <label class="block mb-1 ml-1 text-xs font-bold tracking-wide text-gray-500 uppercase">Email</label>
                    <input type="email" name="email" placeholder="Enter Your Email" required
                           class="w-full p-3 transition-all border-2 outline-none bg-gray-50 border-emerald-600 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block mb-1 ml-1 text-xs font-bold tracking-wide text-gray-500 uppercase">Password</label>
                    <input type="password" name="password" placeholder="Enter Your Password" required
                           class="w-full p-3 transition-all border-2 outline-none bg-gray-50 border-emerald-600 rounded-xl focus:ring-2 focus:ring-emerald-500">
                </div>

                <div class="-mt-1 text-right">
                    <a href="forgot-password.php" class="text-xs font-semibold text-emerald-600 hover:underline">
                        Forget Password?
                    </a>
                </div>

                <button type="submit" name="login_submit"
                        class="w-full bg-emerald-600 text-white font-bold py-3.5 rounded-xl hover:bg-emerald-700 transition-all shadow-md shadow-emerald-100 mt-2 cursor-pointer">
                    Login
                </button>
                
            </form>

            <div class="pt-6 mt-8 text-center border-t border-gray-100">
                <p class="text-sm text-gray-500">New to Fresh Mart?</p>
                <a href="signup.php" 
                   class="inline-block px-6 py-2 mt-2 font-bold transition-colors border-2 text-emerald-600 border-emerald-600 rounded-xl hover:bg-emerald-50">
                    Create an account
                </a>
            </div>

        </div>
    </main>

</body>
</html>