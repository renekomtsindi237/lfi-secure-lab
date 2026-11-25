<?php
/**
 * MEDIUM — validation du nom + filtrage naïf par liste noire.
 *
 * Le nom est d’abord validé (règles imposées), puis quelques motifs connus sont
 * retirés (« ../ », « http:// », « https:// », « ftp:// »). La liste noire reste
 * une mauvaise pratique : ici elle est de surcroît redondante avec la validation.
 *
 * Variables attendues (définies par index.php) :
 *   $pageInput, $includesDir, verifierNomFichier(), resolveLabTarget().
 */

$erreurFormat = verifierNomFichier($pageInput);

if ($erreurFormat !== null) {
    $message = $erreurFormat;
    $status  = 'error';

    return;
}

// Filtrage naïf (style DVWA Medium) — insuffisant contre LFI/RFI.
$filtered = str_replace(['../', '..\\'], '', $pageInput);
$filtered = str_replace(['http://', 'https://', 'ftp://'], '', $filtered);

$file    = resolveLabTarget($filtered, $includesDir);
$status  = 'ok';
$message = 'Cas succès (Medium) : « ' . $pageInput . ' » validé puis filtré par liste noire et inclus.';
