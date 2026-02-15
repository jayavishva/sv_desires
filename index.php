<?php
$page_title = 'Home';
require_once 'includes/header.php';

$conn = getDBConnection();

// Get search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');

// Build query
$query = "SELECT * FROM products WHERE status = 'active'";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Get categories for filter
$categories_result = $conn->query("SELECT DISTINCT category FROM products WHERE status = 'active' AND category IS NOT NULL");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}

closeDBConnection($conn);
?>

<!-- Hero section -->
<section class="home-hero fade-in mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <h1 class="home-hero-title mb-2">
                Discover premium henna, cones & essential oils.
            </h1>
            <p class="home-hero-subtitle mb-3">
                Everything you need for stunning mehendi designs – from finely milled henna powder to ready-to-use cones and nourishing essential oils.
            </p>
            <div class="home-hero-badges mb-3">
                <span><i class="bi bi-check-circle me-1 text-success"></i> 100% quality-checked products</span>
                <span><i class="bi bi-truck me-1 text-primary"></i> Fast shipping</span>
                <span><i class="bi bi-shield-check me-1 text-warning"></i> Secure checkout</span>
            </div>
            <a href="#products-grid" class="btn btn-primary btn-lg mt-1">
                Shop bestsellers
            </a>
        </div>
        <div class="col-lg-5 home-hero-visual d-none d-lg-block">
            <div class="home-hero-card">
                <h5 class="mb-1">Today’s spotlight</h5>
                <p class="small text-muted mb-2">
                    Rich color, smooth application, and long-lasting stains.
                </p>
                <div class="home-hero-pills mb-2">
                    <span>Henna Powder</span>
                    <span>Cones</span>
                    <span>Essential Oils</span>
                </div>
                <p class="mb-0 small text-muted">
                    Browse our latest arrivals and festival picks curated just for you.
                </p>
            </div>
        </div>
    </div>
</section>



<!-- Products Grid -->
<section id="products-grid" class="mb-4">
<div class="row">
    <?php if ($products->num_rows > 0): ?>
        <?php while ($product = $products->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-md">
                    <?php 
                    $image_url = !empty($product['image_path']) ? $product['image_path'] : 
                                 (!empty($product['image_url']) ? $product['image_url'] : 'assets/images/default-product.jpg');
                    ?>
                    <img src="<?php echo htmlspecialchars($image_url); ?>" 
                         class="card-img-top product-image" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         onerror="this.src='assets/images/default-product.jpg'">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="product-name-icon me-2"
                                 style="width: 24px; height: 24px; object-fit: cover; border-radius: 4px; vertical-align: middle;"
                                 onerror="this.style.display='none';">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h5>
                        <p class="card-text text-muted small">
                            <?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>
                            <?php echo strlen($product['description']) > 100 ? '...' : ''; ?>
                        </p>
                        <div class="mt-auto">
                            <p class="h5 text-primary mb-2"><?php echo formatPrice($product['price']); ?></p>
                            <?php 
                            $quantity_display = formatQuantity($product['quantity_value'] ?? null, $product['quantity_unit'] ?? null);
                            if ($quantity_display): ?>
                                <p class="small text-info mb-1">
                                    <strong>Quantity:</strong> <?php echo htmlspecialchars($quantity_display); ?>
                                </p>
                            <?php endif; ?>
                            <p class="small text-muted mb-2">
                                Stock: <?php echo $product['stock']; ?> | 
                                Category: <?php echo htmlspecialchars($product['category']); ?>
                            </p>
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center">
                <h4>No products found</h4>
                <p>Try adjusting your search or filter criteria.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
</section>

<?php require_once 'includes/footer.php'; ?>