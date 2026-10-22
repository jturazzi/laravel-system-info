# anefloire/laravel-system-info

Expose la même route `GET /api/system-info` dans toutes les applications Laravel ANEF Loire, consommée par le tableau de bord central [`status`](../status).

## Ce qui est remonté

| Bloc | Contenu |
|------|---------|
| `status` | `ok`, `degraded` ou `down` (HTTP 503 si `down`) + liste `issues` |
| `app` | nom, environnement, debug, URL, **version, commit, date de build**, maintenance |
| `runtime` | PHP, Laravel, SAPI, serveur, OS, OPcache, limites PHP, extensions |
| `packages` | versions des paquets clés (Inertia, Livewire, Socialite…) |
| `database` | driver, **version** (MariaDB / SQLite / PostgreSQL), latence, taille, migrations en attente |
| `cache` | store + test écriture/lecture |
| `queue` | connexion, jobs en attente, jobs en échec |
| `storage` | écriture possible, espace disque libre, taille des logs |

Passe en `degraded` : maintenance, migrations en attente, jobs en échec, cache KO, storage non inscriptible, disque < 10 % libre.
Passe en `down` : base de données injoignable.

Les messages d'erreur se limitent à la classe et au code de l'exception (aucun hôte ni identifiant).

## Installation

```bash
composer config repositories.system-info vcs <URL_DU_DEPOT>
composer require anefloire/laravel-system-info
```

Le service provider est découvert automatiquement.

`.env` :

```dotenv
SYSTEM_INFO_TOKEN=<jeton aléatoire long>   # openssl rand -hex 32
```

Sans `SYSTEM_INFO_TOKEN`, la route répond 404.

```bash
curl -H "Authorization: Bearer $SYSTEM_INFO_TOKEN" https://fleet.anefloire.cloud/api/system-info
php artisan system-info   # même rapport en CLI (dans le conteneur)
```

## Version et commit dans l'image Docker

`.git` est exclu de l'image : la CI injecte les informations au build.

`Dockerfile` (stage final, avant `COPY . /var/www/html`) :

```dockerfile
ARG APP_VERSION
ARG APP_COMMIT
ARG APP_BUILD_DATE
ENV APP_VERSION=${APP_VERSION} \
    APP_COMMIT=${APP_COMMIT} \
    APP_BUILD_DATE=${APP_BUILD_DATE}
```

GitLab CI :

```yaml
docker build \
  --build-arg APP_VERSION="${CI_COMMIT_TAG:-$CI_COMMIT_REF_NAME}" \
  --build-arg APP_COMMIT="$CI_COMMIT_SHORT_SHA" \
  --build-arg APP_BUILD_DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  -t "$IMAGE_TAG" .
```

GitHub Actions (`docker/build-push-action`) :

```yaml
build-args: |
  APP_VERSION=${{ github.ref_name }}
  APP_COMMIT=${{ github.sha }}
  APP_BUILD_DATE=${{ github.event.head_commit.timestamp }}
```

Ordre de résolution de la version : `APP_VERSION` → `config('app.version')` → version du `composer.json` racine.

## Configuration

```bash
php artisan vendor:publish --tag=system-info-config
```

Permet de changer le chemin (`SYSTEM_INFO_PATH`), la liste des paquets remontés et les seuils d'alerte.
