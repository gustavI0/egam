# DDEV Migration Design

**Date:** 2026-02-24
**Status:** Approved

## Context

The project currently uses a wodby/docker4drupal stack (`docker-compose.yml` + `.env`) with PHP 8.3, MariaDB 11.4, Nginx, and Mailhog. The goal is to replace this with DDEV for a simpler, more standardised local dev experience.

## Goals

- Replace the custom docker-compose stack with DDEV
- Preserve all current service equivalents (PHP 8.3, MariaDB 11.4, mail catching)
- Keep existing `settings.local.php` dev settings intact
- Remove wodby-specific files that are no longer needed

## What Gets Created

- **`.ddev/config.yaml`** — DDEV project config: Drupal 11, PHP 8.3, MariaDB 11.4, project name `every-game`, local URL `https://every-game.ddev.site`

## What Gets Removed

- `docker-compose.yml`
- `.env` (wodby-specific)
- `.env.example` (wodby-specific)

## Settings Changes

DDEV auto-injects database credentials via `settings.ddev.php`, which is included from `settings.php`. The existing `settings.local.php` stays as-is except:

- Update `trusted_host_patterns` to include `every-game.ddev.site`

DDEV's standard Drupal integration adds a `require` for `settings.ddev.php` in `settings.php` if not already present.

## Services

| Service | Current | DDEV equivalent |
|---------|---------|-----------------|
| PHP | wodby/drupal-php:8.3-dev | PHP 8.3 (built-in) |
| Database | wodby/mariadb:11.4 | MariaDB 11.4 (built-in) |
| Web server | wodby/nginx | nginx (built-in) |
| Mail | Mailhog | Mailpit (built-in, ≥ v1.22) |
| Xdebug | Manual env vars | `ddev xdebug on/off` |

Mailpit is accessible at `https://every-game.ddev.site:8026`. No add-on required.

## Out of Scope

- Database/files import from production (done separately after setup)
- CI/CD changes (production uses its own Docker setup, unaffected)

## Common Commands After Migration

```bash
ddev start          # Start environment
ddev stop           # Stop environment
ddev drush cr       # Clear cache
ddev xdebug on      # Enable Xdebug
ddev xdebug off     # Disable Xdebug
ddev import-db --file=dump.sql.gz   # Import a database dump
ddev launch         # Open site in browser
ddev launch --mailpit  # Open Mailpit
```
