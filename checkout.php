<?php
ob_start();
session_start();

require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'config/database.php';

requireLogin();

$conn = getDBConnection();
$count = getCartCount($conn, $_SESSION['user_id']);

if ($count == 0){
    redirect("cart.php");
}

$page_title = "Checkout";
require_once 'includes/header.php';

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.stock
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cart_items = $stmt->get_result();

if ($cart_items->num_rows === 0) {
    closeDBConnection($conn);
    redirect('cart.php');
}

// Get user info
$user = getUserById($conn, $_SESSION['user_id']);

$error = '';
$success = '';

// Process order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = sanitize($_POST['shipping_address'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? 'COD');
    
    // Validate payment method
    $allowed_payment_methods = ['COD', 'UPI', 'GPay', 'Card'];
    if (!in_array($payment_method, $allowed_payment_methods)) {
        $payment_method = 'COD';
    }
    
    if (empty($shipping_address) || empty($phone)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Verify cart items and stock
        $cart_items->data_seek(0);
        $valid = true;
        $total_amount = 0;
        $order_items = [];
        
        while ($item = $cart_items->fetch_assoc()) {
            if ($item['quantity'] > $item['stock']) {
                $error = "Insufficient stock for {$item['name']}. Available: {$item['stock']}";
                $valid = false;
                break;
            }
            $subtotal = $item['price'] * $item['quantity'];
            $total_amount += $subtotal;
            $order_items[] = $item;
        }
        
        if ($valid && $total_amount > 0) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Set payment status based on payment method
                $payment_status = ($payment_method === 'COD') ? 'pending' : 'pending';
                
                // Create order with selected payment method
                $stmt = $conn->prepare("
                    INSERT INTO orders (user_id, total_amount, status, shipping_address, phone, payment_method, payment_status) 
                    VALUES (?, ?, 'pending', ?, ?, ?, ?)
                ");
                $stmt->bind_param("idssss", $_SESSION['user_id'], $total_amount, $shipping_address, $phone, $payment_method, $payment_status);
                $stmt->execute();
                $order_id = $conn->insert_id;
                
                // Create order items
                foreach ($order_items as $item) {
                    // Insert order item
                    $stmt = $conn->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                    $stmt->execute();
                }
                
                // Handle payment method redirect
                if (in_array($payment_method, ['GPay', 'Card', 'UPI'])) {
                    // For online payments: Don't update stock or clear cart until payment is confirmed
                    // Commit transaction without updating stock
                    $conn->commit();
                    closeDBConnection($conn);
                    
                    // Store order ID in session for payment processing
                    $_SESSION['pending_order_id'] = $order_id;
                    redirect('payment_process.php?order_id=' . $order_id);
                } else {
                    // COD - Update stock, clear cart and redirect to orders
                    foreach ($order_items as $item) {
                        // Update product stock
                        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                        $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                        $stmt->execute();
                    }
                    
                    // Clear cart
                    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    
                    // Commit everything
                    $conn->commit();
                    closeDBConnection($conn);
                    redirect('orders.php?order_id=' . $order_id);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Order placement failed. Please try again.';
            }
        }
    }
}

// Re-fetch cart items for display
$cart_items->data_seek(0);
$cart_total = getCartTotal($conn, $_SESSION['user_id']);

closeDBConnection($conn);
?>

<h2 class="mb-2">Checkout</h2>
<p class="text-muted small mb-3">
    Confirm your delivery details and choose a payment method to place your order.
</p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="checkout-steps">
    <div class="checkout-step active">
        <span>1</span> Delivery Address
    </div>
    <div class="checkout-step active">
        <span>2</span> Payment
    </div>
    <div class="checkout-step">
        <span>3</span> Order Confirmation
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card checkout-card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Delivery & Payment</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        <div class="form-text">We will use this to share order and delivery updates.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="shipping_address" class="form-label">Shipping Address *</label>
                        <textarea class="form-control" id="shipping_address" name="shipping_address" 
                                  rows="4" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        <div class="form-text">Include house number, street, area and pincode for faster delivery.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="GPay" selected>Google Pay (GPay)</option>
                            <option value="UPI">UPI</option>
                        </select>
                        <small class="text-muted">
                            <span id="payment_method_info">You will complete payment on the next page.</span>
                        </small>
                    </div>
                    
                    <div id="online_payment_info" class="alert alert-info" style="display: none;">
                        <i class="bi bi-info-circle"></i> You will be redirected to a secure payment page after placing your order.
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg w-100" id="submit_btn">
                        <span id="submit_text">Continue to Payment</span>
                    </button>
                </form>
                
                <script>
                // Show/hide info based on payment method
                document.getElementById('payment_method').addEventListener('change', function() {
                    const paymentMethod = this.value;
                    const infoText = document.getElementById('payment_method_info');
                    const onlineInfo = document.getElementById('online_payment_info');
                    const submitBtn = document.getElementById('submit_btn');
                    const submitText = document.getElementById('submit_text');
                    
                    if (paymentMethod === 'COD') {
                        infoText.textContent = 'Pay cash when your order is delivered.';
                        onlineInfo.style.display = 'none';
                        submitText.textContent = 'Place Order';
                        submitBtn.className = 'btn btn-primary btn-lg w-100';
                    } else {
                        infoText.textContent = 'You will complete payment on the next page.';
                        onlineInfo.style.display = 'block';
                        submitText.textContent = 'Continue to Payment';
                        submitBtn.className = 'btn btn-success btn-lg w-100';
                    }
                });
                </script>
                <script src="assets/js/payment.js"></script>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card checkout-card">
            <div class="card-header">
                <h4 class="mb-0">Order Summary</h4>
            </div>
            <div class="card-body">
                <div class="order-summary-mini mb-3">
                    <?php 
                    $cart_items->data_seek(0);
                    while ($item = $cart_items->fetch_assoc()): 
                        $subtotal = $item['price'] * $item['quantity'];
                    ?>
                        <div class="order-summary-mini-item">
                            <span><?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?></span>
                            <span><?php echo formatPrice($subtotal); ?></span>
                        </div>
                    <?php endwhile; ?>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-1 small text-muted">
                    <span>Items total</span>
                    <span><?php echo formatPrice($cart_total); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1 small text-muted">
                    <span>Delivery</span>
                    <span class="text-success">Free</span>
                </div>
                <div class="d-flex justify-content-between mt-2 order-summary-total">
                    <span>Amount payable</span>
                    <span><?php echo formatPrice($cart_total); ?></span>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    <i class="bi bi-shield-check me-1"></i>
                    Payments are processed securely. You’ll see UPI or GPay options on the next step.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
