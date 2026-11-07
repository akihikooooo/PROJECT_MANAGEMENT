<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = getenv("TASKFLOW_DB_HOST") ?: "127.0.0.1";
$dbName = getenv("TASKFLOW_DB_NAME") ?: "taskflow_db";
$dbUser = getenv("TASKFLOW_DB_USER") ?: "root";
$dbPassword = getenv("TASKFLOW_DB_PASSWORD") ?: "";

$conn = null;

try {
	$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
	$conn->set_charset("utf8mb4");
	$conn->query("SET time_zone = '+00:00'");
} catch (mysqli_sql_exception $exception) {
	error_log("TaskFlow database connection failed: " . $exception->getMessage());
}
