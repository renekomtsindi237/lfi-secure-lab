<?php

/**
 * Téléchargement sécurisé d'un fichier du niveau Secure.
 *
 * Le fichier est stocké hors racine web et n'est jamais exécuté :
 * on ne sert que son contenu, avec un type MIME contrôlé et nosniff.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

$id     = (string) ($_GET['id'] ?? '');
$record = secureUploadManager()->findOwned($id);

if ($record === null) {
    http_response_code(404);
    sendSecurityHeaders('high');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Fichier introuvable ou accès refusé.';
    exit;
}

sendSecurityHeaders('high');
header('Content-Type: ' . (string) $record['mime']);
header('Content-Length: ' . (string) filesize((string) $record['path']));
header(
    'Content-Disposition: ' . (isset($_GET['inline']) ? 'inline' : 'attachment')
    . '; filename="' . rawurlencode((string) $record['original']) . '"'
);

readfile((string) $record['path']);
