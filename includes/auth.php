<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function isUserLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

function requireUserLogin(): void
{
    if (!isUserLoggedIn()) {
        redirect('login.php');
    }

    if (function_exists('db')) {
        $stmt = db()->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND status = 'active' LIMIT 1");
        $stmt->execute(['user_id' => (int)$_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            unset($_SESSION['user_id'], $_SESSION['user_name']);
            flash('error', 'Your session is invalid. Please login again.');
            redirect('login.php');
        }
    }
}

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        redirect('admin/login.php');
    }

    if (function_exists('db')) {
        $stmt = db()->prepare('SELECT admin_id FROM admin WHERE admin_id = :admin_id LIMIT 1');
        $stmt->execute(['admin_id' => (int)$_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            unset($_SESSION['admin_id'], $_SESSION['admin_name']);
            flash('error', 'Admin session is invalid. Please login again.');
            redirect('admin/login.php');
        }
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function getFlash(string $type): array
{
    $messages = $_SESSION['flash'][$type] ?? [];
    if (isset($_SESSION['flash'][$type])) {
        unset($_SESSION['flash'][$type]);
    }
    return $messages;
}
?>
