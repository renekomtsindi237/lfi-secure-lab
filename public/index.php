<?php
/**
 * Devoir — Inclusion de fichier, 4 niveaux (style DVWA) + correction complète.
 *
 * Niveaux via ?level=low|medium|high|secure :
 *  - Low    : le paramètre « page » est inclus tel quel (LFI/RFI).
 *  - Medium : filtrage naïf par liste noire (contournable).
 *  - High   : validation du nom (règles imposées) mais pas de liste blanche.
 *  - Secure : validation du nom + liste blanche + realpath (correction).
 *
 * Le niveau Secure reprend exactement les critères imposés par le devoir :
 *  - nom alphabétique, sans caractères spéciaux, extension .php, ≤ 20 caractères ;
 *  - un message d'erreur personnalisé pour chaque cas ;
 *  - affichage du contenu du fichier inclus en cas de succès ;
 *  - tableau des fichiers et extensions acceptés.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';

const MAX_FILENAME = 20;

/** Extensions acceptables (tableau de présentation). */
$acceptedExtensions = [
    'php' => 'Scripts PHP du module (seule extension réellement incluse)',
];

/** Fichiers autorisés — liste blanche anti-LFI (niveau Secure). */
$acceptedFiles = [
    'accueil.php' => 'Page d’accueil',
    'info.php'    => 'Page d’information',
    'aide.php'    => 'Page d’aide',
];

/** Définition des niveaux pédagogiques (façon DVWA). */
$levels = [
    'low' => [
        'label' => 'Low',
        'title' => 'Aucune validation',
        'desc'  => 'Le paramètre « page » est inclus tel quel, sans aucun contrôle.',
        'flaw'  => 'Inclusion locale (« /etc/passwd », « ../config/… ») et distante (http://…) possibles → lecture de fichiers / exécution.',
    ],
    'medium' => [
        'label' => 'Medium',
        'title' => 'Filtrage naïf (liste noire)',
        'desc'  => 'Quelques motifs sont retirés (« ../ », « http:// », « https:// », « ftp:// »), mais le contrôle reste insuffisant.',
        'flaw'  => 'Contournable (double écriture « ….// », encodage, chemin absolu) : une liste noire n’est jamais fiable.',
    ],
    'high' => [
        'label' => 'High',
        'title' => 'Validation du nom (règles imposées)',
        'desc'  => 'Le nom est contrôlé : alphabétique, sans caractères spéciaux, extension .php et ≤ 20 caractères. Message dédié par cas.',
        'flaw'  => 'Pas de liste blanche : un fichier .php alphabétique présent sur le serveur reste inclusible.',
    ],
    'secure' => [
        'label' => 'Secure',
        'title' => 'Correction complète',
        'desc'  => 'Validation du nom + liste blanche exclusive + résolution realpath confinée à includes/.',
        'flaw'  => 'Aucune faille connue : seuls les fichiers de la liste blanche sont inclus.',
    ],
];

$level = (string) ($_GET['level'] ?? 'secure');
if (!isset($levels[$level])) {
    $level = 'secure';
}
$levelConfig = $levels[$level];
$isSecure    = $level === 'secure';

// Les niveaux faibles n’imposent pas de CSP (démonstration LFI/RFI) ;
// le niveau Secure applique une CSP stricte.
sendSecurityHeaders($isSecure ? 'high' : 'low');

/**
 * Vérifie le nom du fichier (regex + contrôles).
 * Retourne un message d’erreur spécialisé, ou null si le nom est valide.
 */
