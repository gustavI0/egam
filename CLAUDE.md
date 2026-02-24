# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Every Game a Museum is a Drupal 11 site that catalogs artworks found in video games, along with their artists, museums, and games. The site emphasizes simplicity, accessibility, and lightweight design.

Live site: https://everygameamuseum.com

## Local Development Environment

The project uses [DDEV](https://ddev.readthedocs.io/) for local development.

### DDEV Commands
```bash
# Start environment
ddev start

# Stop environment
ddev stop

# Open site in browser
ddev launch

# Open Mailpit (email catcher)
ddev mailpit

# SSH into the web container
ddev ssh

# Enable/disable Xdebug
ddev xdebug on
ddev xdebug off

# Import a database dump
ddev import-db --file=dump.sql.gz
```

## Development Commands

### Composer
```bash
# Install dependencies
composer install

# Update Drupal core and modules
composer update drupal/core-recommended --with-all-dependencies
```

### Drush (via DDEV)
```bash
# Clear cache
ddev drush cr

# Import configuration
ddev drush config:import

# Export configuration
ddev drush config:export

# Update database after code changes
ddev drush updatedb

# Rebuild entity caches
ddev drush entity:updates
```

### Theme Development
```bash
cd web/themes/custom/egam

# Install npm dependencies
npm install

# Build CSS (TailwindCSS)
npm run build

# Watch for CSS changes during development
npm run watch

# Production build (minified)
npm run build:prod
```

## Architecture

### Custom Entity System

The project uses a custom entity architecture centered around five main content types:

1. **Artwork** (`egam_artwork`) - Artworks found in games
2. **Artist** (`egam_artist`) - Artists who created the artworks
3. **Game** (`egam_game`) - Video games containing artworks
4. **Museum** (`egam_museum`) - Museums where original artworks are housed
5. **Screenshot** (`egam_screenshot`) - Screenshots from games

All custom entities:
- Extend `RevisionableContentEntityBase`
- Use `EntityChangedTrait` and `EntityOwnerTrait`
- Have custom form handlers in `Form/` directories
- Have access control handlers
- Support revisions and translations

### Global Module (`egam_global`)

The `egam_global` module provides shared functionality across all entity modules:

- **`Entities` enum** (`web/modules/custom/egam_global/src/Entities.php`): Central enum providing methods for all entity types including counting, route generation, and batch loading
- **`CustomEntityListBuilder`**: Base class for entity list builders
- **`HomeController`**: Handles homepage rendering
- **`RelatedContentHandler`**: Manages relationships between entities
- **`HomeCoverHandler`**: Manages homepage cover image selection
- **`ContextManager`**: Service for managing context across the site
- **`ListFilterForm`**: Provides filtering functionality for entity lists

### Module Structure

Each entity module follows this pattern:
```
web/modules/custom/egam_[entity]/
├── config/                    # Config schema and install config
├── src/
│   ├── Entity/               # Entity class definitions
│   ├── Form/                 # Add/Edit/Delete forms
│   └── [Entity]AccessControlHandler.php
│   └── [Entity]ListBuilder.php
├── egam_[entity].info.yml
├── egam_[entity].routing.yml
└── egam_[entity].module
```

### Theme (`egam`)

The custom theme uses:
- **TailwindCSS** for styling (configured in `tailwind.config.js`)
- **Alpine.js** for minimal JavaScript interactivity
- **PhotoSwipe** (v5.4.4) for image galleries
- **Swiper** (v11.0.6) for image carousels
- **Base theme**: Stable9

Key theme customizations in `web/themes/custom/egam/egam.theme`:
- `egam_preprocess()`: Handles responsive image sizing (max height 1080px)
- `egam_preprocess_page()`: Adds `is_content` variable for entity canonical pages
- `egam_theme_suggestions_field_alter()`: Custom field template suggestions for Swiper galleries
- `egam_preprocess_menu__main()`: Activates menu items based on current entity collection

Template structure:
```
web/themes/custom/egam/templates/
├── block/         # Block templates
├── content/       # Content entity templates
├── field/         # Field templates
├── layout/        # Layout templates
├── media/         # Media templates
├── navigation/    # Menu templates
├── views/         # Views templates
└── webform/       # Webform templates
```

### Configuration Management

Configuration is stored in `config/sync/` and should be exported after making changes:
```bash
vendor/bin/drush config:export
```

Configuration can be safely ignored for certain modules using the `config_ignore` module (already installed).

## Key Drupal Modules

### Contrib Modules
- **admin_toolbar** (3.4): Enhanced admin toolbar
- **gin** (5.0) + **gin_toolbar** (3.0): Admin theme
- **pathauto** (1.11): Automatic URL alias generation
- **metatag** (2.0): SEO meta tags
- **search_api** (1.29): Search functionality
- **focal_point** (2.1): Focal point for responsive images
- **swiper_formatter** (2.0-beta): Swiper field formatter
- **views_infinite_scroll** (2.0): Infinite scroll for views
- **simple_sitemap** (4.2): XML sitemap generation
- **webform** (6.3-beta): Form builder
- **antibot** (2.0) + **honeypot** (2.2): Spam protection

## Development Workflow

1. Make code changes
2. Clear cache: `vendor/bin/drush cr`
3. Run database updates if needed: `vendor/bin/drush updatedb`
4. Export configuration: `vendor/bin/drush config:export`
5. Test changes
6. Commit code and configuration together

## Deployment & CI/CD

The project uses automated deployment to production via GitHub Actions and manual deployment scripts.

### Automated Deployment (GitHub Actions)

Located in `.github/workflows/deploy.yml`

- **Trigger**: Automatically deploys on push to `main` branch, or manually via workflow_dispatch
- **Target**: Production VPS running Docker containers
- **Process**:
  1. SSH into production server
  2. Pull latest changes from main branch
  3. Install Composer dependencies (production mode)
  4. Enable maintenance mode
  5. Update database schema
  6. Import configuration
  7. Rebuild cache
  8. Disable maintenance mode

**Required GitHub Secrets**:
- `DEPLOY_HOST`: Production server hostname
- `DEPLOY_USER`: SSH username
- `DEPLOY_SSH_KEY`: SSH private key for authentication
- `DEPLOY_PATH`: Path to project directory (e.g., `/home/ubuntu/egam`)

### Manual Deployment Scripts

Located in project root directory.

#### Deploy Script (`deploy.sh`)
```bash
# Deploy main branch
./deploy.sh

# Deploy specific branch
./deploy.sh feature-branch
```

Features:
- Pulls latest changes from specified branch (default: main)
- Installs dependencies via Docker (container: `egam_drupal`)
- Enables maintenance mode during deployment
- Updates database and imports configuration
- Sends email notification on completion

#### Rollback Script (`rollback.sh`)
```bash
./rollback.sh
```

Emergency rollback to previous commit:
- Lists last 10 commits
- Prompts for commit hash to rollback to
- Performs full deployment process at specified commit
- Sends email notification

#### Webhook Listener (`webhook-listener.sh`)
```bash
./webhook-listener.sh
```

Alternative deployment trigger:
- Runs as background service
- Polls `/tmp/deploy-egam.trigger` file every 10 seconds
- Triggers deployment when file exists
- Logs output to `~/egam/deploy.log`

### Docker Environment

All deployment scripts assume a Docker-based production environment:
- **Container name**: `egam_drupal`
- **User**: `www-data`
- Commands are executed via: `docker exec -u www-data egam_drupal [command]`

### Deployment Checklist

Before deploying:
1. Test changes locally
2. Export configuration: `vendor/bin/drush config:export`
3. Commit and push to main branch
4. Monitor GitHub Actions workflow for deployment status
5. Verify site functionality after deployment

If issues occur:
1. Use `rollback.sh` to revert to previous working commit
2. Check `~/egam/deploy.log` for error details
3. Manually fix issues and redeploy

## Important Patterns

### Using the Entities Enum

Always use the `Entities` enum when working with custom entities:

```php
use Drupal\egam_global\Entities;

// Get count of artworks
$count = Entities::Artwork->count();

// Get collection route
$route = Entities::Game->getCollectionRoute(); // 'view.games.grid'

// Load multiple entities
$games = Entities::Game->loadMultiple([1, 2, 3]);
```

### Entity Relationships

Entities reference each other through entity reference fields:
- Artworks reference Artists (creators) and Museums (where originals are housed)
- Screenshots reference Games and Artworks (which artwork is shown)
- Games contain references to Artworks found within them

### Thumbnail Logic

Recent commits (732a82d, b919ee2) added thumbnail field and logic for handling image displays in teaser modes.
