<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', APP_ROOT . '/public');

require_once APP_ROOT . '/lib/SecureUploadManager.php';

use App\SecureUploadManager;

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('LFI_SECURE_LAB');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** En-têtes volontairement adaptés au niveau pédagogique. */
function sendSecurityHeaders(string $level = 'high'): void
{
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if ($level === 'high') {
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    } elseif ($level === 'medium') {
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    }
    // Low n'a volontairement pas de CSP : cela permet la démonstration XSS.
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function requireValidCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? null;
    if (!is_string($submitted) || !hash_equals(csrfToken(), $submitted)) {
        http_response_code(403);
        throw new RuntimeException('Jeton CSRF absent ou invalide. Rechargez la page puis réessayez.');
    }
}

function uploadOwnerSecret(): string
{
    if (!isset($_SESSION['upload_owner']) || !is_string($_SESSION['upload_owner'])) {
        $_SESSION['upload_owner'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['upload_owner'];
}

function secureUploadManager(): SecureUploadManager
{
    static $manager = null;
    if ($manager instanceof SecureUploadManager) {
        return $manager;
    }

    $configured = getenv('UPLOAD_STORAGE_DIR');
    $directory = $configured !== false && $configured !== ''
        ? $configured
        : APP_ROOT . '/storage';

    $manager = new SecureUploadManager($directory, uploadOwnerSecret());
    $storage = $manager->storageDirectory();
    $public = realpath(PUBLIC_ROOT);

    if ($public !== false && ($storage === $public || str_starts_with($storage, $public . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Le stockage privé ne doit jamais se trouver dans la racine web.');
    }

    return $manager;
}

function postBodyExceededLimit(): bool
{
    $length = $_SERVER['CONTENT_LENGTH'] ?? null;
    return is_string($length) && ctype_digit($length) && (int) $length > 6 * 1024 * 1024;
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2, ',', ' ') . ' Mio';
    }

    return number_format($bytes / 1024, 1, ',', ' ') . ' Kio';
}

function labUploadDirectory(string $level): string
{
    if (!in_array($level, ['low', 'medium', 'high'], true)) {
        throw new InvalidArgumentException('Niveau de stockage public invalide.');
    }

    $directory = PUBLIC_ROOT . '/uploads/' . $level;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de créer le dossier de démonstration.');
    }

    return $directory;
}
