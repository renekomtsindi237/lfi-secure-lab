<?php

/**
 * Atelier — Téléversement de fichier et affichage du contenu.
 *
 * Quatre niveaux, fidèles au module « File Upload » de DVWA :
 *  - Low    : aucune validation → n'importe quel fichier (RCE via un .php).
 *  - Medium : type MIME déclaré par le client + taille (< 100 Ko) → contournable.
 *  - High   : extension (jpg/jpeg/png) + taille + getimagesize() → image polyglotte.
 *  - Secure : correction complète (validation serveur, nom aléatoire, stockage privé).
 *
 * Le contenu est affiché BRUT (non échappé) pour Low/Medium/High — ce qui montre
 * la divulgation et l'XSS — et de façon SÛRE pour Secure.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

use App\SecureUploadManager;

$levels = [
    'low' => [
        'label' => 'Low',
        'title' => 'Aucune validation',
        'desc'  => 'Le fichier est déplacé tel quel dans un dossier web public. '
                 . 'Aucun contrôle de type, de taille ou d’extension.',
        'flaw'  => 'Téléverser un script PHP (ex. shell.php) puis l’ouvrir → exécution de code à distance (RCE).',
    ],
    'medium' => [
        'label' => 'Medium',
        'title' => 'Type MIME déclaré par le client + taille',
        'desc'  => 'Le type MIME envoyé par le navigateur ($_FILES["type"]) doit valoir image/jpeg ou image/png, '
                 . 'et la taille rester inférieure à 100 Ko.',
        'flaw'  => 'Le type MIME est fourni par le client : envoyer shell.php avec « Content-Type: image/jpeg » contourne le filtre (RCE).',
    ],
    'high' => [
        'label' => 'High',
        'title' => 'Extension + taille + vraie image',
        'desc'  => 'L’extension doit être jpg/jpeg/png, la taille < 100 Ko, et getimagesize() doit confirmer une image valide.',
        'flaw'  => 'Contournable par une image polyglotte (image valide + code PHP ajouté) ; le nom de fichier reste contrôlé par l’attaquant.',
    ],
    'secure' => [
        'label' => 'Secure',
        'title' => 'Correction complète',
        'desc'  => 'Type MIME réel (finfo) + extension imposée + taille (≤ 2 Mio), nom de stockage aléatoire, '
                 . 'stockage hors racine web, aucun accès direct, jeton CSRF.',
        'flaw'  => 'Aucune faille connue : le fichier ne peut être ni exécuté ni atteint directement.',
    ],
];

$level = (string) ($_GET['level'] ?? 'low');
if (!isset($levels[$level])) {
    $level = 'low';
}

$levelConfig = $levels[$level];
$isSecure    = $level === 'secure';

// Les niveaux faibles omettent volontairement la CSP (démonstration XSS) ;
// le niveau Secure applique une CSP stricte.
sendSecurityHeaders($isSecure ? 'high' : 'low');

/**
 * Détermine le type MIME réel d'un fichier.
 */
function detectMime(string $path): string
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

    return is_string($mime) ? $mime : 'application/octet-stream';
}

/**
 * Traite le téléversement pour les niveaux vulnérables (Low/Medium/High).
 *
 * @param array<string,mixed> $file
 * @return array{notice:string,type:string,path:?string,url:?string}
 */
function labProcessUpload(string $level, array $file): array
{
    $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        return ['notice' => SecureUploadManager::describeUploadError($error), 'type' => 'error', 'path' => null, 'url' => null];
    }

    $tmp        = (string) ($file['tmp_name'] ?? '');
    $name       = (string) ($file['name'] ?? 'fichier');
    $size       = (int) ($file['size'] ?? 0);
    $clientMime = (string) ($file['type'] ?? '');
    $ext        = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['notice' => 'Aucun fichier téléversé n’a été reçu.', 'type' => 'error', 'path' => null, 'url' => null];
    }

    if ($level === 'medium') {
        // == DVWA Medium : type MIME déclaré par le client + taille < 100 Ko
        $typeOk = $clientMime === 'image/jpeg' || $clientMime === 'image/png';
        if (!($typeOk && $size < 100000)) {
            return [
                'notice' => 'Fichier refusé : seules les images JPEG ou PNG de moins de 100 Ko sont acceptées (contrôle Medium).',
                'type'   => 'error',
                'path'   => null,
                'url'    => null,
            ];
        }
    }

    if ($level === 'high') {
        // == DVWA High : extension + taille + getimagesize()
        $extOk = in_array($ext, ['jpg', 'jpeg', 'png'], true);
        if (!($extOk && $size < 100000 && @getimagesize($tmp) !== false)) {
            return [
                'notice' => 'Fichier refusé : extension jpg/jpeg/png, taille < 100 Ko et image valide exigées (contrôle High).',
                'type'   => 'error',
                'path'   => null,
                'url'    => null,
            ];
        }
    }

    // Nom de fichier nettoyé (basename) mais tout le reste est conservé : faille.
    $safeName    = basename($name);
    $directory   = labUploadDirectory($level);
    $destination = $directory . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($tmp, $destination)) {
        return ['notice' => 'Le fichier n’a pas pu être enregistré.', 'type' => 'error', 'path' => null, 'url' => null];
    }

    return [
        'notice' => 'Fichier téléversé sous le nom « ' . $safeName . ' » (' . formatBytes($size) . ').',
        'type'   => 'success',
        'path'   => $destination,
        'url'    => 'uploads/' . $level . '/' . rawurlencode($safeName),
    ];
}

/**
 * Aperçu BRUT (niveaux faibles) : le contenu est affiché sans échappement.
 */
