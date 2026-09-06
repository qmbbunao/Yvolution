<?php
/**
 * Mailer wrapper — Gmail SMTP via PHPMailer.
 * Usage:
 *   $mailer = new Mailer();
 *   $mailer->send('recipient@example.com', 'Subject', '<p>HTML body</p>');
 */

require_once BASE_PATH . '/vendor/phpmailer/src/Exception.php';
require_once BASE_PATH . '/vendor/phpmailer/src/PHPMailer.php';
require_once BASE_PATH . '/vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    private array $config;

    public function __construct()
    {
        $this->config = (require BASE_PATH . '/config/api_keys.php')['smtp'];
    }

    /**
     * Send an HTML email. Returns true on success, false on failure
     * (failures are logged via error_log, never shown to the end user).
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port       = $this->config['port'];

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
