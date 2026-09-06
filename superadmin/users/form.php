<?php
require_once __DIR__ . '/../../config/app.php';
require_role('superadmin');

$pdo = Database::connect();
$userId = (int) ($_GET['id'] ?? 0);
$account = null;

if ($userId) {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?");
    $stmt->execute([$userId]);
    $account = $stmt->fetch();
    if (!$account || $account['role_name'] === 'customer') {
        set_flash('error', 'Account not found.');
        redirect('/superadmin/users/index.php');
    }
}

$pageTitle = ($account ? 'Edit' : 'Add') . ' Admin — Super Admin';
$activeNav = 'users';
include __DIR__ . '/../../includes/admin_header.php';
?>
<a href="<?= BASE_URL ?>/superadmin/users/index.php" class="text-link" style="font-size:13px;">&larr; Back to Users</a>
<h1 style="margin-top:12px;"><?= $account ? 'Edit Account' : 'Add Admin / Super Admin' ?></h1>

<div class="card" style="max-width:520px;margin-top:20px;">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/superadmin/users/save.php">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) ($account['user_id'] ?? 0) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input class="form-control" type="text" id="first_name" name="first_name" value="<?= e($account['first_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input class="form-control" type="text" id="last_name" name="last_name" value="<?= e($account['last_name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($account['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="role_name">Role</label>
                <select class="form-control" id="role_name" name="role_name">
                    <option value="admin" <?= ($account['role_name'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superadmin" <?= ($account['role_name'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password"><?= $account ? 'New Password (leave blank to keep current)' : 'Temporary Password' ?></label>
                <input class="form-control" type="password" id="password" name="password" minlength="8" <?= $account ? '' : 'required' ?>>
            </div>

            <button type="submit" class="btn btn-accent"><?= $account ? 'Save Changes' : 'Create Account' ?></button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
