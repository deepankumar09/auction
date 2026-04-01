<?php
declare(strict_types=1);

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

$pageTitle = $pageTitle ?? APP_NAME;
$isAdminArea = str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/');
$isAdminLoginPage = str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/login.php');
$cssPath = ROOT_PATH . '/assets/css/style.css';
$cssVersion = is_file($cssPath) ? (string)filemtime($cssPath) : '1';
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc($pageTitle); ?> | <?php echo esc(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo esc($cssVersion); ?>">
</head>
<body>
<header class="topbar">
    <div class="container nav-wrap">
        <a class="brand" href="<?php echo BASE_URL; ?>/index.php">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="Logo">
            <span class="brand-title">Bank Auction</span>
        </a>
        <nav>
            <?php if ($isAdminArea && isAdminLoggedIn()): ?>
                <a class="<?php echo $currentPage === 'dashboard.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">Dashboard</a>
                        <a class="<?php echo $currentPage === 'add_vehicle.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/add_vehicle.php">Add Vehicles</a>
                        <a class="<?php echo $currentPage === 'defaulters.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/defaulters.php">Defaulters</a>
                        <a class="<?php echo $currentPage === 'defaulter_records.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/defaulter_records.php">Defaulter Records</a>
                        <a class="<?php echo $currentPage === 'manage_bids.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/manage_bids.php">Bids</a>
                        <a class="<?php echo $currentPage === 'complaints.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/complaints.php">Complaints</a>
                        <a class="<?php echo $currentPage === 'vehicles.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/vehicles.php">Manage Auction</a>
                        <a class="<?php echo $currentPage === 'payments.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/payments.php">Payments</a>
                  <a class="<?php echo $currentPage === 'ads.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/ads.php">Advertisements</a>
                <a href="<?php echo BASE_URL; ?>/logout.php?type=admin">Logout</a>
            <?php elseif ($isAdminArea): ?>
                
            <?php else: ?>
                <?php if (isUserLoggedIn()): ?>
                    <?php if ($currentPage !== 'invoice.php'): ?>
                        <a class="<?php echo $currentPage === 'dashboard.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/user/dashboard.php">Dashboard</a>
                        <a class="<?php echo $currentPage === 'vehicles.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/vehicles.php">Vehicles</a>
                        <a class="<?php echo $currentPage === 'place_bids.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/place_bids.php">Place Bids</a>
                        <a class="<?php echo $currentPage === 'complaints.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/user/complaints.php">Complaints</a>
                        <a class="<?php echo $currentPage === 'my_wins.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/user/my_wins.php">My Wins</a>
                        <a href="<?php echo BASE_URL; ?>/logout.php?type=user">Logout</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="<?php echo $currentPage === 'index.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php">Home</a>
                    <a class="<?php echo $currentPage === 'vehicles.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/vehicles.php">Vehicles</a>
                    <a class="<?php echo $currentPage === 'register.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/register.php">Register</a>
                    <a class="<?php echo $currentPage === 'login.php' ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/login.php">Login</a>
                    <a class="<?php echo $currentPage === 'login.php' && $isAdminLoginPage ? 'is-current' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/login.php">Admin</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container page">
    <?php
    $successMessages = getFlash('success');
    $errorMessages = getFlash('error');
    ?>
    <?php if ($successMessages || $errorMessages): ?>
        <div class="flash-toast-wrap" data-flash-wrap>
            <?php foreach ($successMessages as $success): ?>
                <div class="alert alert-success flash-toast" data-flash-toast><?php echo esc($success); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errorMessages as $error): ?>
                <div class="alert alert-error flash-toast" data-flash-toast><?php echo esc($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
