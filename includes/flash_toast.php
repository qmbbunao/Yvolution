<?php
$flashMessages = [];
foreach (['success', 'error'] as $flashType) {
    if ($flashMessage = get_flash($flashType)) {
        $flashMessages[] = [$flashType, $flashMessage];
    }
}
?>
<?php if ($flashMessages): ?>
    <div class="flash-toasts" aria-label="Notifications">
        <?php foreach ($flashMessages as [$flashType, $flashMessage]): ?>
            <div class="flash-toast alert alert-<?= e($flashType) ?>" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>">
                <span><?= e($flashMessage) ?></span>
                <button type="button" aria-label="Dismiss notification" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
    document.querySelectorAll('.flash-toast').forEach((toast) => {
        window.setTimeout(() => toast.remove(), 6500);
    });
    </script>
<?php endif; ?>