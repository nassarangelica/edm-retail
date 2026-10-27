<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('customer');
$pageTitle = 'Checkout';
if (empty($_SESSION['cart'])) { flash('warning', 'Your cart is empty.'); redirect('products.php'); }

$st = db()->prepare('SELECT * FROM customers WHERE id = ?');
$st->execute([user()['customer_id']]);
$cust = $st->fetch();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $address = trim($_POST['address'] ?? '');
    $pay = $_POST['payment_method'] ?? '';
    if (strlen($address) < 8) $errors[] = 'Please enter a complete delivery address.';
    if (!in_array($pay, ['cod', 'gcash_sim', 'card_sim'], true)) $errors[] = 'Choose a payment method.';
    if (!$errors) {
        try {
            $orderId = create_order([
                'channel' => 'online', 'customer_id' => (int)user()['customer_id'],
                'items' => $_SESSION['cart'], 'payment_method' => $pay, 'shipping_address' => $address,
            ]);
            db()->prepare('UPDATE customers SET address = COALESCE(NULLIF(address, ""), ?) WHERE id = ?')->execute([$address, user()['customer_id']]);
            $_SESSION['cart'] = [];
            flash('success', 'Order placed! Thank you for shopping with us.');
            redirect('orders.php?id=' . $orderId);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4" style="max-width:720px">
  <h2 class="mb-3">Checkout</h2>
  <?php foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="card card-body"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Delivery address</label>
      <textarea name="address" class="form-control" rows="2" required><?= e($_POST['address'] ?? $cust['address'] ?? '') ?></textarea></div>
    <div class="mb-3"><label class="form-label">Payment method</label>
      <select name="payment_method" class="form-select" required>
        <option value="cod">Cash on delivery</option>
        <option value="gcash_sim">E-wallet (simulated)</option>
        <option value="card_sim">Card (simulated)</option>
      </select>
      <div class="form-text"><i class="bi bi-shield-lock"></i> PCI DSS: this demo never asks for or stores card numbers. A real system would redirect to a certified payment gateway.</div></div>
    <button class="btn btn-primary">Place order</button>
    <a href="<?= url('cart.php') ?>" class="btn btn-link">Back to cart</a>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
