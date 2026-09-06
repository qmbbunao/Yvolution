<?php
require_once __DIR__ . '/../../config/app.php';
require_role('customer');

$user = current_user();
$pdo = Database::connect();

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$account = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    $errors[] = 'Invalid session token. Please try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    } else {
        $pdo->prepare(
            "UPDATE users SET first_name=?, last_name=?, phone=?, address=? WHERE user_id=?"
        )->execute([$firstName, $lastName, $phone, $address, $user['user_id']]);

        $_SESSION['user']['name'] = $firstName . ' ' . $lastName;
        set_flash('success', 'Profile updated successfully.');
        redirect('/customer/profile/edit.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'home_address') {
    $homeAddress = trim($_POST['home_address'] ?? '');
    $homeLat = $_POST['home_lat'] !== '' ? (float) $_POST['home_lat'] : null;
    $homeLng = $_POST['home_lng'] !== '' ? (float) $_POST['home_lng'] : null;

    if ($homeAddress === '') {
        $errors[] = 'Please search for and pin your address first.';
    } else {
        $pdo->prepare(
            "UPDATE users SET home_address=?, home_lat=?, home_lng=? WHERE user_id=?"
        )->execute([$homeAddress, $homeLat, $homeLng, $user['user_id']]);
        set_flash('success', 'Home address saved.');
        redirect('/customer/profile/edit.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $account['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['user_id']]);
        log_audit($pdo, $user['user_id'], 'password_changed');
        set_flash('success', 'Password updated successfully.');
        redirect('/customer/profile/edit.php');
    }
}

$pageTitle = 'My Profile — Yvolution Custom Apparel';
include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container" style="padding:50px 24px;max-width:600px;">
    <h1>My Profile</h1>

    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($msg = get_flash('success')): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Profile Information</h3>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="profile">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input class="form-control" type="text" id="first_name" name="first_name" value="<?= e($account['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input class="form-control" type="text" id="last_name" name="last_name" value="<?= e($account['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input class="form-control" type="email" value="<?= e($account['email']) ?>" disabled style="background:#f2f2f2;">
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input class="form-control" type="text" id="phone" name="phone" value="<?= e($account['phone']) ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address (text only)</label>
                    <input class="form-control" type="text" id="address" name="address" value="<?= e($account['address']) ?>">
                </div>
                <button type="submit" class="btn btn-accent">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Home Address (Pinned Location)</h3>
            <p style="font-size:13px;color:#777;">Save your exact home location once, then reuse it with one click when placing future orders.</p>

            <form method="POST" id="homeAddressForm">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="home_address">

                <div class="form-group">
                    <label for="home_address_input">Search Address</label>
                    <div style="display:flex;gap:8px;">
                        <input class="form-control" type="text" id="home_address_input" name="home_address" value="<?= e($account['home_address'] ?? '') ?>" placeholder="Street, Barangay, City">
                        <button type="button" id="locateHomeBtn" class="btn btn-dark btn-sm" style="white-space:nowrap;">Locate</button>
                    </div>
                    <p style="font-size:12px;color:#777;margin-top:6px;">Search for an address, then click or drag the pin on the map to confirm the exact location.</p>
                    <p id="homeGeocodeStatus" style="font-size:12px;color:#888;margin-top:6px;"></p>
                </div>

                <div id="homeMap" style="height:260px;border-radius:var(--radius-sm);<?= $account['home_lat'] ? '' : 'display:none;' ?>"></div>

                <input type="hidden" id="home_lat" name="home_lat" value="<?= e((string) ($account['home_lat'] ?? '')) ?>">
                <input type="hidden" id="home_lng" name="home_lng" value="<?= e((string) ($account['home_lng'] ?? '')) ?>">

                <button type="submit" class="btn btn-accent" style="margin-top:14px;">Save Home Address</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <div class="card-body">
            <h3 style="font-size:16px;">Change Password</h3>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="password">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input class="form-control" type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input class="form-control" type="password" id="new_password" name="new_password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                </div>
                <button type="submit" class="btn btn-dark">Update Password</button>
            </form>
        </div>
    </div>
</div>

<script>
let homeMap, homeMarker;

function updateHomeCoordinates(lat, lng, message) {
    document.getElementById('home_lat').value = lat;
    document.getElementById('home_lng').value = lng;
    document.getElementById('homeGeocodeStatus').textContent = message;
}

function initializeHomeMap(lat, lng) {
    const mapDiv = document.getElementById('homeMap');
    mapDiv.style.display = 'block';

    if (!homeMap) {
        homeMap = L.map('homeMap').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(homeMap);

        homeMarker = L.marker([lat, lng], { draggable: true }).addTo(homeMap);
        homeMarker.on('dragend', function () {
            const pos = homeMarker.getLatLng();
            updateHomeCoordinates(pos.lat, pos.lng, 'Pin moved. You can save this location.');
        });

        homeMap.on('click', function (event) {
            const pos = event.latlng;
            homeMarker.setLatLng(pos);
            updateHomeCoordinates(pos.lat, pos.lng, 'Pin placed on the map. You can save this location.');
        });
    } else {
        homeMap.setView([lat, lng], 15);
        homeMarker.setLatLng([lat, lng]);
    }
}

<?php if ($account['home_lat'] && $account['home_lng']): ?>
    initializeHomeMap(<?= (float) $account['home_lat'] ?>, <?= (float) $account['home_lng'] ?>);
<?php endif; ?>

document.getElementById('home_address_input').addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        document.getElementById('locateHomeBtn').click();
    }
});

document.getElementById('locateHomeBtn').addEventListener('click', async () => {
    const address = document.getElementById('home_address_input').value.trim();
    const status = document.getElementById('homeGeocodeStatus');
    if (!address) {
        status.textContent = 'Enter an address first.';
        return;
    }
    status.textContent = 'Searching...';

    try {
        const res = await fetch(`<?= BASE_URL ?>/customer/orders/geocode_ajax.php?address=${encodeURIComponent(address)}`);
        const data = await res.json();
        if (!data.success) {
            status.textContent = data.message;
            return;
        }

        updateHomeCoordinates(data.lat, data.lng, 'Found: ' + data.display_name);
        initializeHomeMap(data.lat, data.lng);
    } catch (err) {
        status.textContent = 'Something went wrong. Please try again.';
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
