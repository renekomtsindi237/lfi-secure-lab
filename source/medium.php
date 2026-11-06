<?php
/**
 * MEDIUM — filtrages simples (insuffisants)
 * On retire quelques motifs connus, mais le contrôle reste contournable.
 */

$file = $pageInput ?? ($_GET['page'] ?? 'accueil.php');

// Filtrage naïf (style DVWA Medium) — insuffisant contre LFI/RFI
$file = str_replace(['../', '..\\'], '', $file);
$file = str_replace(['http://', 'https://', 'ftp://'], '', $file);

$message = '';

if ($file === '' || $file === null) {
    $message = 'Erreur : aucun nom de fichier fourni.';
    $file = null;
} else {
    $candidate = __DIR__ . '/../includes/' . $file;
    if (is_file($candidate)) {
        $file = $candidate;
    }
    // Sinon on laisse $file tel quel → encore dangereux
}
