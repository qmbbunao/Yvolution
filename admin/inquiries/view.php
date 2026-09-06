<?php
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . '/includes/mailer.php';
require_role('admin', 'superadmin');

$admin = current_user();
$pdo = Database::connect();
$inquiryId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM contact_inquiries WHERE inquiry_id = ?");
$stmt->execute([$inquiryId]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    set_flash('error', 'Inquiry not found.');
    redirect('/admin/inquiries/index.php');
}

// Mark as read the first time it's opened
if ($inquiry['status'] === 'new') {
    $pdo->prepare("UPDATE contact_inquiries SET status = 'read' WHERE inquiry_id = ?")->execute([$inquiryId]);
    $inquiry['status'] = 'read';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $replyMessage = trim($_POST['reply_message'] ?? '');

    if ($replyMessage !== '') {
        $mailer = new Mailer();
        $body = email_template('Re: ' . ($inquiry['subject'] ?: 'Your Inquiry'), "
            <p>Hi " . e($inquiry['name']) . ",</p>
            <p>" . nl2br(e($replyMessage)) . "</p>
            <hr style='border:none;border-top:1px solid #ddd;margin:20px 0;'>
            <p style='font-size:12px;color:#999;'>In response to your message: \"" . e(mb_strimwidth($inquiry['message'], 0, 100, '...')) . "\"</p>
        ");
        $sent = $mailer->send($inquiry['email'], $inquiry['name'], 'Re: ' . ($inquiry['subject'] ?: 'Your Inquiry'), $body);

        if ($sent) {
            $pdo->prepare("UPDATE contact_inquiries SET status = 'responded' WHERE inquiry_id = ?")->execute([$inquiryId]);
            log_audit($pdo, $admin['user_id'], 'inquiry_replied', 'contact_inquiries', $inquiryId);
            set_flash('success', 'Reply sent to ' . $inquiry['email']);
        } else {
            set_flash('error', 'Could not send the email. Check your SMTP configuration in config/api_keys.php.');
        }
        redirect('/admin/inquiries/view.php?id=' . $inquiryId);
    }
}

$pageTitle = 'Inquiry from ' . $inquiry['name'] . ' — Admin';
$activeNav = 'inquiries';
include __DIR__ . '/../../includes/admin_header.php';
?>

<a href="<?= BASE_URL ?>/admin/inquiries/index.php" class="text-link" style="font-size:13px;">&larr; Back to Inquiries</a>

<div class="admin-page-header" style="margin-top:16px;">
    <h1 style="font-size:22px;"><?= e($inquiry['subject'] ?: 'General Inquiry') ?></h1>
    <span class="badge <?= $inquiry['status'] === 'responded' ? 'badge-success' : 'badge-progress' ?>"><?= ucfirst($inquiry['status']) ?></span>
</div>

<div class="card" style="max-width:640px;">
    <div class="card-body">
        <p style="font-size:13px;color:#888;">
            From <strong><?= e($inquiry['name']) ?></strong> &lt;<?= e($inquiry['email']) ?>&gt;
            <?= $inquiry['phone'] ? ' &middot; ' . e($inquiry['phone']) : '' ?>
            &middot; <?= date('M j, Y g:ia', strtotime($inquiry['created_at'])) ?>
        </p>
        <p style="margin-top:16px;white-space:pre-line;line-height:1.7;"><?= e($inquiry['message']) ?></p>
    </div>
</div>

<div class="card" style="max-width:640px;margin-top:20px;">
    <div class="card-body">
        <h3 style="font-size:15px;">Reply by Email</h3>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <textarea class="form-control" name="reply_message" rows="6" placeholder="Type your reply..." required></textarea>
            </div>
            <button type="submit" class="btn btn-accent">Send Reply</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/admin_footer.php'; ?>
