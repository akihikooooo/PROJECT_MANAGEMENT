<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
	$cookie = session_get_cookie_params();
	setcookie(
		session_name(),
		"",
		time() - 42000,
		$cookie["path"],
		$cookie["domain"],
		$cookie["secure"],
		$cookie["httponly"]
	);
}

setcookie(
	"taskflow_user",
	"",
	time() - 3600,
	"/",
	"",
	!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
	true
);

session_destroy();
header("Location: login.php");
exit;
