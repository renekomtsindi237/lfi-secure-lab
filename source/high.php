<?php
/**
 * HIGH — validation du nom (règles imposées), sans liste blanche.
 *
 * Le nom doit être alphabétique, sans caractères spéciaux, en .php et ≤ 20
 * caractères, puis correspondre à un fichier présent dans includes/.
 * Il n’y a toujours PAS de liste blanche : tout nom valide pointant vers un
 * fichier existant du dossier est inclus.
 *
 * Variables attendues (définies par index.php) :
 *   $pageInput, $includesDir, verifierNomFichier().
 */

$erreurFormat = verifierNomFichier($pageInput);

if ($erreurFormat !== null) {
    $message = $erreurFormat;
    $status  = 'error';

    return;
}

$candidate = ($includesDir !== false)
    ? $includesDir . DIRECTORY_SEPARATOR . $pageInput
    : null;

if ($candidate === null || !is_file($candidate)) {
    $message = 'Cas accès : fichier introuvable parmi les pages du module.';
    $status  = 'error';

    return;
}

$file    = $candidate;
$status  = 'ok';
$message = 'Cas succès (High) : le fichier « ' . $pageInput . ' » a été inclus (liste blanche encore absente).';
