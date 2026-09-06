    </main>
</div>

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

<script>
document.getElementById('adminUserChip')?.addEventListener('click', function (e) {
    e.stopPropagation();
    document.getElementById('adminUserMenu').classList.toggle('open');
});
document.addEventListener('click', function () {
    document.getElementById('adminUserMenu')?.classList.remove('open');
});

function openLogoutModal(e) {
    e.preventDefault();
    document.getElementById('logoutModal').classList.add('open');
}
function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('open');
}
</script>
</body>
</html>
