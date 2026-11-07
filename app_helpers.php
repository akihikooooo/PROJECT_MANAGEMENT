<?php
function requireAppLogin(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

function appEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function renderAppHeader(string $activePage, string $pageTitle, string $pageSubtitle): void
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    $userName = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "My workspace";
    $initial = strtoupper(substr((string) $userName, 0, 1));
    $navigation = [
        "dashboard" => ["dashboard.php", "Overview", "▦"],
        "my_task" => ["my_task.php", "My tasks", "✓"],
        "calendar" => ["calendar.php", "Calendar", "□"],
        "project" => ["project.php", "Projects", "▤"],
        "team_management" => ["team_management.php", "Team", "♧"],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="<?= appEscape($_SESSION["csrf_token"]) ?>">
        <title><?= appEscape($pageTitle) ?> | TaskFlow</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="app-body" data-page="<?= appEscape($activePage) ?>">
        <div class="app-shell">
            <aside class="app-sidebar" id="app-sidebar">
                <a class="app-brand" href="dashboard.php"><span class="app-brand-mark">T</span><span>TaskFlow</span></a>
                <div class="workspace-label">WORKSPACE</div>
                <nav class="app-nav" aria-label="Main navigation">
                    <?php foreach ($navigation as $key => [$href, $label, $icon]): ?>
                        <a class="app-nav-link<?= $activePage === $key ? " active" : "" ?>" href="<?= appEscape($href) ?>"<?= $activePage === $key ? ' aria-current="page"' : "" ?>>
                            <span class="nav-icon" aria-hidden="true"><?= appEscape($icon) ?></span><span><?= appEscape($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-bottom">
                    <div class="sidebar-note"><span class="note-dot"></span><span>Workspace data<br><strong>Saved to MySQL</strong></span></div>
                    <a class="app-nav-link logout-link" href="logout.php"><span class="nav-icon" aria-hidden="true">↪</span><span>Log out</span></a>
                </div>
            </aside>
            <main class="app-main">
                <header class="app-topbar">
                    <button class="mobile-menu" type="button" aria-label="Open navigation" aria-expanded="false" data-menu-toggle>☰</button>
                    <div class="breadcrumbs">Workspace <span>/</span> <strong><?= appEscape($pageTitle) ?></strong></div>
                    <div class="topbar-user"><span class="topbar-avatar"><?= appEscape($initial) ?></span><span><?= appEscape($userName) ?></span></div>
                </header>
                <div class="app-content">
                    <div class="page-heading"><div><p class="eyebrow">TASKFLOW WORKSPACE</p><h1><?= appEscape($pageTitle) ?></h1><p class="page-subtitle"><?= appEscape($pageSubtitle) ?></p></div></div>
                    <div class="app-feedback" data-feedback role="status" aria-live="polite" hidden></div>
    <?php
}

function renderAppFooter(): void
{
    ?>
                </div>
            </main>
        </div>
        <div class="mobile-scrim" data-menu-close></div>
        <script src="script.js" defer></script>
    </body>
    </html>
    <?php
}
