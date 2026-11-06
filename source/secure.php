<?php
/**
 * SECURE — correction complète LFI + RFI
 *
 * Principes :
 * 1) Liste blanche exclusive (seuls les fichiers prévus existent)
 * 2) Refus de toute URL / wrapper / chemin absolu / traversal
 * 3) Validation du format du nom (longueur, extension, caractères)
 * 4) Résolution du chemin réelle sous le dossier includes/ (realpath)
 * 5) Aucun include d’entrée utilisateur brute
 */

$file = $pageInput ?? ($_GET['page'] ?? '');
$message = '';

$includesDir = realpath(__DIR__ . '/../includes');
if ($includesDir === false) {
    $message = 'Erreur serveur : dossier includes inaccessible.';
    $file = null;
    return;
}

/**
 * Validation de forme + messages personnalisés (comme High), puis whitelist.
 */
function validatePageNameSecure(string $name): ?string
{
    if ($name === '') {
        return 'Erreur : veuillez indiquer un nom de fichier.';
    }

    if (strlen($name) > 20) {
        return 'Erreur : le nom ne doit pas dépasser 20 caractères (actuel : ' . strlen($name) . ').';
    }

    if (preg_match('/[\\\\\/:*?"<>|\0]/', $name)) {
        return 'Erreur : caractères spéciaux interdits dans le nom (ex. / \\ : * ? " < > |).';
    }

    if (strpos($name, '..') !== false) {
        return 'Erreur : la séquence ".." est interdite.';
    }

    // Anti-RFI / wrappers
    if (preg_match('#^(https?|ftp|php|data|file|zlib|glob|phar):#i', $name)) {
        return 'Erreur : inclusion distante ou wrapper PHP refusé (protection RFI).';
    }

    if (!preg_match('/\.php$/i', $name)) {
        return 'Erreur : seule l’extension .php est acceptée.';
    }

    // En Secure on autorise les noms de la whitelist (file1.php contient un chiffre)
    // → on vérifie seulement un format sûr : lettres, chiffres, _ et -
    $base = substr($name, 0, -4);
    if ($base === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $base)) {
        return 'Erreur : nom invalide. Utilisez uniquement lettres, chiffres, tirets ou underscores, puis .php.';
    }

    return null;
}

$error = validatePageNameSecure($file);
if ($error !== null) {
    $message = $error;
    $file = null;
    return;
}

// Liste blanche exclusive (anti-LFI)
$whitelist = array_keys($acceptedFiles); // fournie par index.php
if (!in_array($file, $whitelist, true)) {
    $message = 'Erreur : ce fichier n’est pas dans la liste des fichiers acceptés.';
    $file = null;
    return;
}

$candidate = $includesDir . DIRECTORY_SEPARATOR . $file;
$real = realpath($candidate);

// Le fichier doit exister et rester DANS includes/
if ($real === false || !is_file($real) || strpos($real, $includesDir . DIRECTORY_SEPARATOR) !== 0) {
    $message = 'Erreur : accès refusé — chemin non autorisé.';
    $file = null;
    return;
}

$file = $real;
