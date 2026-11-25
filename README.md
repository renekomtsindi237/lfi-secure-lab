# Devoir — Ateliers sécurité : inclusion & téléversement de fichiers

Applications PHP pédagogiques (style DVWA) couvrant deux modules :

1. **Inclusion de fichier (anti-LFI)** : formulaire qui vérifie le **nom de fichier** (alphabétique, sans caractères spéciaux, ≤ 20 caractères avec extension `.php`), affiche un **message spécialisé par cas**, présente un **tableau des extensions acceptables**, et corrige la faille **LFI** via une liste blanche + contrôle de chemin. Déclinée elle aussi en **4 niveaux** (Low/Medium/High vulnérables, Secure corrigé).
2. **Téléversement de fichier** : petite application qui **téléverse des fichiers et affiche leur contenu** sur la page, déclinée en **4 niveaux** : Low, Medium, High (vulnérables, fidèles à DVWA) et Secure (correction complète).

## Contenu

| Élément | Rôle |
|--------|------|
| `public/index.php` | Module inclusion : `?level=low\|medium\|high\|secure` (validation du nom à tous les niveaux, liste blanche en Secure) |
| `public/upload.php` | Module téléversement : `?level=low\|medium\|high\|secure` |
| `public/download.php` | Téléchargement sûr des fichiers du niveau Secure |
| `public/source.php` | « Voir le code source » du module/niveau courant (façon DVWA) |
| `public/assets/style.css` | Feuille de style de l'atelier téléversement |
| `lib/bootstrap.php` | Session, CSRF, en-têtes de sécurité, helpers de niveau |
| `lib/SecureUploadManager.php` | Stockage privé + validation serveur (niveau Secure) |
| `includes/` | Fichiers inclus par le module LFI (`accueil.php`, `info.php`, `aide.php`) |
| `source/` | Logique par niveau du module LFI (`low.php`, `medium.php`, `high.php`, `secure.php`), exécutée **et** affichée par « Voir le code source » |
| `config/` | Configuration Apache + PHP durcies |
| `Dockerfile` / `docker-compose.yml` | Déploiement conteneurisé |

## Prérequis

- [Docker](https://docs.docker.com/get-docker/) + Docker Compose

## Lancer avec Docker

```bash
git clone https://github.com/<TON_COMPTE>/lfi-secure-lab.git
cd lfi-secure-lab
docker compose up -d --build
```

Ouvrir :

- Module inclusion (LFI) : [http://127.0.0.1:8080/](http://127.0.0.1:8080/)
- Module téléversement : [http://127.0.0.1:8080/upload.php](http://127.0.0.1:8080/upload.php)
- Depuis **Kali** (réseau Host-Only / lab) : `http://<IP_DE_LA_MACHINE_DOCKER>:8080/`

Exemples d’IP selon où tourne Docker :

| Docker tourne sur… | URL depuis Kali |
|--------------------|-----------------|
| Windows (hôte VirtualBox) | `http://192.168.56.1:8080/` (vérifier avec `ipconfig`) |
| Kali elle-même | `http://127.0.0.1:8080/` |
| Autre VM Linux | `http://<IP_VM>:8080/` |

Arrêter :

```bash
docker compose down
```

## Sans Docker (PHP local)

Lancer le serveur intégré avec **`public/` comme racine web** (indispensable : le reste du projet reste hors racine web) :

```bash
php -S 0.0.0.0:8080 -t public
```

## Module inclusion (niveaux)

Niveau choisi via `index.php/?level=…` (Low/Medium/High = vulnérables, Secure = corrigé, **niveau par défaut**).

**La validation du nom s’applique à tous les niveaux** : chaîne vide, longueur > 20,
caractères spéciaux / non alphabétiques et mauvaise extension sont **refusés partout**.
Seule la **liste blanche** distingue le niveau Secure des autres.

| Niveau | Contrôles | Faille / contournement |
|--------|-----------|------------------------|
| **Low** | Validation du nom (règles imposées) — **pas** de liste blanche | Absence de liste blanche : tout `.php` alphabétique existant est inclus |
| **Medium** | Validation du nom + liste noire naïve (`../`, `http://`, `https://`, `ftp://`) | Liste noire = mauvaise pratique (de surcroît redondante ici) |
| **High** | Validation du nom + messages par cas | Pas de liste blanche : tout `.php` alphabétique présent est inclus |
| **Secure** | Validation + **liste blanche** + `realpath` confiné à `includes/` | Aucune faille connue |

Chaque niveau propose un bouton **« Voir le code source »** (façon DVWA) :
`public/source.php?module=inclusion&level=…` affiche le code réellement exécuté.

### Règles de validation (tous les niveaux)

- Nom **alphabétique** + extension `.php`
- **Pas** de caractères spéciaux
- **≤ 20** caractères au total (avec `.php`)
- Message dédié pour chaque cas d’erreur (vide, longueur, caractères spéciaux, extension)
- (Secure) Inclusion uniquement des fichiers de la **liste blanche** (anti-LFI)

## Module téléversement (niveaux)

Niveau choisi via `upload.php?level=…` (Low/Medium/High = vulnérables, Secure = corrigé).
Le contenu du fichier est affiché **brut** (non échappé) en Low/Medium/High et de façon **sûre** en Secure.

| Niveau | Contrôles | Faille / contournement | Stockage |
|--------|-----------|------------------------|----------|
| **Low** | Aucun | Téléverser `shell.php` puis l’ouvrir → **RCE** | `public/uploads/low/` (web) |
| **Medium** | `$_FILES["type"]` ∈ image/jpeg\|image/png **et** taille < 100 Ko | Type MIME déclaré par le client → forger `Content-Type: image/jpeg` → RCE | `public/uploads/medium/` (web) |
| **High** | Extension ∈ jpg/jpeg/png **et** < 100 Ko **et** `getimagesize()` | Image **polyglotte** (image valide + code PHP) | `public/uploads/high/` (web) |
| **Secure** | Type MIME **réel** (`finfo`) + extension imposée + taille ≤ 2 Mio + `getimagesize()` + **CSRF** | Aucune faille connue | `storage/` (**hors racine web**, nom aléatoire, non exécutable) |

En Secure, l’accès se fait uniquement via `download.php` (rattaché à la session, `Content-Disposition`, `nosniff`) :
le fichier n’est jamais exécuté ni accessible directement.

## Licence / cadre

Travaux pratiques académiques — usage en environnement de lab isolé.