function renderRawPreview(string $path, string $url): string
{
    if (str_starts_with(detectMime($path), 'image/')) {
        return '<div class="preview"><img src="' . e($url) . '" alt="Aperçu de l’image téléversée"></div>';
    }

    $content = (string) file_get_contents($path);
    if (strlen($content) > 100000) {
        $content = substr($content, 0, 100000) . "\n… (tronqué)";
    }

    // Rendu brut volontaire : aucune échappement → démonstration XSS / divulgation.
    return '<div class="preview">' . $content . '</div>';
}

/**
 * Aperçu SÛR (niveau Secure) : jamais de code exécuté, contenu échappé.
 *
 * @param array<string,mixed> $record
 */
function renderSafePreview(array $record): string
{
    $url = 'download.php?id=' . rawurlencode((string) $record['id']);

    if (!empty($record['is_image'])) {
        return '<div class="preview"><img src="' . e($url . '&inline=1') . '" alt="Aperçu sécurisé"></div>';
    }

    if (str_starts_with((string) $record['mime'], 'text/')) {
        $content = (string) file_get_contents((string) $record['path']);
        if (strlen($content) > 20000) {
            $content = substr($content, 0, 20000) . "\n… (tronqué)";
        }

        return '<div class="preview"><pre>' . e($content) . '</pre></div>';
    }

    return '<p class="muted">Aperçu indisponible pour ce type. '
        . '<a class="button button-secondary button-small" href="' . e($url) . '">Télécharger</a></p>';
}

$notice     = '';
$noticeType = 'error';
$preview    = '';
$directLink = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (postBodyExceededLimit()) {
        $notice = 'Requête trop volumineuse (limite 6 Mio) : le fichier n’a pas été traité.';
    } elseif ($isSecure) {
        try {
            requireValidCsrf();
        } catch (RuntimeException $e) {
            $notice = $e->getMessage();
        }
    }

    if ($notice === '' && isset($_FILES['uploaded'])) {
        $file = $_FILES['uploaded'];

        if ($isSecure) {
            $result = secureUploadManager()->store($file);
            if ($result['ok'] && $result['record'] !== null) {
                $record       = $result['record'];
                $notice       = 'Fichier accepté et stocké de façon privée sous un nom aléatoire : '
                    . $record['stored'] . ' (' . formatBytes((int) $record['size']) . ').';
                $noticeType   = 'success';
                $preview      = renderSafePreview($record);
            } else {
                $notice = (string) $result['error'];
            }
        } else {
            $info       = labProcessUpload($level, $file);
            $notice     = $info['notice'];
            $noticeType = $info['type'];
            $directLink = (string) ($info['url'] ?? '');

            if ($info['path'] !== null) {
                $preview = renderRawPreview((string) $info['path'], $directLink);
            }
        }
    } elseif ($notice === '') {
        $notice = 'Veuillez sélectionner un fichier.';
    }
}

$badgeClass = $isSecure ? 'high' : $level;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Téléversement de fichier — atelier sécurité</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">

    <header class="site-header">
        <div>
            <h1>Téléversement de fichier</h1>
            <p>Atelier « File Upload » : téléverser un fichier et afficher son contenu, du plus vulnérable au plus sûr.</p>
        </div>
        <span class="badge badge-<?= e($badgeClass) ?>"><?= e($levelConfig['label']) ?></span>
    </header>

    <nav class="level-nav">
        <?php foreach ($levels as $key => $cfg): ?>
            <a href="?level=<?= e($key) ?>"<?= $key === $level ? ' class="active"' : '' ?>><?= e($cfg['label']) ?></a>
        <?php endforeach; ?>
        <a href="index.php">← Inclusion de fichier (LFI)</a>
    </nav>

    <div class="card">
        <h2><?= e($levelConfig['label']) ?> — <?= e($levelConfig['title']) ?></h2>
        <p><?= e($levelConfig['desc']) ?></p>
        <p class="help"><strong>Faille / contournement (DVWA) :</strong> <?= e($levelConfig['flaw']) ?></p>
    </div>

    <div class="card">
        <form method="post" action="?level=<?= e($level) ?>" enctype="multipart/form-data">
            <?php if ($isSecure): ?>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <?php endif; ?>
            <label for="uploaded">Fichier à téléverser</label>
            <input type="file" id="uploaded" name="uploaded" required>
            <button type="submit">Téléverser</button>
            <?php if ($isSecure): ?>
                <p class="help">Types acceptés : JPEG, PNG, GIF, PDF, TXT — 2 Mio maximum.</p>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($notice !== ''): ?>
    <div class="card">
        <h3>Résultat</h3>
        <div class="message message-<?= e($noticeType) ?>"><?= e($notice) ?></div>

        <?php if ($directLink !== ''): ?>
            <p class="help">URL publique du fichier (accès direct — exécution possible) :
                <a href="<?= e($directLink) ?>" target="_blank" rel="noopener"><?= e($directLink) ?></a></p>
        <?php endif; ?>

        <?php if ($preview !== ''): ?>
            <h3>Contenu affiché <?= $isSecure ? '(aperçu sûr)' : '(rendu brut)' ?></h3>
            <?= $preview ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card muted">
        <strong>Rappel des niveaux</strong>
        <ul>
            <li><strong>Low</strong> : aucune validation → RCE direct (fichier .php exécutable).</li>
            <li><strong>Medium</strong> : type MIME client → contournable via un Content-Type forgé.</li>
            <li><strong>High</strong> : image réelle exigée → contournable par une image polyglotte.</li>
            <li><strong>Secure</strong> : validation serveur + nom aléatoire + stockage privé (aucune exécution).</li>
        </ul>
    </div>

</div>
</body>
</html>
