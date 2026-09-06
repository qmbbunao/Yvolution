<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$promoId = (int) ($_GET['id'] ?? 0);

if ($promoId) {
    $pdo->prepare("DELETE FROM promotions WHERE promo_id = ?")->execute([$promoId]);
    log_audit($pdo, $admin['user_id'], 'promotion_deleted', 'promotions', $promoId);
    set_flash('success', 'Promotion deleted.');
}

redirect('/admin/promotions/index.php');
