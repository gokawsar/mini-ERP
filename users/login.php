<?php
// Start session for authentication
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli('localhost', 'root', '', 'dbms');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = ''; // Message for success/error

// Logout logic
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Login logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $msg = "<p style='color:#dc3545; font-weight:bold;'>Please enter both username and password.</p>";
    } else {
        $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE username=?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->bind_result($user_id, $hash);
            if ($stmt->fetch() && password_verify($password, $hash)) {
                $_SESSION['user_id'] = $user_id;
                $stmt->close();
                $conn->close(); // Close connection before redirect
                header("Location: ../index.php");
                exit;
            } else {
                $msg = "<p style='color:#dc3545; font-weight:bold;'>Invalid username or password.</p>";
            }
            $stmt->close();
        } else {
            $msg = "<p style='color:#dc3545; font-weight:bold;'>Database error: Could not prepare login statement.</p>";
        }
    }
}

// Registration logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $msg = "<p style='color:#dc3545; font-weight:bold;'>All registration fields are required.</p>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<p style='color:#dc3545; font-weight:bold;'>Please enter a valid email address.</p>";
    } elseif (strlen($password) < 6) {
        $msg = "<p style='color:#dc3545; font-weight:bold;'>Password must be at least 6 characters long.</p>";
    } else {
        // Check for duplicate username/email
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username=? OR email=?");
        if ($stmt_check) {
            $stmt_check->bind_param("ss", $username, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $msg = "<p style='color:#dc3545; font-weight:bold;'>Username or email already exists.</p>";
            } else {
                $stmt_check->close();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                if ($stmt_insert) {
                    $stmt_insert->bind_param("sss", $username, $hashed_password, $email);
                    if ($stmt_insert->execute()) {
                        $msg = "<p style='color:#28a745; font-weight:bold;'>Account created successfully! You can now log in.</p>";
                    } else {
                        $msg = "<p style='color:#dc3545; font-weight:bold;'>Error creating account: " . htmlspecialchars($stmt_insert->error) . "</p>";
                    }
                    $stmt_insert->close();
                } else {
                    $msg = "<p style='color:#dc3545; font-weight:bold;'>Database error: Could not prepare registration statement.</p>";
                }
            }
            //$stmt_check->close(); // Ensure statement is closed after use
        } else {
            $msg = "<p style='color:#dc3545; font-weight:bold;'>Database error: Could not prepare duplicate check statement.</p>";
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register - Mini-ERP</title>
    <style>
        body {
            background-color: #f4f7f6; /* Light background for the whole page */
            margin: 0;
            display: flex;
            flex-direction: column; /* Changed to column to stack brand header and container */
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
            color: #333;
        }
        .brand-header {
            background-color: #2c3e50; /* Dark blue from sidebar */
            color: #ecf0f1; /* Off-white from sidebar */
            padding: 20px;
            width: 100%;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px; /* Space below the brand header */
            box-sizing: border-box; /* Include padding in width calculation */
        }
        .brand-header h1 {
            margin: 0;
            font-size: 2.5em;
            letter-spacing: 2px;
        }
        .container {
            display: flex;
            flex-direction: column;
            gap: 30px; /* Space between login and register forms */
            padding: 20px;
            max-width: 800px; /* Adjust max-width to accommodate both forms side-by-side or stacked */
            width: 100%;
            /* margin: 32px auto; Removed as body flex handles centering */
        }
        .form-card {
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            flex: 1; /* Allow cards to grow and shrink */
            min-width: 300px; /* Minimum width before wrapping */
        }
        .form-card h2 {
            font-size: 1.8em;
            margin-bottom: 25px;
            color: #2c3e50; /* Dark blue from sidebar */
            text-align: center;
        }
        .form-card label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }
        .form-card input[type="text"],
        .form-card input[type="email"],
        .form-card input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            margin-bottom: 15px;
            font-size: 1em;
        }
        .form-card button[type="submit"] {
            background-color: #007bff; /* Primary blue color */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1em;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }
        .form-card button[type="submit"]:hover {
            background-color: #0056b3; /* Darker blue on hover */
            transform: translateY(-2px);
        }
        .message {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-size: 1em;
            width: 100%; /* Ensure message takes full width in container */
            box-sizing: border-box;
        }
        /* Flex container for forms on wider screens */
        @media (min-width: 768px) {
            .container {
                flex-direction: row; /* Side-by-side on larger screens */
                justify-content: center;
                align-items: flex-start; /* Align forms at the top */
            }
            .form-card {
                flex: 1; /* Allow forms to take equal space */
                margin: 0 10px; /* Space between forms */
            }
        }
    </style>
</head>
<body>
    <div class="brand-header">
        <h1>Mini-ERP</h1>
    </div>
    <div class="container">
        <?php if (!empty($msg)): ?>
            <div class="message">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <div class="form-card">
            <h2>Login</h2>
            <form method="post" action="login.php" aria-label="Login Form">
                <label for="login-username">Username:</label>
                <input type="text" id="login-username" name="username" required autocomplete="username">

                <label for="login-password">Password:</label>
                <input type="password" id="login-password" name="password" required autocomplete="current-password">
                
                <button type="submit" name="login">Login</button>
            </form>
        </div>

        <!-- Registration Form -->
        <div class="form-card">
            <h2>Create Account</h2>
            <form method="post" action="login.php" aria-label="Registration Form">
                <label for="register-username">Username:</label>
                <input type="text" id="register-username" name="username" required autocomplete="new-username">

                <label for="register-email">Email:</label>
                <input type="email" id="register-email" name="email" required autocomplete="email">

                <label for="register-password">Password:</label>
                <input type="password" id="register-password" name="password" required autocomplete="new-password">
                
                <button type="submit" name="register">Register</button>
            </form>
        </div>
    </div>
</body>
</html>
