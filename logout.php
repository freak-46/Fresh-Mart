<?php
// 1. Core hand-shake to connect to the current active session
session_start();

// 2. Clear all of the session variables out of temporary memory
$_SESSION = array();

// 3. Completely destroy the session cookie on the server side
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session completely
session_destroy();

// 5. Redirect the user back to the profile page (which will now show the Guest view!)
header("Location: profile.php");
exit();
?>

explain this code