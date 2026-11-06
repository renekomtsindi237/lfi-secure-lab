# Devoir — Inclusion de fichier sécurisée (anti-LFI)

Application PHP pédagogique : formulaire web qui vérifie le **nom de fichier** (alphabétique, sans caractères spéciaux, ≤ 20 caractères avec extension `.php`), affiche un **message spécialisé par cas**, présente un **tableau des extensions acceptables**, et corrige la faille **LFI** via une liste blanche + contrôle de chemin.

## Contenu

| Élément | Rôle |
|--------|------|
| `index.php` | Page + formulaire + validation sécurisée |
| `includes/` | Fichiers autorisés (`accueil.php`, `info.php`, `aide.php`) |
| `source/` | Variantes Low / Medium / High / Secure (comparaison TP) |
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

- Sur la machine hôte : [http://127.0.0.1:8080/](http://127.0.0.1:8080/)
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

```bash
php -S 0.0.0.0:8080
```

## Règles de validation (devoir)

- Nom **alphabétique** + extension `.php`
- **Pas** de caractères spéciaux
- **≤ 20** caractères au total (avec `.php`)
- Message dédié pour chaque cas d’erreur
- Inclusion uniquement des fichiers de la **liste blanche** (anti-LFI)

## Licence / cadre

Travaux pratiques académiques — usage en environnement de lab isolé.
