# Saisies à coller dans le champ « Nom du fichier »

**Module inclusion de fichier** — `http://127.0.0.1:8080/`  
Une ligne = un test → puis **Valider**.

> Le champ « Nom du fichier » (paramètre `page`, formulaire **POST**) attend un
> **nom de fichier** (ex. `accueil.php`), **pas** une commande système
> (`ip a` est refusé — c’est normal).

L’application propose **4 niveaux** (onglets en haut de page, `?level=low|medium|high|secure`).
Le **niveau par défaut est Secure**.

**Les règles de validation sont appliquées à TOUS les niveaux** (c’est le critère
imposé) : chaîne vide, nom trop long, caractères spéciaux / non alphabétiques et
mauvaise extension sont refusés **de la même façon en Low, Medium, High et Secure**,
avec un **message personnalisé** pour chaque cas. Ce qui distingue les niveaux,
c’est la **liste blanche** : seul le niveau Secure l’applique.

Chaque page dispose d’un bouton **« Voir le code source »** (façon DVWA) qui
affiche le code PHP réellement exécuté pour le niveau courant.

Fichiers réellement présents dans `includes/` : `accueil.php`, `info.php`, `aide.php`.

---

## 1. Saisies refusées — identiques à TOUS les niveaux

| Saisie | Cas de message attendu |
|--------|-------------------------|
| *(ne rien écrire, cliquer Valider)* | **Cas vide** |
| `abcdefghijklmnopqrstuvwxyz.php` | **Cas longueur** (> 20) |
| `ip a` | **Cas caractères spéciaux** (espace) |
| `/etc/passwd` | **Cas caractères spéciaux** (barre oblique) |
| `../../etc/passwd` | **Cas caractères spéciaux** |
| `....//....//etc/passwd` | **Cas caractères spéciaux** |
| `http://exemple.com/shell.php` | **Cas caractères spéciaux** |
| `php://filter/resource=accueil.php` | **Cas caractères spéciaux** |
| `file:///etc/passwd` | **Cas caractères spéciaux** |
| `file1.php` | **Cas caractères spéciaux** (chiffre interdit) |
| `accueil.txt` | **Cas extension** |
| `test.jpg` | **Cas extension** |
| `accueil..php` | **Cas alphabétique** |

---

## 2. Saisies acceptées — identiques à TOUS les niveaux

```
accueil.php
info.php
aide.php
```
→ *Cas succès* : le nom est valide, le contenu du fichier est affiché.

---

## 3. Différence entre les niveaux — uniquement la liste blanche

### Niveau Secure (par défaut)

Un nom **au bon format** mais **absent de la liste blanche** est refusé :

```
secret.php
admin.php
```
→ *Cas liste blanche* : ce fichier n’est pas autorisé.

### Niveaux Low / Medium / High

Pas de liste blanche : un nom au bon format pointant vers un fichier `.php`
**existant** est inclus. Le niveau Secure est le seul à restreindre à
`accueil.php`, `info.php` et `aide.php`.

---

## 4. Voir le code source

Le bouton **« Voir le code source »** (dans la carte du niveau) ouvre :

```
source.php?module=inclusion&level=low
source.php?module=inclusion&level=medium
source.php?module=inclusion&level=high
source.php?module=inclusion&level=secure
```

→ affiche le code du fichier `source/<niveau>.php` en couleurs.
Pour le module de téléversement : `source.php?module=upload`.

---

## 5. Module téléversement (autre page)

Page dédiée : `http://127.0.0.1:8080/upload.php` (champ **fichier**, `?level=low|medium|high|secure`).
Le contenu est affiché **brut** (Low/Medium/High) ou de façon **sûre** (Secure).

| Niveau | Fichier à téléverser | Résultat attendu |
|--------|----------------------|------------------|
| **Low** | `shell.php` (`<?php echo "PWNED"; ?>`) | accepté, exécutable via `uploads/low/shell.php` (**RCE**) |
| **Medium** | `shell.php` avec `Content-Type: image/png` | type MIME client forgé → **contourné** (RCE) |
| **High** | petite image `image.png` (< 100 Ko) | acceptée ; un `.php` est refusé |
| **Secure** | `plain.txt` / `image.png` / PDF (≤ 2 Mio) | stocké sous nom aléatoire hors racine web, aperçu sûr ; `.php` refusé ; jeton CSRF requis |
