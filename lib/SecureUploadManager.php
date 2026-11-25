<?php

/**
 * Téléversement sécurisé — stockage privé + validation serveur stricte.
 *
 * Correction complète des failles de téléversement :
 *  1) Le nom fourni par le client n'est JAMAIS utilisé comme nom de fichier.
 *  2) Nom de stockage aléatoire (bin2hex(random_bytes)) + extension imposée.
 *  3) Validation de la taille (≤ MAX_SIZE) et du type MIME RÉEL (finfo).
 *  4) Double contrôle des images via getimagesize() (en-tête réel).
 *  5) Stockage hors racine web : aucune exécution ni accès direct.
 *  6) Métadonnées JSON par fichier, rattachées à la session (propriétaire).
 */

declare(strict_types=1);

namespace App;

use finfo;
use JsonException;
use RuntimeException;

final class SecureUploadManager
{
    /** Taille maximale acceptée (2 Mio). */
    public const MAX_SIZE = 2 * 1024 * 1024;

    /** Types MIME réels acceptés → extension sûre imposée. */
    public const ALLOWED_TYPES = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'application/pdf' => 'pdf',
        'text/plain'      => 'txt',
    ];

    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    private string $directory;

    private string $ownerHash;

    public function __construct(string $directory, string $ownerSecret)
    {
        $this->directory = rtrim($directory, "/\\");
        $this->ownerHash = hash('sha256', $ownerSecret);
        $this->ensureDirectory();
    }

    public function storageDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Traite un élément de $_FILES.
     *
     * @param array<string,mixed> $file
     * @return array{ok:bool,error:?string,record:?array<string,mixed>}
     */
    public function store(array $file): array
    {
        $errorCode = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($errorCode !== UPLOAD_ERR_OK) {
            return $this->failure(self::describeUploadError($errorCode));
        }

        $tmp  = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $this->failure('Aucun fichier téléversé n’a été reçu.');
        }

        if ($size <= 0) {
            return $this->failure('Le fichier est vide.');
        }

        if ($size > self::MAX_SIZE) {
            return $this->failure('Fichier trop volumineux (maximum 2 Mio).');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset(self::ALLOWED_TYPES[$mime])) {
            return $this->failure('Type de fichier refusé (JPEG, PNG, GIF, PDF ou TXT uniquement).');
        }

        if (in_array($mime, self::IMAGE_TYPES, true) && getimagesize($tmp) === false) {
            return $this->failure('L’image est invalide ou corrompue.');
        }

        $id     = bin2hex(random_bytes(16));
        $stored = $id . '.' . self::ALLOWED_TYPES[$mime];
        $dest   = $this->directory . DIRECTORY_SEPARATOR . $stored;

        if (!move_uploaded_file($tmp, $dest)) {
            return $this->failure('Échec de l’enregistrement du fichier.');
        }

        @chmod($dest, 0640);

        $record = [
            'id'       => $id,
            'stored'   => $stored,
            'original' => basename((string) ($file['name'] ?? 'fichier')),
            'mime'     => $mime,
            'size'     => $size,
            'sha256'   => hash_file('sha256', $dest),
            'is_image' => in_array($mime, self::IMAGE_TYPES, true),
            'owner'    => $this->ownerHash,
            'time'     => time(),
        ];

        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($this->metaPath($id), $json, LOCK_EX) === false) {
            @unlink($dest);

            return $this->failure('Impossible d’enregistrer les métadonnées du fichier.');
        }

        // Chemin absolu requis par l'aperçu (non persisté : recalculé par findOwned()).
        $record['path'] = $dest;

        return ['ok' => true, 'error' => null, 'record' => $record];
    }

    /**
     * Retrouve un fichier appartenant à la session courante.
     *
     * @return array<string,mixed>|null
     */
    public function findOwned(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }

        $meta = $this->metaPath($id);
        if (!is_file($meta)) {
            return null;
        }

        try {
            $record = json_decode((string) file_get_contents($meta), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($record) || !hash_equals((string) ($record['owner'] ?? ''), $this->ownerHash)) {
            return null;
        }

        $stored = (string) ($record['stored'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,4}$/', $stored)) {
            return null;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $stored;
        if (!is_file($path)) {
            return null;
        }

        $record['path'] = $path;

        return $record;
    }

    /**
     * Lit le contenu d'un fichier appartenant à la session courante.
     */
    public function read(string $id): ?string
    {
        $record = $this->findOwned($id);
        if ($record === null) {
            return null;
        }

        $content = file_get_contents($record['path']);

        return $content === false ? null : $content;
    }

    public static function describeUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
            UPLOAD_ERR_PARTIAL    => 'Le téléversement a été interrompu.',
            UPLOAD_ERR_NO_FILE    => 'Veuillez sélectionner un fichier.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant (erreur serveur).',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d’écrire le fichier sur le disque (erreur serveur).',
            UPLOAD_ERR_EXTENSION  => 'Téléversement bloqué par une extension PHP.',
            default               => 'Aucun fichier reçu.',
        };
    }

    private function metaPath(string $id): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.json';
    }

    /**
     * @return array{ok:false,error:string,record:null}
     */
    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'record' => null];
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!@mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Impossible de créer le dossier de stockage privé.');
        }
    }
}
