<?php
/**
 * LOW — volontairement vulnérable (comme DVWA Low)
 * Aucune validation : le paramètre page est inclus tel quel.
 * → LFI et RFI possibles si allow_url_include est activé.
 */

$file = $pageInput ?? ($_GET['page'] ?? 'accueil.php');

// Préfixe local pour les pages du lab (mais rien n’empêche ../ ou http://)
if ($file !== '' && strpos($file, 'http://') !== 0 && strpos($file, 'https://') !== 0 && strpos($file, '/') !== 0) {
    $candidate = __DIR__ . '/../includes/' . $file;
    if (is_file($candidate)) {
        $file = $candidate;
    }
}
