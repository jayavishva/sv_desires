<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$cart_count = 0;
if (isLoggedIn()) {
    $conn = getDBConnection();
    $cart_count = getCartCount($conn, $_SESSION['user_id']);
    //closeDBConnection($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>SV Mehendi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Top strip -->
    <div class="top-strip text-center text-white">
        <div class="container d-flex flex-column flex-md-row align-items-center justify-content-between">
            <span class="small">
                <i class="bi bi-stars me-1"></i>
                Premium henna products delivered across India
            </span>
            <span class="small mt-1 mt-md-0">
                <i class="bi bi-telephone me-1"></i> Support: +91 7639170568
            </span>
        </div>
    </div>

    <!-- Main marketplace-style header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary main-header sticky-top shadow-sm">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <span class="brand-icon d-inline-flex align-items-center justify-content-center me-2">
                    <i class="bi bi-flower1"></i>
                </span>
                <div class="d-flex flex-column">
                    <span class="brand-title">SV Mehendi</span>
                    <span class="brand-subtitle d-none d-sm-inline">Henna, cones & essential oils</span>
                </div>
            </a>

            <!-- Search (mobile first) -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <!-- Center search bar -->
                <form class="d-flex flex-grow-1 mx-lg-3 mt-3 mt-lg-0 header-search-bar" method="GET" action="index.php">
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control border-start-0"
                            name="search"
                            placeholder="Search for henna, cones, essential oils..."
                            value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                        >
                        <button class="btn btn-light d-none d-md-inline-flex" type="submit">Search</button>
                    </div>
                </form>

                <!-- Right actions -->
                <ul class="navbar-nav ms-lg-2 align-items-lg-center mt-3 mt-lg-0">
                    <!-- Category shortcuts -->
                    <li class="nav-item d-lg-none">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item d-lg-none">
                        <a class="nav-link" href="index.php?category=Henna Powder">Henna Powder</a>
                    </li>
                    <li class="nav-item d-lg-none">
                        <a class="nav-link" href="index.php?category=Cones">Cones</a>
                    </li>
                    <li class="nav-item d-lg-none">
                        <a class="nav-link" href="index.php?category=Accessories">Accessories</a>
                    </li>
                    <li class="nav-item d-lg-none mb-2">
                        <a class="nav-link" href="index.php?category=Essential oil">Essential oil</a>
                    </li>

                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item me-lg-2">
                            <a class="nav-link cart-link d-flex align-items-center" href="cart.php">
                                <span class="position-relative">
                                    <i class="bi bi-cart3 fs-5"></i>
                                    <?php if ($cart_count > 0): ?>
                                        <span class="cart-count-badge">
                                            <?php echo $cart_count; ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="ms-2 d-none d-md-inline">Cart</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown me-lg-2">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <span class="d-none d-md-inline">
                                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Account'); ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                                <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="admin/index.php">Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item me-lg-2">
                            <a class="btn btn-outline-light btn-sm me-2" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-light btn-sm" href="register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Desktop category strip -->
    <div class="category-strip d-none d-lg-block">
        <div class="container d-flex align-items-center gap-3 overflow-auto py-2">
            <a href="index.php" class="category-pill <?php echo !isset($_GET['category']) || $_GET['category'] === '' ? 'active' : ''; ?>">All Products</a>
            <a href="index.php?category=Henna Powder" class="category-pill <?php echo (($_GET['category'] ?? '') === 'Henna Powder') ? 'active' : ''; ?>">Henna Powder</a>
            <a href="index.php?category=Cones" class="category-pill <?php echo (($_GET['category'] ?? '') === 'Cones') ? 'active' : ''; ?>">Cones</a>
            <a href="index.php?category=Accessories" class="category-pill <?php echo (($_GET['category'] ?? '') === 'Accessories') ? 'active' : ''; ?>">Accessories</a>
            <a href="index.php?category=Essential oil" class="category-pill <?php echo (($_GET['category'] ?? '') === 'Essential oil') ? 'active' : ''; ?>">Essential oil</a>
        </div>
    </div>

    <main class="container my-4">