function verifierNomFichier(string $nom): ?string
{
    // Cas 1 : vide
    if ($nom === '') {
        return 'Cas vide : veuillez saisir un nom de fichier.';
    }

    // Cas 2 : trop long (nom + extension ≤ 20)
    if (strlen($nom) > MAX_FILENAME) {
        return 'Cas longueur : le nom (avec extension) ne doit pas dépasser '
            . MAX_FILENAME . ' caractères (saisi : ' . strlen($nom) . ').';
    }

    // Cas 3 : caractères spéciaux (tout sauf lettres et le point de l’extension)
    // On refuse aussi les chiffres, espaces, / \ .. etc.
    if (preg_match('/[^A-Za-z.]/', $nom)) {
        return 'Cas caractères spéciaux : le nom ne doit contenir que des lettres'
            . ' et l’extension .php (pas de chiffres, espaces, / \\ : * ? " < > |, etc.).';
    }

    // Cas 4 : forme attendue = lettres + ".php"
    if (!preg_match('/^[A-Za-z]+\.php$/', $nom)) {
        if (!preg_match('/\.php$/i', $nom)) {
            return 'Cas extension : le fichier doit se terminer par .php.';
        }
        return 'Cas alphabétique : la partie avant .php doit être uniquement alphabétique'
            . ' (ex. accueil.php).';
    }

    // Cas 5 : double point / traversal
    if (strpos($nom, '..') !== false) {
        return 'Cas caractères spéciaux : la séquence ".." est interdite.';
    }

    return null; // OK côté format
}

/**
 * Résout une cible d’inclusion pour les niveaux vulnérables (Low/Medium).
 * Essaie le chemin tel quel, puis relativement au dossier includes/.
 */
function resolveLabTarget(string $input, ?string $includesDir): string
{
    if ($input === '') {
        return $input;
    }

    // URL / wrapper (RFI) : laissé tel quel.
    if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $input)) {
        return $input;
    }

    // Chemin absolu : résolu tel quel.
    if ($input[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $input)) {
        $real = realpath($input);

        return $real !== false ? $real : $input;
    }

    // Chemin relatif : tel quel s’il existe, sinon relatif à includes/.
    // realpath() renvoie un chemin absolu (l’include ne dépend plus du cwd).
    $real = realpath($input);
    if ($real !== false) {
        return $real;
    }

    if ($includesDir !== null) {
        $real = realpath($includesDir . DIRECTORY_SEPARATOR . ltrim($input, '/\\'));
        if ($real !== false) {
            return $real;
        }
    }

    return $input;
}

$includesDir = realpath(dirname(__DIR__) . '/includes');

$submitted = isset($_POST['go']);
$pageInput = trim((string) ($_POST['page'] ?? ''));
$message   = '';
$status    = ''; // ok | error
$file      = null;

if ($submitted) {
    if ($level === 'low') {
        // Aucune validation : inclusion directe (faille volontaire).
        if ($pageInput === '') {
            $message = 'Cas vide : veuillez saisir un nom de fichier.';
            $status  = 'error';
        } else {
            $file    = resolveLabTarget($pageInput, $includesDir);
            $status  = 'ok';
            $message = 'Cas succès (Low) : « ' . $pageInput . ' » a été inclus sans aucune validation.';
        }
    } elseif ($level === 'medium') {
        // Filtrage naïf par liste noire : insuffisant (faille volontaire).
        if ($pageInput === '') {
            $message = 'Erreur (Medium) : aucun nom de fichier fourni.';
            $status  = 'error';
        } else {
            $filtered = str_replace(['../', '..\\'], '', $pageInput);
            $filtered = str_replace(['http://', 'https://', 'ftp://'], '', $filtered);
            $file     = resolveLabTarget($filtered, $includesDir);
            $status   = 'ok';
            $message  = 'Cas succès (Medium) : « ' . $pageInput . ' » filtré par liste noire puis inclus.';
        }
    } elseif ($level === 'high') {
        // Validation du nom (règles imposées) mais pas encore de liste blanche.
        $erreurFormat = verifierNomFichier($pageInput);

        if ($erreurFormat !== null) {
            $message = $erreurFormat;
            $status  = 'error';
        } else {
            $candidate = ($includesDir !== false)
                ? $includesDir . DIRECTORY_SEPARATOR . $pageInput
                : null;

            if ($candidate === null || !is_file($candidate)) {
                $message = 'Cas accès : fichier introuvable parmi les pages du module.';
                $status  = 'error';
            } else {
                $file    = $candidate;
                $status  = 'ok';
                $message = 'Cas succès (High) : le fichier « ' . $pageInput . ' » a été inclus'
                    . ' (liste blanche encore absente).';
            }
        }
    } else {
        // SECURE — correction complète : validation + liste blanche + realpath.
        $erreurFormat = verifierNomFichier($pageInput);

        if ($erreurFormat !== null) {
            $message = $erreurFormat;
            $status  = 'error';
        } elseif (!in_array($pageInput, array_keys($acceptedFiles), true)) {
            $message = 'Cas liste blanche : ce fichier n’est pas autorisé.'
                . ' Choisissez un fichier du tableau ci-dessous.';
            $status = 'error';
        } else {
            $candidate = $includesDir . DIRECTORY_SEPARATOR . $pageInput;
            $real      = ($includesDir !== false) ? realpath($candidate) : false;

            // Le fichier doit exister et rester dans includes/ (anti-LFI)
            if (
                $real === false
                || !is_file($real)
                || strpos($real, $includesDir . DIRECTORY_SEPARATOR) !== 0
            ) {
                $message = 'Cas accès : fichier introuvable ou chemin non autorisé.';
                $status  = 'error';
            } else {
                $file    = $real;
                $status  = 'ok';
                $message = 'Cas succès : le fichier « ' . $pageInput . ' » est valide'
                    . ' et a été inclus en toute sécurité.';
            }
        }
    }
}

