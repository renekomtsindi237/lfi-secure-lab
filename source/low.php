<?php
/**
 * LOW — validation du nom (critères imposés) mais AUCUNE liste blanche.
 *
 * Le nom est contrôlé (alphabétique, sans caractères spéciaux, extension .php,
 * ≤ 20 caractères) : chaîne vide, nom trop long, caractères spéciaux ou
 * mauvaise extension sont donc refusés, même à ce niveau.
 * Il n’y a toutefois pas de liste blanche : tout nom valide pointant vers un
 * fichier .php existant est inclus.
 *
 * Variables attendues (définies par index.php) :
 *   $pageInput, $includesDir                     (entrée + dossier includes)
 *   verifierNomFichier(), resolveLabTarget()     (helpers communs)
 */

$erreurFormat = verifierNomFichier($pageInput);

if ($erreurFormat !== null) {
    $message = $erreurFormat;
    $status  = 'error';

    return;
}

$file    = resolveLabTarget($pageInput, $includesDir);
$status  = 'ok';
$message = 'Cas succès (Low) : le fichier « ' . $pageInput . ' » a été inclus (sans liste blanche).';
