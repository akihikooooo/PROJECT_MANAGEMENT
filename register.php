<?php
session_start();

require_once "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($fullName === "" || $username === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $error = "Please complete all fields to create your account.";
    } elseif (mb_strlen($fullName) < 2) {
        $error = "Please enter your full name using at least 2 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $error = "Username can only contain letters, numbers, dots, underscores, and hyphens.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match. Please try again.";
    } elseif (!isset($conn) || !($conn instanceof mysqli)) {
        $error = "Database connection is unavailable. Please try again later.";
    } else {
        try {
            $checkStmt = $conn->prepare(
                "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1"
            );
            $checkStmt->bind_param("ss", $username, $email);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $existingUser = $result->fetch_assoc();
            $checkStmt->close();

            if ($existingUser) {
                $error = "That username or email is already in use.";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $conn->prepare(
                    "INSERT INTO users (username, full_name, email, password, role, status)
                     VALUES (?, ?, ?, ?, 'member', 'active')"
                );
                $insertStmt->bind_param("ssss", $username, $fullName, $email, $passwordHash);
                $insertStmt->execute();
                $insertStmt->close();

                $_SESSION["registration_success"] = "Account created successfully. Please sign in below.";
                header("Location: login.php");
                exit;
            }
        } catch (mysqli_sql_exception $exception) {
            $error = "Unable to create your account right now. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow - Create account</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="page">

        <div class="decoration decoration-1"></div>
        <div class="decoration decoration-2"></div>

        <main class="login-card">

            <section class="brand-panel">

                <div class="brand">
                    <div class="brand-icon">✓</div>
                    <span>TaskFlow</span>
                </div>

                <h2>Plan <span>•</span> Organize <span>•</span> Achieve</h2>

                <p class="description">
                    Manage your tasks, projects,<br>
                    and team – all in one place.
                </p>

                <div class="illustration">

                    <div class="task-board">
                        <div class="task-row">
                            <div class="small-check">✓</div>
                            <div class="task-line"></div>
                        </div>
                        <div class="task-row">
                            <div class="small-check">✓</div>
                            <div class="task-line medium"></div>
                        </div>
                        <div class="task-row">
                            <div class="small-check">✓</div>
                            <div class="task-line short"></div>
                        </div>
                    </div>

                    <div class="calendar">
                        <div class="calendar-top">
                            <span></span>
                            <span></span>
                        </div>
                        <div class="calendar-title">
                            <div class="calendar-check">✓</div>
                            <div class="calendar-lines"></div>
                        </div>
                        <div class="calendar-grid">
                            <i></i><i></i><i></i><i></i>
                            <i></i><i></i><i></i><i></i>
                            <i></i><i></i><i></i><i></i>
                        </div>
                    </div>

                    <div class="person">
                        <div class="head">
                            <div class="hair"></div>
                        </div>
                        <div class="neck"></div>
                        <div class="body">
                            <div class="arm"></div>
                        </div>
                        <div class="laptop">
                            <div class="laptop-screen">
                                <div class="laptop-logo"></div>
                            </div>
                            <div class="laptop-base"></div>
                        </div>
                    </div>

                    <div class="leaf leaf-1"></div>
                    <div class="leaf leaf-2"></div>
                    <div class="leaf leaf-3"></div>

                </div>

            </section>

            <section class="form-panel">

                <div class="form-content">

                    <h1>Create your account</h1>
                    <p class="subtitle">Start managing your work with TaskFlow.</p>

                    <?php if ($error !== ""): ?>
                        <div class="error-message" role="alert">
                            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php" novalidate>

                        <div class="input-group">
                            <span class="input-icon">👤</span>
                            <input
                                type="text"
                                name="full_name"
                                placeholder="Full name"
                                value="<?= htmlspecialchars($_POST["full_name"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                                autocomplete="name"
                                required>
                        </div>

                        <div class="input-group">
                            <span class="input-icon">@</span>
                            <input
                                type="text"
                                name="username"
                                placeholder="Username"
                                value="<?= htmlspecialchars($_POST["username"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                                autocomplete="username"
                                required>
                        </div>

                        <div class="input-group">
                            <span class="input-icon">✉</span>
                            <input
                                type="email"
                                name="email"
                                placeholder="Email address"
                                value="<?= htmlspecialchars($_POST["email"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                                autocomplete="email"
                                required>
                        </div>

                        <div class="input-group">
                            <span class="input-icon">🔒</span>
                            <input
                                type="password"
                                name="password"
                                placeholder="Password"
                                autocomplete="new-password"
                                required>
                        </div>

                        <div class="input-group">
                            <span class="input-icon">🔐</span>
                            <input
                                type="password"
                                name="confirm_password"
                                placeholder="Confirm password"
                                autocomplete="new-password"
                                required>
                        </div>

                        <button type="submit" class="login-btn">Create account</button>

                    </form>

                    <p class="signup">
                        Already have an account?
                        <a href="login.php">Log in</a>
                    </p>

                </div>

            </section>

        </main>

    </div>

</body>

</html>