$badgeClass = $isSecure ? 'high' : $level;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inclusion de fichier — atelier sécurité</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">

    <header class="site-header">
        <div>
            <h1>Inclusion de fichier</h1>
            <p>Atelier « File Inclusion » : valider un nom de fichier et afficher son contenu, du plus vulnérable au plus sûr.</p>
        </div>
        <span class="badge badge-<?= e($badgeClass) ?>"><?= e($levelConfig['label']) ?></span>
    </header>

    <nav class="level-nav">
        <?php foreach ($levels as $key => $cfg): ?>
            <a href="?level=<?= e($key) ?>"<?= $key === $level ? ' class="active"' : '' ?>><?= e($cfg['label']) ?></a>
        <?php endforeach; ?>
        <a href="upload.php">→ Téléversement de fichier</a>
    </nav>

    <div class="card">
        <h2><?= e($levelConfig['label']) ?> — <?= e($levelConfig['title']) ?></h2>
        <p><?= e($levelConfig['desc']) ?></p>
        <p class="help"><strong>Faille / contournement (DVWA) :</strong> <?= e($levelConfig['flaw']) ?></p>
    </div>

    <div class="card">
        <form method="post" action="?level=<?= e($level) ?>">
            <label for="page">Nom du fichier</label>
            <input type="text" id="page" name="page" maxlength="60"
                   value="<?= e($pageInput) ?>"
                   placeholder="ex. accueil.php" required>
            <button type="submit" name="go" value="1">Valider</button>
        </form>
        <?php if ($isSecure): ?>
            <p class="help">Règles : alphabétique, sans caractères spéciaux, extension <code>.php</code>, ≤ 20 caractères.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Fichiers acceptés (liste blanche)</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Nom du fichier</th><th>Description</th></tr>
                </thead>
                <tbody>
                <?php foreach ($acceptedFiles as $name => $desc): ?>
                    <tr>
                        <td><code><?= e($name) ?></code></td>
                        <td><?= e($desc) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Extensions acceptables</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Extension</th><th>Commentaire</th></tr>
                </thead>
                <tbody>
                <?php foreach ($acceptedExtensions as $ext => $info): ?>
                    <tr>
                        <td><code>.<?= e($ext) ?></code></td>
                        <td><?= e($info) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">Dans ce devoir, seule l’extension <code>.php</code> est acceptée.</p>
    </div>

    <?php if ($submitted): ?>
    <div class="card">
        <h2>Résultat</h2>
        <div class="message <?= $status === 'ok' ? 'message-success' : 'message-error' ?>">
            <?= e($message) ?>
        </div>
        <?php if ($status === 'ok' && $file !== null): ?>
            <h3>Contenu du fichier inclus</h3>
            <div class="preview">
                <?php include $file; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card muted">
        <strong>Rappel des cas de message (niveaux High &amp; Secure) :</strong>
        <ul>
            <li>vide</li>
            <li>longueur &gt; 20</li>
            <li>caractères spéciaux / non alphabétique</li>
            <li>mauvaise extension (pas <code>.php</code>)</li>
            <li>fichier hors liste blanche (anti-LFI)</li>
            <li>succès</li>
        </ul>
    </div>

</div>
</body>
</html>
