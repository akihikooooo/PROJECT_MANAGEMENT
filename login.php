<?php
session_start();

require_once "db.php";

$error = "";

$success = $_SESSION["registration_success"] ?? "";
if ($success !== "") {
	unset($_SESSION["registration_success"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$login = trim($_POST["login"] ?? "");
	$password = $_POST["password"] ?? "";
	$remember = isset($_POST["remember"]);

	if ($login === "" || $password === "") {
		$error = "Please enter your username/email and password.";
	} elseif (!isset($conn) || !($conn instanceof mysqli)) {
		$error = "Database connection is unavailable. Please try again later.";
	} else {
		try {
			$stmt = $conn->prepare(
				"SELECT id, username, full_name, email, password, role, status
				 FROM users
				 WHERE username = ? OR email = ?
				 LIMIT 1"
			);

			$stmt->bind_param("ss", $login, $login);
			$stmt->execute();

			$result = $stmt->get_result();
			$user = $result->fetch_assoc();
			$stmt->close();

			if (!$user || !password_verify($password, $user["password"])) {
				$error = "Invalid username/email or password.";
			} elseif ($user["status"] !== "active") {
				$error = "Your account is currently inactive.";
			} else {
				session_regenerate_id(true);

				$_SESSION["user_id"] = $user["id"];
				$_SESSION["username"] = $user["username"];
				$_SESSION["full_name"] = $user["full_name"];
				$_SESSION["email"] = $user["email"];
				$_SESSION["role"] = $user["role"];

				if ($remember) {
					setcookie(
						"taskflow_user",
						$user["username"],
						time() + (86400 * 30),
						"/",
						"",
						!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
						true
					);
				}

				header("Location: dashboard.php");
				exit;
			}
		} catch (mysqli_sql_exception $exception) {
			$error = "Unable to log in right now. Please check the database configuration.";
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title>TaskFlow - Login</title>

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
		rel="stylesheet"
	>

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

				<h1>Welcome back!</h1>
				<p class="subtitle">Log in to your TaskFlow account.</p>

				<?php if ($error !== ""): ?>
					<div class="error-message" role="alert">
						<?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
					</div>
				<?php elseif ($success !== ""): ?>
					<div class="success-message" role="status">
						<?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?>
					</div>
				<?php endif; ?>

				<form method="POST" action="login.php">

					<div class="input-group">
						<span class="input-icon">✉</span>
						<input
							type="text"
							name="login"
							placeholder="Email or Username"
							value="<?= htmlspecialchars($_POST["login"] ?? "", ENT_QUOTES, "UTF-8") ?>"
							autocomplete="username"
							required
						>
					</div>

					<div class="input-group">
						<span class="input-icon">🔒</span>
						<input
							type="password"
							name="password"
							placeholder="Password"
							autocomplete="current-password"
							required
						>
					</div>

					<div class="form-options">
						<label class="remember">
							<input type="checkbox" name="remember" checked>
							<span class="custom-checkbox">✓</span>
							<span>Remember me</span>
						</label>

						<a href="forgot-password.php" class="forgot">
							Forgot password?
						</a>
					</div>

					<button type="submit" class="login-btn">Log In</button>

				</form>

				<p class="signup">
					Don't have an account?
					<a href="register.php">Sign up</a>
				</p>

			</div>

		</section>

	</main>

</div>

</body>
</html>
