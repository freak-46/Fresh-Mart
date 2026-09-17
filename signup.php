<?php
include 'db.php';

if ($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

// Local variable to hold the error message for this specific page load
$error_message = "";

if(($_SERVER['REQUEST_METHOD'] == 'POST') && isset($_POST['submit'])){
    $user_name = trim($_POST['user_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone_number = trim($_POST['phone_number']);
    $date_of_birth = $_POST['date_of_birth'];

    // Check if email already exists
    $check_sql = "SELECT COUNT(*) FROM Users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
        $error_message = htmlspecialchars($email) . " is already registered!";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO Users (user_name, email, password, phone_number, date_of_birth) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $user_name, $email, $hashed_password, $phone_number, $date_of_birth);

        if($stmt->execute()){
            $stmt->close();
            $conn->close();
            header("Location: signin.php?status=success");
            exit();
        } else {
            $error_message = "Error: " . $conn->error;
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
    <title>Create Account - Fresh Mart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Playfair Display', 'serif'],
                    },
                    colors: {
                        primary: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' },
                        accent: { 50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f' },
                        slate: { 850: '#1e293b', 900: '#0f172a' },
                    }
                }
            }
        }
    </script>
    <style>
        .glass { background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .hero-pattern { background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
        .gradient-text { background: linear-gradient(135deg, #16a34a 0%, #059669 50%, #047857 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .blob { border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .float-anim { animation: float 5s ease-in-out infinite; }
        .btn-press:active { transform: scale(0.96); }
        .input-focus:focus { box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); border-color: #22c55e; }
        .input-group:focus-within i { color: #16a34a; }
        .input-group:focus-within { border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); }
        .password-strength { height: 3px; border-radius: 2px; transition: all 0.3s ease; }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #22c55e; width: 100%; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
        .shake { animation: shake 0.4s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .slide-down { animation: slideDown 0.4s ease forwards; }
    </style>
</head>
<body class="min-h-screen font-sans antialiased bg-gray-50">

    <!-- Top Bar -->
    <div class="py-2 text-xs text-gray-300 bg-slate-900">
        <div class="flex items-center justify-between px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1"><i class="fas fa-truck-fast text-primary-400"></i> Free delivery on orders over $50</span>
                <span class="items-center hidden gap-1 sm:flex"><i class="fas fa-phone text-primary-400"></i> 090-1234-1532</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="#" class="transition-colors hover:text-white">Help Center</a>
            </div>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-50 border-b glass border-gray-200/50">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">
                <a href="index.php" class="flex items-center gap-2.5 group">
                    <div class="flex items-center justify-center w-10 h-10 transition-shadow shadow-lg bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl shadow-primary-500/30 group-hover:shadow-primary-500/50">
                        <i class="text-lg text-white fas fa-leaf"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold leading-tight font-display lg:text-2xl gradient-text">Fresh Mart</h1>
                        <p class="text-[10px] text-gray-400 -mt-0.5 tracking-widest uppercase font-medium">Premium Groceries</p>
                    </div>
                </a>
                <div class="flex items-center gap-3">
                    <a href="signin.php" class="items-center hidden gap-2 text-sm font-medium text-gray-600 transition-colors sm:flex hover:text-primary-600">
                        <i class="text-xs fas fa-sign-in-alt"></i> Sign In
                    </a>
                    <a href="index.php" class="flex items-center gap-2 text-gray-600 transition-colors hover:text-primary-600 group">
                        <div class="flex items-center justify-center transition-colors bg-gray-100 rounded-full w-9 h-9 group-hover:bg-primary-50">
                            <i class="text-sm fas fa-home"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="relative overflow-hidden">
        <!-- Background Decoration -->
        <div class="absolute inset-0 bg-gradient-to-br from-primary-700 via-primary-600 to-emerald-700 hero-pattern"></div>
        <div class="absolute inset-0 bg-black/10"></div>
        <div class="absolute top-20 right-20 w-72 h-72 bg-white/5 blob float-anim"></div>
        <div class="absolute w-48 h-48 bottom-20 left-10 bg-white/5 blob float-anim" style="animation-delay: 2.5s; border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;"></div>

        <div class="relative z-10 px-4 py-12 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-16 lg:py-20">
            <div class="grid items-center gap-12 lg:grid-cols-2">

                <!-- Left Side - Branding -->
                <div class="hidden text-white lg:block">
                    <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm rounded-full px-4 py-1.5 mb-6 border border-white/20">
                        <span class="w-2 h-2 rounded-full bg-accent-400 animate-pulse"></span>
                        <span class="text-sm font-medium">Join 10,000+ happy customers</span>
                    </div>
                    <h1 class="mb-6 text-4xl font-bold leading-tight font-display sm:text-5xl">
                        Start Your Fresh<br>
                        <span class="text-primary-200">Journey Today</span>
                    </h1>
                    <p class="max-w-md mb-8 text-lg leading-relaxed text-primary-100">
                        Create your account and get access to exclusive deals, fast checkout, and personalized grocery recommendations.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-xl">
                                <i class="text-white fas fa-bolt"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold">Lightning Fast Checkout</p>
                                <p class="text-xs text-primary-200">Save your details for instant purchases</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-xl">
                                <i class="text-white fas fa-tag"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold">Exclusive Member Deals</p>
                                <p class="text-xs text-primary-200">Up to 30% off on premium products</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-xl">
                                <i class="text-white fas fa-heart"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold">Personalized Favorites</p>
                                <p class="text-xs text-primary-200">Smart recommendations just for you</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Form -->
                <div class="w-full max-w-md mx-auto lg:mx-0 lg:ml-auto">
                    <!-- Mobile Header -->
                    <div class="mb-8 text-center text-white lg:hidden">
                        <h1 class="mb-2 text-3xl font-bold font-display">Create Account</h1>
                        <p class="text-sm text-primary-100">Join Fresh Mart today</p>
                    </div>

                    <!-- Error Message -->
                    <?php if (!empty($error_message)): ?>
                    <div id="errorAlert" class="flex items-start gap-3 p-4 mb-4 border bg-rose-50 border-rose-200 rounded-2xl slide-down">
                        <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                            <i class="text-sm fas fa-exclamation-circle text-rose-500"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-rose-800">Registration Failed</p>
                            <p class="text-xs text-rose-600 mt-0.5"><?php echo $error_message; ?></p>
                        </div>
                        <button onclick="document.getElementById('errorAlert').remove()" class="ml-auto transition-colors text-rose-400 hover:text-rose-600">
                            <i class="text-xs fas fa-times"></i>
                        </button>
                    </div>
                    <script>
                        if (window.history.replaceState) {
                            window.history.replaceState(null, null, window.location.href);
                        }
                    </script>
                    <?php endif; ?>

                    <!-- Signup Form Card -->
                    <div class="p-6 bg-white shadow-2xl rounded-3xl shadow-black/10 sm:p-8">
                        <div class="mb-8 text-center">
                            <div class="flex items-center justify-center mx-auto mb-4 shadow-lg w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl shadow-primary-500/20">
                                <i class="text-xl text-white fas fa-user-plus"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 font-display">Create Account</h2>
                            <p class="mt-1 text-sm text-gray-400">Fill in your details to get started</p>
                        </div>

                        <form action="signup.php" method="POST" class="space-y-4" id="signupForm">
                            <!-- Username -->
                            <div class="flex items-center gap-3 px-4 transition-all duration-300 border border-gray-200 input-group rounded-xl bg-gray-50/50">
                                <i class="text-sm text-gray-400 transition-colors fas fa-user"></i>
                                <input type="text" id="user_name" name="user_name" 
                                    class="w-full py-3 text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none"
                                    placeholder="Full Name" required>
                            </div>

                            <!-- Email -->
                            <div class="flex items-center gap-3 px-4 transition-all duration-300 border border-gray-200 input-group rounded-xl bg-gray-50/50">
                                <i class="text-sm text-gray-400 transition-colors fas fa-envelope"></i>
                                <input type="email" id="email" name="email" 
                                    class="w-full py-3 text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none"
                                    placeholder="Email Address" required>
                            </div>

                            <!-- Password -->
                            <div>
                                <div class="flex items-center gap-3 px-4 transition-all duration-300 border border-gray-200 input-group rounded-xl bg-gray-50/50">
                                    <i class="text-sm text-gray-400 transition-colors fas fa-lock"></i>
                                    <input type="password" id="password" name="password" 
                                        class="w-full py-3 text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none"
                                        placeholder="Password" required 
                                        pattern="(?=.*\d)(?=.*[A-Z]).{8,}"
                                        title="Password must be at least 8 characters, contain one uppercase letter and one number"
                                        oninput="checkStrength(this.value)">
                                    <button type="button" id="togglePassword" 
                                        class="px-2 py-1 text-xs font-semibold transition-colors rounded-lg text-primary-600 hover:text-primary-700 hover:bg-primary-50">
                                        Show
                                    </button>
                                </div>
                                <!-- Password Strength Bar -->
                                <div class="px-1 mt-2">
                                    <div class="h-1 overflow-hidden bg-gray-100 rounded-full">
                                        <div id="strengthBar" class="password-strength"></div>
                                    </div>
                                    <p id="strengthText" class="text-[10px] text-gray-400 mt-1 font-medium"></p>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="flex items-center gap-3 px-4 transition-all duration-300 border border-gray-200 input-group rounded-xl bg-gray-50/50">
                                <i class="text-sm text-gray-400 transition-colors fas fa-phone"></i>
                                <input type="tel" id="phone_number" name="phone_number" 
                                    class="w-full py-3 text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none"
                                    placeholder="Phone Number" required>
                            </div>

                            <!-- Date of Birth -->
                            <div class="flex items-center gap-3 px-4 transition-all duration-300 border border-gray-200 input-group rounded-xl bg-gray-50/50">
                                <i class="text-sm text-gray-400 transition-colors fas fa-calendar-alt"></i>
                                <input type="date" id="date_of_birth" name="date_of_birth" 
                                    class="w-full py-3 text-sm text-gray-700 placeholder-gray-400 bg-transparent outline-none"
                                    required placeholder="Date of Birth">  
                                  
                            </div>

                            <!-- Terms -->
                            <label class="flex items-start gap-3 cursor-pointer group">
                                <div class="relative flex items-center">
                                    <input type="checkbox" id="terms" name="terms" class="sr-only peer" required>
                                    <div class="flex items-center justify-center w-5 h-5 transition-all border-2 border-gray-300 rounded-md peer-checked:bg-primary-600 peer-checked:border-primary-600">
                                        <i class="fas fa-check text-white text-[10px] opacity-0 peer-checked:opacity-100"></i>
                                    </div>
                                </div>
                                <span class="text-xs leading-relaxed text-gray-500 transition-colors group-hover:text-gray-600">
                                    I agree to the <a href="#" class="font-semibold underline text-primary-600 hover:text-primary-700">Terms and Conditions</a> and <a href="#" class="font-semibold underline text-primary-600 hover:text-primary-700">Privacy Policy</a>
                                </span>
                            </label>

                            <!-- Submit Button -->
                            <button type="submit" name="submit" 
                                class="w-full py-3.5 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-bold text-sm rounded-xl hover:from-primary-700 hover:to-primary-800 transition-all duration-300 btn-press shadow-lg shadow-primary-500/20 flex items-center justify-center gap-2">
                                <i class="text-xs fas fa-user-plus"></i> Create Account
                            </button>
                        </form>

                        <!-- Divider -->
                        <div class="flex items-center gap-4 my-6">
                            <div class="flex-1 h-px bg-gray-200"></div>
                            <span class="text-xs font-medium text-gray-400">or</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>

                        <!-- Social Signup -->
                        <div class="grid grid-cols-2 gap-3">
                            <button class="flex items-center justify-center gap-2 py-2.5 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all duration-300 text-sm font-medium text-gray-600 btn-press">
                                <i class="text-red-500 fab fa-google"></i> Google
                            </button>
                            <button class="flex items-center justify-center gap-2 py-2.5 border border-gray-200 rounded-xl hover:bg-gray-50 transition-all duration-300 text-sm font-medium text-gray-600 btn-press">
                                <i class="text-blue-600 fab fa-facebook"></i> Facebook
                            </button>
                        </div>

                        <!-- Sign In Link -->
                        <p class="mt-6 text-sm text-center text-gray-500">
                            Already have an account? 
                            <a href="signin.php" class="font-bold transition-colors text-primary-600 hover:text-primary-700">Sign In</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-gray-400 bg-slate-900">
        <div class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <div class="flex items-center gap-2.5">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700">
                        <i class="text-xs text-white fas fa-leaf"></i>
                    </div>
                    <span class="text-sm font-bold text-white font-display">Fresh Mart</span>
                </div>
                <p class="text-xs text-gray-500">&copy; 2026 Fresh Mart. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300">Privacy</a>
                    <a href="#" class="text-xs text-gray-500 transition-colors hover:text-gray-300">Terms</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Toggle Password Visibility
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                togglePassword.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                togglePassword.textContent = 'Show';
            }
        });

        // Password Strength Checker
        function checkStrength(password) {
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');

            if (password.length === 0) {
                bar.className = 'password-strength';
                bar.style.width = '0';
                text.textContent = '';
                return;
            }

            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            bar.className = 'password-strength';

            if (strength <= 1) {
                bar.classList.add('strength-weak');
                text.textContent = 'Weak password';
                text.className = 'text-[10px] mt-1 font-medium text-rose-500';
            } else if (strength === 2 || strength === 3) {
                bar.classList.add('strength-medium');
                text.textContent = 'Medium strength';
                text.className = 'text-[10px] mt-1 font-medium text-amber-500';
            } else {
                bar.classList.add('strength-strong');
                text.textContent = 'Strong password';
                text.className = 'text-[10px] mt-1 font-medium text-green-600';
            }
        }

        // Form Validation
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const terms = document.getElementById('terms');
            if (!terms.checked) {
                e.preventDefault();
                terms.parentElement.parentElement.classList.add('shake');
                setTimeout(() => terms.parentElement.parentElement.classList.remove('shake'), 400);
            }
        });
    </script>

</body>
</html>