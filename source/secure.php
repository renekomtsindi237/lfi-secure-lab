<?php
/**
 * SECURE — correction complète : validation + liste blanche + realpath confiné.
 *
 * 1) Validation du format du nom (règles imposées) ;
 * 2) Liste blanche exclusive (anti-LFI) ;
 * 3) Résolution realpath confinée au dossier includes/.
 *
 * Variables attendues (définies par index.php) :
 *   $pageInput, $includesDir, $acceptedFiles, verifierNomFichier().
 */

$erreurFormat = verifierNomFichier($pageInput);

if ($erreurFormat !== null) {
    $message = $erreurFormat;
    $status  = 'error';

    return;
}

// Liste blanche exclusive : seuls les fichiers prévus sont autorisés.
if (!in_array($pageInput, array_keys($acceptedFiles), true)) {
    $message = 'Cas liste blanche : ce fichier n’est pas autorisé.'
        . ' Choisissez un fichier du tableau ci-dessous.';
    $status = 'error';

    return;
}

$candidate = $includesDir . DIRECTORY_SEPARATOR . $pageInput;
$real      = ($includesDir !== false) ? realpath($candidate) : false;

// Le fichier doit exister et rester dans includes/ (anti-LFI).
if (
    $real === false
    || !is_file($real)
    || strpos($real, $includesDir . DIRECTORY_SEPARATOR) !== 0
) {
    $message = 'Cas accès : fichier introuvable ou chemin non autorisé.';
    $status  = 'error';

    return;
}

$file    = $real;
$status  = 'ok';
$message = 'Cas succès : le fichier « ' . $pageInput . ' » est valide'
    . ' et a été inclus en toute sécurité.';
