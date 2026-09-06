<?php
require_once __DIR__ . '/../config/app.php';
require_once BASE_PATH . '/includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Something went wrong. Please try again.');
    redirect('/public/index.php#contact');
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
    set_flash('error', 'Please fill in your name, a valid email, and a message.');
    redirect('/public/index.php#contact');
}

$pdo = Database::connect();
$stmt = $pdo->prepare(
    "INSERT INTO contact_inquiries (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)"
);
$stmt->execute([$name, $email, $phone, $subject, $message]);

// Notify the shop by email (non-blocking — form still succeeds even if mail fails)
$smtpConfig = require BASE_PATH . '/config/api_keys.php';
$mailer = new Mailer();
$body = email_template('New Contact Inquiry', "
    <p><strong>From:</strong> " . e($name) . " (" . e($email) . ")</p>
    <p><strong>Phone:</strong> " . e($phone ?: 'Not provided') . "</p>
    <p><strong>Subject:</strong> " . e($subject ?: 'General Inquiry') . "</p>
    <p><strong>Message:</strong><br>" . nl2br(e($message)) . "</p>
");
$mailer->send($smtpConfig['smtp']['from_email'], 'Yvolution Team', 'New Inquiry: ' . ($subject ?: 'General'), $body);

set_flash('success', 'Thanks for reaching out! We\'ll get back to you shortly.');
redirect('/public/index.php#contact');
