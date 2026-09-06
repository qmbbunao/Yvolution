<?php
require_once __DIR__ . '/../../config/app.php';
require_role('admin', 'superadmin');

$pdo = Database::connect();
$items = $pdo->query("SELECT * FROM inventory ORDER BY item_name")->fetchAll();

$pageTitle = 'Inventory — Admin';
$activeNav = 'inventory';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header">
    <h1>Inventory</h1>
    <button type="button" class="btn btn-accent" onclick="document.getElementById('addItemModal').style.display='flex'">+ Add Item</button>
</div>

<div class="data-table-wrap">
    <table class="data-table">
        <thead><tr><th>Item</th><th>SKU</th><th>Category</th><th>On Hand</th><th>Reorder Level</th><th>Adjust Stock</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i):
            $low = $i['quantity_on_hand'] <= $i['reorder_level'];
        ?>
            <tr>
                <td><strong><?= e($i['item_name']) ?></strong></td>
                <td style="color:#888;font-size:13px;"><?= e($i['sku'] ?: '—') ?></td>
                <td><?= e($i['category'] ?: '—') ?></td>
                <td>
                    <span style="<?= $low ? 'color:#b3261e;font-weight:700;' : '' ?>"><?= (int) $i['quantity_on_hand'] ?> <?= e($i['unit']) ?></span>
                    <?php if ($low): ?><span class="badge badge-danger" style="margin-left:6px;">Low</span><?php endif; ?>
                </td>
                <td><?= (int) $i['reorder_level'] ?></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/admin/inventory/adjust.php" style="display:flex;gap:6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="inventory_id" value="<?= (int) $i['inventory_id'] ?>">
                        <input class="form-control" type="number" name="change_qty" placeholder="+/- qty" style="width:100px;" required>
                        <button type="submit" class="btn btn-dark btn-sm">Apply</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" class="empty-state">No inventory items yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Item Modal -->
<div id="addItemModal" style="display:none;position:fixed;inset:0;background:rgba(10,14,26,0.6);align-items:center;justify-content:center;z-index:200;">
    <div class="card" style="width:100%;max-width:420px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Add Inventory Item</h3>
            <form method="POST" action="<?= BASE_URL ?>/admin/inventory/save.php">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="item_name">Item Name</label>
                    <input class="form-control" type="text" id="item_name" name="item_name" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="sku">SKU</label>
                        <input class="form-control" type="text" id="sku" name="sku">
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input class="form-control" type="text" id="category" name="category" placeholder="e.g. Blanks, Ink">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_on_hand">Initial Qty</label>
                        <input class="form-control" type="number" id="quantity_on_hand" name="quantity_on_hand" value="0">
                    </div>
                    <div class="form-group">
                        <label for="reorder_level">Reorder Level</label>
                        <input class="form-control" type="number" id="reorder_level" name="reorder_level" value="10">
                    </div>
                    <div class="form-group">
                        <label for="unit">Unit</label>
                        <input class="form-control" type="text" id="unit" name="unit" value="pcs">
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button type="submit" class="btn btn-accent">Add Item</button>
                    <button type="button" class="btn btn-outline" style="border-color:#ccc;color:#333;" onclick="document.getElementById('addItemModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
