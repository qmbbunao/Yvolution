<?php
/**
 * Shared public header. Include after config/app.php has been loaded.
 * Optional $pageTitle variable can be set before including this file.
 */
$user = current_user();
$pageTitle = $pageTitle ?? 'Yvolution Custom Apparel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/images/logo/logo2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_url('/assets/css/main.css') ?>">
<script>
(function () {
    try {
        var theme = localStorage.getItem('yvolution-theme') || 'light';
        document.documentElement.classList.add(theme === 'dark' ? 'theme-dark' : 'theme-light');
    } catch (e) {}
})();
</script>
</head>
<body>
<header class="navbar">
    <div class="navbar-inner">
        <a href="<?= BASE_URL ?>/public/index.php" class="navbar-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo/logo.png" alt="Yvolution Custom Apparel logo">
            YVOLUTION
        </a>
        <button class="navbar-toggle" id="navToggle" aria-label="Toggle menu">&#9776;</button>
        <nav class="navbar-links" id="navLinks">
            <form class="catalog-search" action="<?= BASE_URL ?>/public/search.php" method="GET" role="search" autocomplete="off">
                <button type="button" class="search-toggle" id="searchToggle" aria-label="Open product search" aria-expanded="false">
                    <span class="search-icon" aria-hidden="true"></span>
                </button>
                <div class="catalog-search-field">
                    <label class="sr-only" for="catalogSearchInput">Search products</label>
                    <input id="catalogSearchInput" type="text" name="q" placeholder="Search products" value="<?= e($_GET['q'] ?? '') ?>">
                </div>
                <div class="catalog-search-suggestions" id="catalogSearchSuggestions" hidden></div>
            </form>
            <a href="<?= BASE_URL ?>/public/index.php#products">Products</a>
            <a href="<?= BASE_URL ?>/public/index.php#services">Services</a>
            <a href="<?= BASE_URL ?>/public/index.php#packages">Packages</a>
            <a href="<?= BASE_URL ?>/public/index.php#promotions">Promotions</a>
            <a href="<?= BASE_URL ?>/public/index.php#contact">Contact</a>

            <?php if (!$user): ?>
                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline btn-sm">Log In</a>
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-accent btn-sm">Sign Up</a>
            <?php else: ?>
                <div class="account-menu" id="accountMenu">
                    <button type="button" class="account-menu-trigger" id="accountMenuTrigger">
                        <?= e(explode(' ', $user['name'])[0]) ?>
                        <span class="chevron">&#9662;</span>
                    </button>
                    <div class="account-menu-dropdown">
                        <?php if ($user['role'] === 'customer'): ?>
                            <a href="<?= BASE_URL ?>/customer/dashboard.php">My Account</a>
                            <a href="<?= BASE_URL ?>/customer/orders/index.php">My Orders</a>
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <a href="<?= BASE_URL ?>/admin/dashboard.php">Admin Panel</a>
                        <?php elseif ($user['role'] === 'superadmin'): ?>
                            <a href="<?= BASE_URL ?>/superadmin/dashboard.php">Super Admin</a>
                        <?php endif; ?>
                        <button type="button" class="theme-toggle-menu" id="themeToggleMenu">Dark Mode</button>
                        <a href="#" class="danger" onclick="openLogoutModal(event)">Log Out</a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if ($user): ?>
<div class="modal-overlay" id="logoutModal">
    <div class="modal-box">
        <h3>Log Out?</h3>
        <p>Are you sure you want to log out of your account?</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline btn-sm" style="border-color:#ccc;color:#333;" onclick="closeLogoutModal()">Cancel</button>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-accent btn-sm">Log Out</a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const root = document.documentElement;
    const themeToggleMenu = document.getElementById('themeToggleMenu');

    function currentTheme() {
        return root.classList.contains('theme-dark') ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        root.classList.remove('theme-light', 'theme-dark');
        root.classList.add(theme === 'dark' ? 'theme-dark' : 'theme-light');
        if (themeToggleMenu) {
            themeToggleMenu.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
        }
    }

    applyTheme(currentTheme());

    themeToggleMenu?.addEventListener('click', function (e) {
        e.stopPropagation();
        const nextTheme = currentTheme() === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
        try {
            localStorage.setItem('yvolution-theme', nextTheme);
        } catch (err) {}
    });
})();

document.getElementById('navToggle')?.addEventListener('click', function () {
    document.getElementById('navLinks').classList.toggle('open');
});

const catalogSearch = document.querySelector('.catalog-search');
const searchToggle = document.getElementById('searchToggle');
const catalogSearchInput = document.getElementById('catalogSearchInput');
searchToggle?.addEventListener('click', function () {
    const isOpen = catalogSearch.classList.toggle('is-open');
    searchToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    searchToggle.setAttribute('aria-label', isOpen ? 'Close product search' : 'Open product search');
    if (isOpen) catalogSearchInput.focus();
});

document.getElementById('accountMenuTrigger')?.addEventListener('click', function (e) {
    e.stopPropagation();
    document.getElementById('accountMenu').classList.toggle('open');
});
document.addEventListener('click', function () {
    document.getElementById('accountMenu')?.classList.remove('open');
});

function openLogoutModal(e) {
    e.preventDefault();
    document.getElementById('logoutModal').classList.add('open');
}
function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('open');
}
</script>
<script src="<?= asset_url('/assets/js/search.js') ?>" defer></script>
