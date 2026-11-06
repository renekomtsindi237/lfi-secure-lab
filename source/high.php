<?php
/**
 * HIGH — contrôles sur le nom (longueur, extension, chiffres, caractères spéciaux)
 * Messages personnalisés selon le cas.
 * Attention : ce n’est PAS encore une correction complète (pas de liste blanche stricte).
 */

$file = $pageInput ?? ($_GET['page'] ?? '');
$message = '';

/**
 * Valide le nom demandé et renvoie un message d’erreur personnalisé, ou null si OK.
 */
function validatePageNameHigh(string $name): ?string
{
    if ($name === '') {
        return 'Erreur : veuillez indiquer un nom de fichier.';
    }

    // Longueur maximale 20 caractères (nom + extension)
    if (strlen($name) > 20) {
        return 'Erreur : le nom ne doit pas dépasser 20 caractères (actuel : ' . strlen($name) . ').';
    }

    // Caractères spéciaux / traversal / schémas distants
    if (preg_match('/[\\\\\/:*?"<>|\0]/', $name)) {
        return 'Erreur : caractères spéciaux interdits dans le nom (ex. / \\ : * ? " < > |).';
    }
    if (strpos($name, '..') !== false) {
        return 'Erreur : la séquence ".." est interdite (tentative de sortir du dossier).';
    }
    if (preg_match('#^(https?|ftp|php|data|file):#i', $name)) {
        return 'Erreur : les préfixes distants ou dangereux (http, https, ftp, php, data, file) sont refusés.';
    }

    // Extension obligatoire .php
    if (!preg_match('/\.php$/i', $name)) {
        return 'Erreur : seule l’extension .php est acceptée.';
    }

    $base = substr($name, 0, -4); // retire ".php"

    if ($base === '') {
        return 'Erreur : le nom avant l’extension .php est vide.';
    }

    // Pas de chiffres dans le nom de base (règle pédagogique High)
    if (preg_match('/[0-9]/', $base)) {
        return 'Erreur : les chiffres sont interdits dans le nom du fichier (exemple refusé : file1.php).';
    }

    // Uniquement lettres, tiret et underscore dans le nom de base
    if (!preg_match('/^[A-Za-z_-]+$/', $base)) {
        return 'Erreur : le nom ne peut contenir que des lettres, tirets ou underscores (avant .php).';
    }

    return null; // OK
}

$error = validatePageNameHigh($file);

if ($error !== null) {
    $message = $error;
    $file = null;
} else {
    $candidate = __DIR__ . '/../includes/' . $file;
    if (!is_file($candidate)) {
        $message = 'Erreur : fichier introuvable parmi les pages du module.';
        $file = null;
    } else {
        $file = $candidate;
    }
}
