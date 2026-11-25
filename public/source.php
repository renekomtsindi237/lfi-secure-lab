<?php

/**
 * « Voir le code source » (façon DVWA).
 *
 * Affiche, en couleurs, le code PHP réellement exécuté pour le module et le
 * niveau demandés :
 *   - module=inclusion&level=low|medium|high|secure → source/{niveau}.php
 *   - module=upload                                 → public/upload.php
 *
 * La racine web étant public/, les fichiers source/ (hors racine web) ne sont
 * lisibles qu’ici, via le chemin absolu côté serveur.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

// On autorise les styles en ligne (coloration syntaxique de highlight_file()).
sendSecurityHeaders('medium');

$module = (string) ($_GET['module'] ?? 'inclusion');
$level  = (string) ($_GET['level'] ?? 'low');

if (!in_array($level, ['low', 'medium', 'high', 'secure'], true)) {
    $level = 'low';
}

$sources = [
    'inclusion' => APP_ROOT . '/source/' . $level . '.php',
    'upload'    => PUBLIC_ROOT . '/upload.php',
];

$path = $sources[$module] ?? null;

if ($path === null || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Code source introuvable.';
    exit;
}

$label = $module === 'upload'
    ? 'Téléversement de fichier'
    : 'Inclusion de fichier — niveau ' . ucfirst($level);

$retour = $module === 'upload'
    ? 'upload.php?level=' . rawurlencode($level)
    : 'index.php?level=' . rawurlencode($level);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Code source — atelier sécurité</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">

    <header class="site-header">
        <div>
            <h1>Code source</h1>
            <p><?= e($label) ?></p>
        </div>
        <a class="button button-secondary button-small" href="<?= e($retour) ?>">← Retour</a>
    </header>

    <div class="card">
        <pre class="source-code"><?php highlight_file($path); ?></pre>
    </div>

</div>
</body>
</html>
