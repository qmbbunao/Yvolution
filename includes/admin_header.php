<?php
/**
 * Shared Admin/Super Admin layout header. Include after require_role().
 * Set $pageTitle and $activeNav before including.
 * $activeNav one of: dashboard, orders, products, services, packages,
 * promotions, inventory, size_guides, customers, homepage, users, settings, analytics, audit
 */
$user = current_user();
$pageTitle = $pageTitle ?? 'Admin — Yvolution Custom Apparel';
$activeNav = $activeNav ?? '';

$navItems = [
    ['key' => 'dashboard',  'label' => 'Dashboard',   'href' => '/admin/dashboard.php',        'roles' => ['admin','superadmin']],
    ['key' => 'orders',     'label' => 'Orders',      'href' => '/admin/orders/index.php',     'roles' => ['admin','superadmin']],
    ['key' => 'inquiries',  'label' => 'Inquiries',   'href' => '/admin/inquiries/index.php',  'roles' => ['admin','superadmin']],
    ['key' => 'returns',    'label' => 'Returns & Refunds', 'href' => '/admin/returns/index.php', 'roles' => ['admin','superadmin']],
    ['key' => 'products',   'label' => 'Products',    'href' => '/admin/products/index.php',   'roles' => ['admin','superadmin']],
    ['key' => 'services',   'label' => 'Services',    'href' => '/admin/services/index.php',   'roles' => ['admin','superadmin']],
    ['key' => 'packages',   'label' => 'Packages',    'href' => '/admin/packages/index.php',   'roles' => ['admin','superadmin']],
    ['key' => 'design_templates', 'label' => 'Design Templates', 'href' => '/admin/design_templates/index.php', 'roles' => ['admin','superadmin']],
    ['key' => 'size_guides', 'label' => 'Size Guides', 'href' => '/admin/size_guides/index.php', 'roles' => ['admin','superadmin']],
    ['key' => 'promotions', 'label' => 'Promotions',  'href' => '/admin/promotions/index.php', 'roles' => ['admin','superadmin']],
    ['key' => 'inventory',  'label' => 'Inventory',   'href' => '/admin/inventory/index.php',  'roles' => ['admin','superadmin']],
    ['key' => 'customers',  'label' => 'Customers',   'href' => '/admin/customers/index.php',  'roles' => ['admin','superadmin']],
    ['key' => 'homepage',   'label' => 'Homepage CMS','href' => '/admin/homepage/content.php', 'roles' => ['admin','superadmin']],
    ['key' => 'users',      'label' => 'Users & Roles','href' => '/superadmin/users/index.php','roles' => ['superadmin']],
    ['key' => 'settings',   'label' => 'API Settings','href' => '/superadmin/settings/index.php','roles' => ['superadmin']],
    ['key' => 'analytics',  'label' => 'Analytics',   'href' => '/superadmin/analytics/index.php','roles' => ['superadmin']],
    ['key' => 'audit',      'label' => 'Audit Logs',  'href' => '/superadmin/analytics/audit_logs.php','roles' => ['superadmin']],
    ['key' => 'backups',    'label' => 'Backups',     'href' => '/superadmin/backups/index.php','roles' => ['superadmin']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/assets/css/main.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="<?= BASE_URL ?>/public/index.php" class="admin-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo/logo2.png" alt="Yvolution logo">
            YVOLUTION
        </a>
        <nav class="admin-nav-scroll">
            <?php foreach ($navItems as $item): ?>
                <?php if (in_array($user['role'], $item['roles'], true)): ?>
                    <?php if ($item['key'] === 'users'): ?>
                        <div class="admin-nav-group-label">System</div>
                    <?php endif; ?>
                    <a href="<?= BASE_URL . $item['href'] ?>" class="admin-nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>">
                        <?= e($item['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-footer" id="adminUserMenu">
            <div class="admin-user-popout">
                <a href="<?= BASE_URL ?>/public/index.php">View Site</a>
                <a href="#" class="danger" onclick="openLogoutModal(event)">Log Out</a>
            </div>
            <button type="button" class="admin-user-chip" id="adminUserChip">
                <span>
                    <strong><?= e($user['name']) ?></strong>
                    <span class="role"><?= e(ucfirst($user['role'])) ?></span>
                </span>
                <span class="chevron">&#9662;</span>
            </button>
        </div>
    </aside>
    <main class="admin-main">
        <?php if ($msg = get_flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($msg = get_flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
