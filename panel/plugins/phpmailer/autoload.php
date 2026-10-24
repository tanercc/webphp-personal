<?php
// Local bootstrap only. Upstream src/ files remain unmodified.
spl_autoload_register(static function ($class) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    if (str_starts_with($class, $prefix)) {
        $name = substr($class, strlen($prefix));
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/D', $name) && is_file(__DIR__ . '/src/' . $name . '.php')) {
            require_once __DIR__ . '/src/' . $name . '.php';
        }
    }
});
foreach (['PHPMailer' => 'PHPMailer', 'SMTP' => 'SMTP', 'POP3' => 'POP3', 'phpmailerException' => 'Exception'] as $legacy => $modern) {
    if (!class_exists($legacy, false)) { class_alias('PHPMailer\\PHPMailer\\' . $modern, $legacy); }
}
