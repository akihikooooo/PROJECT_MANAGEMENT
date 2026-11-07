<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

function apiRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function apiDate($value): ?string
{
    if ($value === null || $value === "") {
        return null;
    }

    if (!is_string($value)) {
        throw new InvalidArgumentException("Invalid date.");
    }

    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (!$date || ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0))) {
        throw new InvalidArgumentException("Invalid date.");
    }

    return $date->format("Y-m-d");
}

if (empty($_SESSION["user_id"])) {
    apiRespond(401, ["error" => "Please sign in to continue."]);
}

require_once "db.php";

if (!($conn instanceof mysqli)) {
    apiRespond(503, ["error" => "Database connection is unavailable. Check db.php and start MySQL."]);
}

$userId = (int) $_SESSION["user_id"];
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

try {
    if ($method === "GET") {
        $tasksStmt = $conn->prepare(
            "SELECT t.id, t.title, t.project_id, COALESCE(p.name, '') AS project,
                    DATE_FORMAT(t.due_date, '%Y-%m-%d') AS due,
                    t.priority, (t.is_completed = 1) AS completed
             FROM tasks t
             LEFT JOIN projects p ON p.id = t.project_id AND p.owner_user_id = t.user_id
             WHERE t.user_id = ?
             ORDER BY t.is_completed ASC, t.due_date IS NULL, t.due_date ASC, t.created_at DESC"
        );
        $tasksStmt->bind_param("i", $userId);
        $tasksStmt->execute();
        $tasks = $tasksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tasksStmt->close();

        $projectsStmt = $conn->prepare(
            "SELECT id, name, owner_label AS owner,
                    DATE_FORMAT(target_date, '%Y-%m-%d') AS due, progress, tone
             FROM projects WHERE owner_user_id = ? ORDER BY created_at DESC"
        );
        $projectsStmt->bind_param("i", $userId);
        $projectsStmt->execute();
        $projects = $projectsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $projectsStmt->close();

        $membersStmt = $conn->prepare(
            "SELECT id, name, email, role FROM team_members
             WHERE owner_user_id = ? ORDER BY name ASC"
        );
        $membersStmt->bind_param("i", $userId);
        $membersStmt->execute();
        $members = $membersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $membersStmt->close();

        apiRespond(200, ["tasks" => $tasks, "projects" => $projects, "members" => $members]);
    }

    if ($method !== "POST") {
        apiRespond(405, ["error" => "Method not allowed."]);
    }

    $sessionToken = $_SESSION["csrf_token"] ?? "";
    $requestToken = $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
    if ($sessionToken === "" || !hash_equals($sessionToken, $requestToken)) {
        apiRespond(403, ["error" => "The request could not be verified. Refresh the page and try again."]);
    }

    $payload = json_decode(file_get_contents("php://input"), true);
    if (!is_array($payload)) {
        apiRespond(400, ["error" => "Invalid request data."]);
    }

    $action = $payload["action"] ?? "";

    if ($action === "create_task") {
        $title = trim((string) ($payload["title"] ?? ""));
        $priority = (string) ($payload["priority"] ?? "Medium");
        $dueDate = apiDate($payload["due"] ?? null);
        $projectId = null;

        if ($title === "" || strlen($title) > 100 || !in_array($priority, ["Low", "Medium", "High"], true)) {
            throw new InvalidArgumentException("Enter a task name and valid priority.");
        }

        if (isset($payload["project_id"]) && $payload["project_id"] !== "" && $payload["project_id"] !== null) {
            $projectId = filter_var($payload["project_id"], FILTER_VALIDATE_INT);
            if (!$projectId || $projectId < 1) {
                throw new InvalidArgumentException("Choose a valid project.");
            }
            $projectCheck = $conn->prepare("SELECT id FROM projects WHERE id = ? AND owner_user_id = ?");
            $projectCheck->bind_param("ii", $projectId, $userId);
            $projectCheck->execute();
            $belongsToUser = (bool) $projectCheck->get_result()->fetch_assoc();
            $projectCheck->close();
            if (!$belongsToUser) {
                apiRespond(404, ["error" => "Project not found."]);
            }
        }

        $stmt = $conn->prepare(
            "INSERT INTO tasks (user_id, project_id, title, due_date, priority)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iisss", $userId, $projectId, $title, $dueDate, $priority);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
        apiRespond(201, ["ok" => true, "id" => $id]);
    }

    if ($action === "toggle_task") {
        $taskId = filter_var($payload["id"] ?? null, FILTER_VALIDATE_INT);
        if (!$taskId || $taskId < 1) {
            throw new InvalidArgumentException("Invalid task.");
        }
        $completed = !empty($payload["completed"]) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE tasks SET is_completed = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $completed, $taskId, $userId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();
        if ($changed === 0) {
            $check = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
            $check->bind_param("ii", $taskId, $userId);
            $check->execute();
            $exists = (bool) $check->get_result()->fetch_assoc();
            $check->close();
            if (!$exists) {
                apiRespond(404, ["error" => "Task not found."]);
            }
        }
        apiRespond(200, ["ok" => true]);
    }

    if ($action === "delete_task") {
        $taskId = filter_var($payload["id"] ?? null, FILTER_VALIDATE_INT);
        if (!$taskId || $taskId < 1) {
            throw new InvalidArgumentException("Invalid task.");
        }
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $taskId, $userId);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();
        if (!$deleted) {
            apiRespond(404, ["error" => "Task not found."]);
        }
        apiRespond(200, ["ok" => true]);
    }

    if ($action === "create_project") {
        $name = trim((string) ($payload["name"] ?? ""));
        $owner = trim((string) ($payload["owner"] ?? ""));
        $dueDate = apiDate($payload["due"] ?? null);
        $tones = ["green", "coral", "blue"];
        $tone = in_array(($payload["tone"] ?? ""), $tones, true) ? $payload["tone"] : "green";

        if ($name === "" || strlen($name) > 70 || strlen($owner) > 50) {
            throw new InvalidArgumentException("Enter a project name and valid owner.");
        }

        $stmt = $conn->prepare(
            "INSERT INTO projects (owner_user_id, name, owner_label, target_date, tone)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issss", $userId, $name, $owner, $dueDate, $tone);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
        apiRespond(201, ["ok" => true, "id" => $id]);
    }

    if ($action === "create_member") {
        $name = trim((string) ($payload["name"] ?? ""));
        $email = trim((string) ($payload["email"] ?? ""));
        $role = trim((string) ($payload["role"] ?? ""));
        if ($role === "") {
            $role = "Team member";
        }

        if ($name === "" || strlen($name) > 60 || strlen($email) > 100 || strlen($role) > 50 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Enter a name and valid email address.");
        }

        $stmt = $conn->prepare(
            "INSERT INTO team_members (owner_user_id, name, email, role)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $userId, $name, $email, $role);
        $stmt->execute();
        $id = $conn->insert_id;
        $stmt->close();
        apiRespond(201, ["ok" => true, "id" => $id]);
    }

    apiRespond(400, ["error" => "Unknown action."]);
} catch (InvalidArgumentException $exception) {
    apiRespond(422, ["error" => $exception->getMessage()]);
} catch (mysqli_sql_exception $exception) {
    error_log("TaskFlow API database error: " . $exception->getMessage());
    if ((int) $exception->getCode() === 1062) {
        apiRespond(409, ["error" => "That email is already in your team."]);
    }
    apiRespond(500, ["error" => "The database could not complete this request."]);
}
