<?php
session_start();
require 'includes/functions.php';

if (!isLoggedIn()) {
    redirect("login.php");
}
$page_title = 'Shopping Cart';
require_once 'includes/header.php';
requireLogin();

$conn = getDBConnection();
$error = '';
$success = '';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        $cart_id = intval($_POST['cart_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity < 1) {
            // Remove item
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $cart_id, $_SESSION['user_id']);
            $stmt->execute();
            $success = 'Item removed from cart.';
        } else {
            // Check stock
            $stmt = $conn->prepare("
                SELECT p.stock, c.product_id 
                FROM cart c 
                JOIN products p ON c.product_id = p.id 
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->bind_param("ii", $cart_id, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $item = $result->fetch_assoc();
                if ($quantity > $item['stock']) {
                    $error = 'Insufficient stock. Available: ' . $item['stock'];
                } else {
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("iii", $quantity, $cart_id, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $success = 'Cart updated successfully!';
                    } else {
                        $error = 'Failed to update cart.';
                    }
                }
            }
        }
    } elseif (isset($_POST['remove_item'])) {
        $cart_id = intval($_POST['cart_id']);
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $success = 'Item removed from cart.';
        } else {
            $error = 'Failed to remove item.';
        }
    }
}

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image_path, p.image_url, p.stock, p.quantity_value, p.quantity_unit
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cart_items = $stmt->get_result();

$cart_total = getCartTotal($conn, $_SESSION['user_id']);

closeDBConnection($conn);
?>

<h2 class="mb-3">My Cart</h2>
<p class="text-muted mb-4 small">
    Review your items and update quantities before checkout.
</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($cart_items->num_rows > 0): ?>
    <div class="cart-layout">
        <!-- Left: cart items -->
        <div>
            <?php while ($item = $cart_items->fetch_assoc()): ?>
                <?php 
                $subtotal = $item['price'] * $item['quantity'];
                $image_url = !empty($item['image_path']) ? $item['image_path'] : 
                             (!empty($item['image_url']) ? $item['image_url'] : 'assets/images/default-product.jpg');
                ?>
                <div class="cart-item-card mb-3 fade-in">
                    <div>
                        <img
                            src="<?php echo htmlspecialchars($image_url); ?>"
                            alt="<?php echo htmlspecialchars($item['name']); ?>"
                            class="cart-thumbnail"
                            onerror="this.src='assets/images/default-product.jpg'"
                        >
                    </div>
                    <div class="flex-grow-1 cart-item-info">
                        <h5><?php echo htmlspecialchars($item['name']); ?></h5>
                        <div class="cart-meta mb-1">
                            <?php 
                            $quantity_display = formatQuantity($item['quantity_value'] ?? null, $item['quantity_unit'] ?? null);
                            if ($quantity_display): ?>
                                <span class="me-2 text-info">
                                    Pack: <?php echo htmlspecialchars($quantity_display); ?>
                                </span>
                            <?php endif; ?>
                            <span>Stock: <?php echo $item['stock']; ?></span>
                        </div>
                        <?php if ($item['quantity'] > $item['stock']): ?>
                            <div class="small text-danger mb-1">
                                Only <?php echo $item['stock']; ?> available
                            </div>
                        <?php endif; ?>

                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <div class="d-flex align-items-center">
                                <span class="cart-price me-3">
                                    <?php echo formatPrice($item['price']); ?>
                                </span>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <div class="input-group" style="width: 140px;">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="this.nextElementSibling.stepDown(); this.form.submit();">
                                            -
                                        </button>
                                        <input
                                            type="number"
                                            name="quantity"
                                            class="form-control form-control-sm text-center"
                                            value="<?php echo $item['quantity']; ?>"
                                            min="1"
                                            max="<?php echo $item['stock']; ?>"
                                            required
                                        >
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="this.previousElementSibling.stepUp(); this.form.submit();">
                                            +
                                        </button>
                                        <button type="submit" name="update_cart" class="btn btn-sm btn-outline-primary ms-2 d-none d-md-inline-flex">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">Subtotal</div>
                                <div class="fw-semibold">
                                    <?php echo formatPrice($subtotal); ?>
                                </div>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <button
                                        type="submit"
                                        name="remove_item"
                                        class="btn btn-link btn-sm text-danger p-0 mt-1"
                                        onclick="return confirm('Remove this item from cart?')"
                                    >
                                        <i class="bi bi-trash"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>

            <div class="d-flex justify-content-between mt-3">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Continue shopping
                </a>
                <a href="checkout.php" class="btn btn-primary">
                    Proceed to checkout
                </a>
            </div>
        </div>

        <!-- Right: summary -->
        <aside>
            <div class="cart-summary-card">
                <div class="cart-summary-title mb-3">Price Details</div>
                <div class="cart-summary-row">
                    <span>Items</span>
                    <span><?php echo $cart_items->num_rows; ?></span>
                </div>
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span><?php echo formatPrice($cart_total); ?></span>
                </div>
                <div class="cart-summary-row">
                    <span>Delivery</span>
                    <span class="text-success">Free</span>
                </div>
                <div class="cart-summary-row cart-summary-total">
                    <span>Total Amount</span>
                    <span><?php echo formatPrice($cart_total); ?></span>
                </div>
                <p class="cart-summary-note mt-2 mb-3">
                    You will see available payment options on the next step.
                </p>
                <a href="checkout.php" class="btn btn-success w-100 mb-2">
                    Place order
                </a>
                <p class="small text-muted mb-0">
                    <i class="bi bi-shield-check me-1"></i>
                    Safe and secure payments.
                </p>
            </div>
        </aside>
    </div>
<?php else: ?>
    <div class="alert alert-info text-center">
        <h4>Your cart is empty</h4>
        <p>Start shopping to add items to your cart.</p>
        <a href="index.php" class="btn btn-primary">Browse Products</a>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
