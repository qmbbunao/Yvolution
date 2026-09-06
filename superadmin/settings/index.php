<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/google_calendar.php';
require_role('superadmin');

$superAdmin = current_user();
$pdo = Database::connect();

$apiDefs = [
    'smtp'            => 'Gmail SMTP (PHPMailer) — order & quotation emails',
    'cloudinary'       => 'Cloudinary — design file & image uploads',
    'qr_server'        => 'QR Server API — order tracking QR codes',
    'nominatim'        => 'OpenStreetMap Nominatim + Leaflet — delivery address & map',
];

$googleClient = new GoogleCalendarClient();
$googleConnected = $googleClient->isConnected();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $apiName = $_POST['api_name'] ?? '';
    $action = $_POST['action'] ?? '';

    if (array_key_exists($apiName, $apiDefs) && in_array($action, ['activate', 'deactivate'], true)) {
        $newStatus = $action === 'activate' ? 'active' : 'inactive';
        $existing = $pdo->prepare("SELECT setting_id FROM api_settings WHERE api_name = ?");
        $existing->execute([$apiName]);

        if ($row = $existing->fetch()) {
            $pdo->prepare("UPDATE api_settings SET status = ?, updated_by = ? WHERE setting_id = ?")
                ->execute([$newStatus, $superAdmin['user_id'], $row['setting_id']]);
        } else {
            $pdo->prepare("INSERT INTO api_settings (api_name, config_json, status, updated_by) VALUES (?, '{}', ?, ?)")
                ->execute([$apiName, $newStatus, $superAdmin['user_id']]);
        }
        log_audit($pdo, $superAdmin['user_id'], 'api_setting_' . $action, 'api_settings', null, $apiName);
        set_flash('success', ucfirst($apiName) . ' integration ' . $newStatus . '.');
    }
    redirect('/superadmin/settings/index.php');
}

$statuses = $pdo->query("SELECT api_name, status FROM api_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$apiKeysConfig = require BASE_PATH . '/config/api_keys.php';

// Best-effort check whether each integration still has placeholder values
function looks_unconfigured(array $config): bool
{
    foreach ($config as $value) {
        if (is_string($value) && (str_starts_with($value, 'your-') || str_contains($value, 'yourbusinessemail'))) {
            return true;
        }
    }
    return false;
}

$pageTitle = 'API Settings — Super Admin';
$activeNav = 'settings';
include __DIR__ . '/../../includes/admin_header.php';
?>

<div class="admin-page-header"><h1>API Settings</h1></div>
<p style="color:#777;max-width:640px;margin-top:-16px;margin-bottom:24px;">
    Actual credentials live in <code>config/api_keys.php</code> on the server (never exposed here for security).
    Use this page to enable or disable each integration system-wide.
</p>

<div class="card" style="max-width:720px;margin-bottom:14px;">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <strong>Google Calendar API — production scheduling</strong>
            <div style="margin-top:6px;">
                <span class="badge <?= $googleConnected ? 'badge-success' : 'badge-danger' ?>"><?= $googleConnected ? 'Connected' : 'Not Connected' ?></span>
                <?php if (str_starts_with($apiKeysConfig['google_calendar']['client_id'], 'your-')): ?>
                    <span class="badge badge-pending" style="margin-left:6px;">Placeholder credentials — update config/api_keys.php</span>
                <?php endif; ?>
            </div>
            <p style="font-size:12px;color:#888;margin-top:6px;max-width:420px;">
                Once connected, setting an order's status to "In Production" with a production date
                will automatically create a calendar event for it.
            </p>
        </div>
        <?php if ($googleConnected): ?>
            <form method="POST" action="<?= BASE_URL ?>/superadmin/settings/google_disconnect.php" onsubmit="return confirm('Disconnect Google Calendar?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-sm" style="border-color:#b3261e;color:#b3261e;">Disconnect</button>
            </form>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/superadmin/settings/google_connect.php" class="btn btn-accent btn-sm">Connect Google Calendar</a>
        <?php endif; ?>
    </div>
</div>

<div style="display:flex;flex-direction:column;gap:14px;max-width:720px;">
    <?php foreach ($apiDefs as $key => $label):
        $status = $statuses[$key] ?? 'active';
        $unconfigured = looks_unconfigured($apiKeysConfig[$key] ?? []);
    ?>
        <div class="card"><div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <strong><?= e($label) ?></strong>
                <div style="margin-top:6px;">
                    <span class="badge <?= $status === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($status) ?></span>
                    <?php if ($unconfigured): ?>
                        <span class="badge badge-pending" style="margin-left:6px;">Placeholder credentials — update config/api_keys.php</span>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="api_name" value="<?= e($key) ?>">
                <button type="submit" name="action" value="<?= $status === 'active' ? 'deactivate' : 'activate' ?>" class="btn <?= $status === 'active' ? 'btn-outline' : 'btn-accent' ?> btn-sm" style="<?= $status === 'active' ? 'border-color:#b3261e;color:#b3261e;' : '' ?>">
                    <?= $status === 'active' ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
        </div></div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
