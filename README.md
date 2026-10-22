# jturazzi/laravel-system-info

Exposes the same token-protected `GET /api/system-info` endpoint in every Laravel application, consumed by the central [`status`](https://github.com/jturazzi/status) dashboard.

## What is reported

| Block | Content |
|-------|---------|
| `status` | `ok`, `degraded` or `down` (HTTP 503 when `down`) + `issues` list |
| `app` | name, environment, debug, URL, **version, commit, build date**, maintenance |
| `runtime` | PHP, Laravel, SAPI, server, OS, OPcache, PHP limits, extensions |
| `packages` | versions of key packages (Inertia, Livewire, Socialite…) |
| `database` | driver, **version** (MariaDB / MySQL / SQLite / PostgreSQL), latency, size, pending migrations |
| `cache` | store + write/read check |
| `queue` | connection, pending jobs, failed jobs |
| `storage` | writable, free disk space, log size |

Becomes `degraded` on: maintenance mode, pending migrations, failed jobs, broken cache, non-writable storage, less than 10% free disk.
Becomes `down` when the database is unreachable.

Error messages are limited to the exception class and code (no host or credentials).

## Installation

```bash
composer require jturazzi/laravel-system-info
```

The service provider is auto-discovered.

`.env`:

```dotenv
SYSTEM_INFO_TOKEN=<long random token>   # openssl rand -hex 32
```

Without `SYSTEM_INFO_TOKEN`, the route responds 404.

```bash
curl -H "Authorization: Bearer $SYSTEM_INFO_TOKEN" https://app.example.com/api/system-info
php artisan system-info   # same report from the CLI (e.g. inside the container)
```

## Version and commit in the Docker image

`.git` is excluded from the image, so CI injects this information at build time.

`Dockerfile` (end of the final stage, after `composer install` to keep the layer cache):

```dockerfile
ARG APP_COMMIT
ARG APP_BUILD_DATE
ENV APP_COMMIT=${APP_COMMIT} \
    APP_BUILD_DATE=${APP_BUILD_DATE}
```

GitLab CI:

```yaml
docker build \
  --build-arg APP_COMMIT="$CI_COMMIT_SHORT_SHA" \
  --build-arg APP_BUILD_DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  -t "$IMAGE_TAG" .
```

GitHub Actions (`docker/build-push-action`):

```yaml
- name: Build date
  id: build-date
  run: echo "value=$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$GITHUB_OUTPUT"

- uses: docker/build-push-action@v7
  with:
    build-args: |
      APP_COMMIT=${{ github.sha }}
      APP_BUILD_DATE=${{ steps.build-date.outputs.value }}
```

The application version is deliberately **not** injected by CI: an empty or branch-name
`APP_VERSION` baked into the image would override the one defined in `config/app.php`
or in `docker-compose.yml`.

Version resolution order: `APP_VERSION` → `config('app.version')` → root `composer.json` version.

## Configuration

```bash
php artisan vendor:publish --tag=system-info-config
```

Lets you change the path (`SYSTEM_INFO_PATH`), the list of reported packages and the alert thresholds.

## License

MIT. See [LICENSE](LICENSE).
