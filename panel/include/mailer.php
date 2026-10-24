<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Read installation settings; constructing a mailer never opens a connection. */
function panelCreateMailer(): \PHPMailer\PHPMailer\PHPMailer
{
    require_once __DIR__ . '/../plugins/phpmailer/autoload.php';
    global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption, $smtp_auth;
    global $mail_from_address, $mail_from_name;

    if (!is_string($smtp_host) || !preg_match('/^(?:[a-zA-Z0-9][a-zA-Z0-9.-]*|\[::1\])$/D', $smtp_host)
        || (!is_int($smtp_port) && !is_string($smtp_port))
        || filter_var($smtp_port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false
        || !is_bool($smtp_auth) || !is_string($smtp_encryption)
        || !in_array($smtp_encryption, ['tls', 'ssl', ''], true)) {
        throw new InvalidArgumentException('Invalid SMTP connection settings');
    }
    $loopback = in_array(strtolower($smtp_host), ['localhost', '127.0.0.1', '[::1]'], true);
    if ($smtp_encryption === '' && !$loopback) { throw new InvalidArgumentException('SMTP requires TLS outside loopback'); }
    if ($smtp_auth && (!is_string($smtp_username) || $smtp_username === '' || !is_string($smtp_password) || $smtp_password === '')) {
        throw new InvalidArgumentException('SMTP credentials required');
    }
    if (!is_string($mail_from_address) || !\PHPMailer\PHPMailer\PHPMailer::validateAddress($mail_from_address)
        || !is_string($mail_from_name) || preg_match('/[\r\n]/', $mail_from_name)) {
        throw new InvalidArgumentException('Invalid sender settings');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->Port = (int) $smtp_port;
    $mail->SMTPAuth = $smtp_auth;
    $mail->Username = $smtp_auth ? $smtp_username : '';
    $mail->Password = $smtp_auth ? $smtp_password : '';
    $mail->SMTPSecure = $smtp_encryption;
    $mail->SMTPAutoTLS = $smtp_encryption !== '';
    $mail->SMTPDebug = 0;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($mail_from_address, $mail_from_name);
    return $mail;
}
