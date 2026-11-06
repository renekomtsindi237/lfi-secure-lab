<?php
/**
 * Devoir — Page sécurisée anti-LFI
 *
 * Vérifie le nom de fichier avec une fonction + expression régulière :
 * - alphabétique (lettres uniquement dans le nom, avant l’extension)
 * - pas de caractères spéciaux
 * - pas plus de 20 caractères au total, avec l’extension .php
 * Message spécialisé pour chaque cas d’erreur.
 * Tableau des extensions acceptables (facultatif du devoir).
 * Inclusion uniquement via liste blanche (correction LFI).
 */

declare(strict_types=1);

const MAX_FILENAME = 20;

/** Extensions acceptables (tableau de présentation) */
$acceptedExtensions = [
    'php' => 'Scripts PHP du module (seule extension réellement incluse)',
];

/** Fichiers autorisés — liste blanche anti-LFI */
$acceptedFiles = [
    'accueil.php' => 'Page d’accueil',
    'info.php'    => 'Page d’information',
    'aide.php'    => 'Page d’aide',
];

$submitted = isset($_POST['go']);
$pageInput = trim((string)($_POST['page'] ?? ''));
$message   = '';
$status    = ''; // ok | error
$file      = null;

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

    // Cas 4 : pas alphabétique correctement formé → doit être lettres + ".php"
    // Regex : une ou plusieurs lettres, puis exactement .php
    if (!preg_match('/^[A-Za-z]+\.php$/', $nom)) {
        // Distinguer extension manquante / mauvaise extension / point mal placé
        if (!preg_match('/\.php$/i', $nom)) {
            return 'Cas extension : le fichier doit se terminer par .php.';
        }
        return 'Cas alphabétique : la partie avant .php doit être uniquement alphabétique'
            . ' (ex. accueil.php).';
    }

    // Cas 5 : double point / traversal déjà couvert, mais message explicite si ".."
    if (strpos($nom, '..') !== false) {
        return 'Cas caractères spéciaux : la séquence ".." est interdite.';
    }

    return null; // OK côté format
}

if ($submitted) {
    $erreurFormat = verifierNomFichier($pageInput);

    if ($erreurFormat !== null) {
        $message = $erreurFormat;
        $status  = 'error';
    } elseif (!in_array($pageInput, array_keys($acceptedFiles), true)) {
        // Cas 6 : format OK mais hors liste blanche (correction LFI)
        $message = 'Cas liste blanche : ce fichier n’est pas autorisé.'
            . ' Choisissez un fichier du tableau ci-dessous.';
        $status = 'error';
    } else {
        $includesDir = realpath(__DIR__ . '/includes');
        $candidate   = $includesDir . DIRECTORY_SEPARATOR . $pageInput;
        $real        = ($includesDir !== false) ? realpath($candidate) : false;

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Devoir — Inclusion sécurisée de fichier</title>
    <style>
        body { font-family: "Segoe UI", system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; line-height: 1.45; color: #1f2937; }
        h1 { font-size: 1.4rem; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 1rem 1.1rem; margin: 1rem 0; background: #fff; }
        label { font-weight: 600; display: block; margin-bottom: 0.35rem; }
        input[type="text"] { width: 100%; padding: 0.5rem 0.65rem; font-size: 1rem; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
        button { margin-top: 0.75rem; padding: 0.55rem 1rem; background: #1d4ed8; color: #fff; border: 0; border-radius: 6px; font-size: 1rem; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; font-size: 0.95rem; }
        th, td { border: 1px solid #d1d5db; padding: 0.4rem 0.55rem; text-align: left; }
        th { background: #f3f4f6; }
        code { background: #f3f4f6; padding: 0.1rem 0.3rem; border-radius: 4px; }
        .msg { padding: 0.75rem; border-radius: 6px; margin: 0.75rem 0; }
        .err { background: #fef2f2; color: #991b1b; }
        .ok  { background: #ecfdf5; color: #065f46; }
        .box { background: #f9fafb; border: 1px dashed #d1d5db; padding: 0.85rem; border-radius: 6px; }
        .muted { color: #6b7280; font-size: 0.9rem; }
        ul { margin: 0.4rem 0; padding-left: 1.2rem; }
    </style>
</head>
<body>

    <h1>Inclusion de fichier (code sécurisé)</h1>
    <p class="muted">
        Le nom du fichier doit être <strong>alphabétique</strong>, sans caractères spéciaux,
        avec l’extension <code>.php</code>, et ne pas dépasser <strong>20 caractères</strong> au total.
        Seuls les fichiers de la liste blanche peuvent être inclus (correction de la faille LFI).
    </p>

    <div class="card">
        <form method="post" action="">
            <label for="page">Nom du fichier</label>
            <input type="text" id="page" name="page" maxlength="40"
                   value="<?= htmlspecialchars($pageInput, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="ex. accueil.php" required>
            <button type="submit" name="go" value="1">Valider</button>
        </form>
    </div>

    <div class="card">
        <h2 style="margin:0;font-size:1.05rem;">Fichiers acceptés (liste blanche)</h2>
        <table>
            <thead>
                <tr><th>Nom du fichier</th><th>Description</th></tr>
            </thead>
            <tbody>
            <?php foreach ($acceptedFiles as $name => $desc): ?>
                <tr>
                    <td><code><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin:0;font-size:1.05rem;">Extensions de fichier acceptables</h2>
        <table>
            <thead>
                <tr><th>Extension</th><th>Commentaire</th></tr>
            </thead>
            <tbody>
            <?php foreach ($acceptedExtensions as $ext => $info): ?>
                <tr>
                    <td><code>.<?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted">Dans ce devoir, seule l’extension <code>.php</code> est acceptée.</p>
    </div>

    <?php if ($submitted): ?>
    <div class="card">
        <h2 style="margin:0 0 0.5rem;font-size:1.05rem;">Résultat</h2>
        <div class="msg <?= $status === 'ok' ? 'ok' : 'err' ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php if ($status === 'ok' && $file !== null): ?>
            <div class="box">
                <?php include $file; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card muted">
        <strong>Rappel des cas de message :</strong>
        <ul>
            <li>vide</li>
            <li>longueur &gt; 20</li>
            <li>caractères spéciaux / non alphabétique</li>
            <li>mauvaise extension (pas <code>.php</code>)</li>
            <li>fichier hors liste blanche (anti-LFI)</li>
            <li>succès</li>
        </ul>
        <p>Mettre cette page sur un serveur (ex. <code>php -S 127.0.0.1:8080</code> dans ce dossier, ou copier le dossier dans <code>/var/www/</code>).</p>
    </div>

</body>
</html>
