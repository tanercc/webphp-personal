<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/csrf.php';

/** External CAPTCHA/user lookup boundaries are injectable for offline tests. */
function panelAuthenticate(array $input, callable $captcha, callable $findUser, array $roles, ?callable $reserveAttempt = null): bool
{
    $_SESSION['userId'] = 0;
    $_SESSION['userName'] = 'Guest';
    $_SESSION['userType'] = 'guest';
    $_SESSION['userAuthorized'] = false;
    unset($_SESSION['userImage'], $_SESSION['RF']);

    foreach (['email', 'password', 'g-recaptcha-response'] as $field) {
        if (!isset($input[$field]) || !is_string($input[$field]) || trim($input[$field]) === '') {
            return false;
        }
    }
    $email = trim($input['email']);
    if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($input['password']) > 4096 || strlen($input['g-recaptcha-response']) > 8192) {
        return false;
    }

    try {
        if ($reserveAttempt !== null && $reserveAttempt($email) !== true) {
            return false;
        }
        if ($captcha($input['g-recaptcha-response']) !== true) {
            return false;
        }
        $user = $findUser($email);
        if (!is_array($user) || (int) ($user['is_active'] ?? 0) !== 1
            || (int) ($user['deleted'] ?? 1) !== 0 || (int) ($user['id'] ?? 0) < 1
            || !in_array($user['type'] ?? null, $roles, true)
            || !is_string($user['password'] ?? null)
            || !password_verify(trim($input['password']), $user['password'])) {
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE || !session_regenerate_id(true)) {
            return false;
        }
        panelCsrfRotate();
    } catch (Throwable $error) {
        // Never pass DB queries, CAPTCHA tokens or passwords to the legacy handler.
        return false;
    }

    $_SESSION['userId'] = (int) $user['id'];
    $_SESSION['userName'] = (string) ($user['name'] ?? '');
    $_SESSION['userType'] = $user['type'];
    $_SESSION['userImage'] = ($user['image'] ?? '') ?: '/panel/img/brand.svg';
    $_SESSION['CREATED'] = $_SESSION['LAST_ACTIVITY'] = time();
    $_SESSION['userAuthorized'] = true;
    return true;
}

function panelFindLoginUser(PanelSqliteConnection $db, string $email): ?array
{
    $statement = $db->prepare('SELECT u.id, u.name, u.password, u.image, u.is_active, u.deleted, t.slug AS type
        FROM users u INNER JOIN usertypes t ON t.id = u.type_id
        WHERE u.email = ? AND u.is_active = 1 AND u.deleted = 0
          AND t.is_active = 1 AND t.deleted = 0 LIMIT 1');
    if (!$statement) {
        return null;
    }
    try {
        $statement->bind_param('s', $email);
        $statement->execute();
        return $statement->get_result()->fetch_assoc() ?: null;
    } finally {
        $statement->close();
    }
}

function panelCaptchaAccepted(string $body, int $status): bool
{
    if ($status !== 200) {
        return false;
    }
    $response = json_decode($body, true);
    return is_array($response) && ($response['success'] ?? false) === true
        && empty($response['error-codes'])
        && (!isset($response['action']) || $response['action'] === 'submit');
}

function panelVerifyCaptcha(string $token, string $secret): bool
{
    if ($secret === '' || $token === '') {
        return false;
    }
    $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
    try {
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($curl);
        return is_string($body) && panelCaptchaAccepted($body, (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
    } finally {
        curl_close($curl);
    }
}

/** Atomic fixed 15-minute window: 5 attempts/account and 10 attempts/IP. */
function panelReserveLoginAttempt(string $path, string $ip, string $email, int $now): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP) || is_link($path)) {
        return false;
    }
    $file = @fopen($path, 'c+');
    if (!$file) {
        return false;
    }
    try {
        if (!flock($file, LOCK_EX)) {
            return false;
        }
        @chmod($path, 0600);
        $raw = stream_get_contents($file, 1048577);
        if ($raw === false || strlen($raw) > 1048576) {
            return false;
        }
        $entries = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($entries)) {
            return false;
        }
        foreach ($entries as $key => $entry) {
            if (!is_array($entry) || !is_int($entry['until'] ?? null) || !is_int($entry['count'] ?? null)) {
                return false;
            }
            if ($entry['until'] <= $now) {
                unset($entries[$key]);
            }
        }
        $keys = [hash('sha256', 'ip:' . inet_pton($ip)) => 10, hash('sha256', 'email:' . strtolower($email)) => 5];
        foreach ($keys as $key => $limit) {
            if (($entries[$key]['count'] ?? 0) >= $limit || count($entries) >= 5000) {
                return false;
            }
        }
        foreach ($keys as $key => $limit) {
            $entries[$key] ??= ['until' => $now + 900, 'count' => 0];
            $entries[$key]['count']++;
        }
        $json = json_encode($entries);
        rewind($file);
        return ftruncate($file, 0) && fwrite($file, $json) === strlen($json) && fflush($file);
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}
