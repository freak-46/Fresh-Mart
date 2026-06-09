<?php
include'db.php';

if ($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

if(($_SERVER['REQUEST_METHOD'] == 'POST') && isset($_POST['submit'])){
    $user_name = $_POST['user_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $phone_number = $_POST['phone_number'];
    $date_of_birth = $_POST['date_of_birth'];

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);


  $sql = "INSERT INTO Users (user_name, email, password, phone_number, date_of_birth) VALUES (?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("sssss", $user_name, $email, $hashed_password, $phone_number, $date_of_birth);
 
  if($stmt->execute()){
    header("Location: signin.php?status=success");
    exit();
  } else{
    echo "Error: " . $sql . "<br>" . $conn->error;}
    $stmt->close();
    $conn->close();
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Fresh Mart</title>
    <link rel="stylesheet" href="src/output.css">
</head>
<body>
    <div class="text-white bg-green-600 border h-50 " style="clip-path: ellipse(120% 100% at 50% 0%);"></div>
    <div class="absolute top-0 z-10 p-7">
        <h1 class="text-3xl font-bold text-center ">Create Your Account</h1>
        <div class="flex items-center justify-center gap-3 mt-7">
          <img src="images/shopping-cart.png" alt="Fresh Mart Logo" width="40" >
          <h1 class=" font-serif text-[25px] lg:text-[28px] ">Fresh Mart</h1>
         </div> 
    </div>
        
    </div>
  <form action="signup.php" method="POST" class="mt-5 ">
    <div class="flex flex-col items-center justify-center gap-5">
        <input type="text" id="user_name" name="user_name" class="px-4 py-2 border border-gray-300 h-15 w-70 rounded-2xl text-20" placeholder="User Name" required>
        <input type="email" id="email" name="email" class="px-4 py-2 border border-gray-300 h-15 w-70 rounded-2xl" placeholder="Email" required>
        <input type="password" id="password" name="password" class="px-4 py-2 border border-gray-300 h-15 w-70 rounded-2xl" placeholder="Password"  required>
        <input type="text" id="phone_number" name="phone_number" class="px-4 py-2 border border-gray-300 h-15 w-70 rounded-2xl" placeholder="Phone Number" required>
        <input type="text" id="date_of_birth" name="date_of_birth" class="px-4 py-2 border border-gray-300 h-15 w-70 rounded-2xl" placeholder="Date of Birth (YYYY-MM-DD)" required>
        <div>
            <input type="checkbox" id="terms" name="terms" class="mr-2" required>
            <label for="terms">I agree to the Terms and Conditions</label>
        </div>
        <button type="submit" name="submit" class="px-4 py-2 text-white bg-green-600 rounded hover:bg-green-700 w-50">Create Account</button>
    </div>
  </form> 

</body>
</html>