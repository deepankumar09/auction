<?php
declare(strict_types=1);

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

autoCloseExpiredAuctions();

$pageTitle = $pageTitle ?? APP_NAME;
$isAdminArea = str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc($pageTitle); ?> | <?php echo esc(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="container nav-wrap">
        <a class="brand" href="<?php echo BASE_URL; ?>/index.php">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" alt="Logo">
            <span><?php echo esc(APP_NAME); ?></span>
        </a>
        <nav>
            <?php if ($isAdminArea): ?>
                <a href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/admin/add_vehicle.php">Add Vehicle</a>
                <a href="<?php echo BASE_URL; ?>/admin/vehicles.php">Vehicles</a>
                <a href="<?php echo BASE_URL; ?>/admin/bids.php">Bids</a>
                <a href="<?php echo BASE_URL; ?>/admin/ads.php">Advertisements</a>
                <a href="<?php echo BASE_URL; ?>/admin/payments.php">Payments</a>
                <a href="<?php echo BASE_URL; ?>/logout.php?type=admin">Logout</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/index.php">Home</a>
                <a href="<?php echo BASE_URL; ?>/vehicles.php">Vehicles</a>
                <?php if (isUserLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>/user/my_wins.php">My Wins</a>
                    <a href="<?php echo BASE_URL; ?>/logout.php?type=user">Logout</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/register.php">Register</a>
                    <a href="<?php echo BASE_URL; ?>/login.php">Login</a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/admin/login.php">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container page">
    <?php foreach (getFlash('success') as $success): ?>
        <div class="alert alert-success"><?php echo esc($success); ?></div>
    <?php endforeach; ?>
    <?php foreach (getFlash('error') as $error): ?>
        <div class="alert alert-error"><?php echo esc($error); ?></div>
    <?php endforeach; ?>
