# EVERY GAME A MUSEUM

## Introduction

Ce site a pour objectif de recenser un maximum d'œuvres d'art présentes dans des jeux vidéo. On y trouvera la liste des artistes, des jeux et des œuvres ainsi que de leurs lieux de conservation.

**Site en production** : https://everygameamuseum.com

## Philosophie du projet

Simplicité est le maître mot de ce site, ainsi qu'une recherche de légèreté et d'accessibilité. Le projet privilégie une approche minimaliste avec :
- Du code propre et maintenable
- Des performances optimales
- Une accessibilité maximale
- Un design épuré et efficace

## Stack Technique

### Backend
- **Drupal 11** - CMS moderne avec système d'entités personnalisées
- **PHP 8.2+** - Langage serveur
- **MariaDB** - Base de données
- **Composer** - Gestion des dépendances PHP

### Frontend
- **TailwindCSS** - Framework CSS utilitaire
- **Alpine.js** - Interactions JavaScript légères
- **Twig** - Moteur de templates
- **PhotoSwipe 5.4.4** - Galeries d'images
- **Swiper 11.0.6** - Carrousels d'images

### Développement & Déploiement
- **Docker** - Environnement de développement local
- **GitHub Actions** - CI/CD automatisé
- **Drush** - CLI Drupal pour les tâches d'administration

## Installation Locale

### Prérequis
- Docker et Docker Compose
- Git

### Configuration

1. **Cloner le projet**
```bash
git clone [repository-url]
cd every_game_drupal
```

2. **Copier le fichier d'environnement**
```bash
cp .env.example .env
```

3. **Configurer les variables d'environnement**
Éditer `.env` avec vos paramètres locaux :
```env
PROJECT_NAME=egam
PROJECT_BASE_URL=egam.localhost
DB_NAME=drupal
DB_USER=drupal
DB_PASSWORD=drupal
DB_ROOT_PASSWORD=password
DB_HOST=egam_mariadb
```

4. **Démarrer Docker**
```bash
docker-compose up -d
```

5. **Installer les dépendances**
```bash
docker exec -u wodby egam_php composer install
```

6. **Importer la base de données**
Placer votre dump SQL dans `.docker/mariadb-init/` et redémarrer le conteneur DB, ou :
```bash
docker exec -i egam_mariadb mysql -u root -p[password] drupal < backup.sql
```

7. **Importer la configuration**
```bash
docker exec -u wodby egam_php vendor/bin/drush config:import -y
docker exec -u wodby egam_php vendor/bin/drush cr
```

8. **Accéder au site**
- Site : http://egam.localhost
- MailHog (emails) : http://egam.localhost:8025

### Développement du thème

```bash
cd web/themes/custom/egam

# Installer les dépendances npm
npm install

# Mode développement avec watch
npm run watch

# Build de production
npm run build:prod
```

## Architecture du Projet

### Entités Personnalisées

Le site est construit autour de 5 types d'entités custom :

1. **Artwork** (`egam_artwork`) - Les œuvres d'art trouvées dans les jeux
2. **Artist** (`egam_artist`) - Les artistes créateurs des œuvres
3. **Game** (`egam_game`) - Les jeux vidéo contenant les œuvres
4. **Museum** (`egam_museum`) - Les musées où se trouvent les œuvres originales
5. **Screenshot** (`egam_screenshot`) - Les captures d'écran des jeux

Toutes les entités :
- Étendent `RevisionableContentEntityBase`
- Supportent les révisions et traductions
- Ont des handlers de formulaires personnalisés
- Utilisent `EntityChangedTrait` et `EntityOwnerTrait`

### Structure des Modules

```
web/modules/custom/
├── egam_global/          # Module partagé central
│   ├── src/
│   │   ├── Entities.php  # Enum central pour toutes les entités
│   │   ├── Service/      # Services partagés
│   │   └── Form/         # Formulaires partagés
│   └── ...
├── egam_artwork/         # Module Artwork
├── egam_artist/          # Module Artist
├── egam_game/            # Module Game
├── egam_museum/          # Module Museum
└── egam_screenshot/      # Module Screenshot
```

### Module Global (`egam_global`)

Le module `egam_global` fournit des fonctionnalités partagées :
- **`Entities` enum** : Méthodes centralisées pour toutes les entités (comptage, chargement, routes)
- **`CustomEntityListBuilder`** : Classe de base pour les listes d'entités
- **`RelatedContentHandler`** : Gestion des relations entre entités
- **`HomeCoverHandler`** : Gestion de l'image de couverture de la page d'accueil
- **`ContextManager`** : Service de gestion du contexte
- **`ListFilterForm`** : Système de filtrage pour les listes

### Thème Custom (`egam`)

Le thème utilise :
- **Base theme** : Stable9
- **TailwindCSS** : Configuration dans `tailwind.config.js`
- **Templates Twig** : Organisation par type (block, content, field, etc.)
- **Alpine.js** : Interactions JS minimales (menu burger, modales, etc.)

Structure des templates :
```
web/themes/custom/egam/templates/
├── block/         # Blocs
├── content/       # Entités de contenu
├── field/         # Champs
├── layout/        # Layouts
├── media/         # Médias
├── navigation/    # Menus
├── views/         # Vues
└── webform/       # Formulaires
```

## Commandes Utiles

### Drush

```bash
# Vider le cache
vendor/bin/drush cr

# Exporter la configuration
vendor/bin/drush config:export

# Importer la configuration
vendor/bin/drush config:import

# Mettre à jour la base de données
vendor/bin/drush updatedb

# Reconstruire les caches d'entités
vendor/bin/drush entity:updates

# État du site
vendor/bin/drush status
```

### Docker

```bash
# Démarrer les conteneurs
docker-compose up -d

# Arrêter les conteneurs
docker-compose down

# Voir les logs
docker-compose logs -f

# Accéder au conteneur PHP
docker exec -it egam_php bash

# Exécuter une commande Drush
docker exec -u wodby egam_php vendor/bin/drush [commande]
```

### Composer

```bash
# Installer les dépendances
composer install

# Mettre à jour Drupal core
composer update drupal/core-recommended --with-all-dependencies

# Ajouter un module
composer require drupal/[module_name]
```

## Modules Contrib Principaux

- **admin_toolbar** (3.4) : Barre d'administration améliorée
- **gin** (5.0) + **gin_toolbar** (3.0) : Thème d'administration moderne
- **pathauto** (1.11) : Génération automatique d'alias d'URL
- **metatag** (2.0) : Gestion des balises meta SEO
- **search_api** (1.29) : Fonctionnalités de recherche
- **focal_point** (2.1) : Point focal pour images responsives
- **swiper_formatter** (2.0-beta) : Formateur de champ Swiper
- **views_infinite_scroll** (2.0) : Défilement infini pour les vues
- **simple_sitemap** (4.2) : Génération de sitemap XML
- **webform** (6.3-beta) : Constructeur de formulaires
- **antibot** (2.0) + **honeypot** (2.2) : Protection anti-spam

## Workflow de Développement

1. Créer une branche feature depuis `main`
2. Faire les modifications de code
3. Tester localement
4. Exporter la configuration : `vendor/bin/drush config:export`
5. Commit et push
6. Créer une Pull Request
7. Après merge dans `main`, déploiement automatique via GitHub Actions

## Déploiement

Le projet utilise **GitHub Actions** pour le déploiement automatique :
- Déclenchement automatique sur push vers `main`
- Déploiement sur VPS Docker
- Scripts de déploiement manuel disponibles à la racine du projet (`deploy.sh`, `rollback.sh`, `webhook-listener.sh`)

Pour plus de détails, consulter `CLAUDE.md` section "Deployment & CI/CD".

## Contribution

Les contributions sont les bienvenues ! Merci de :
1. Respecter les conventions de code Drupal
2. Tester vos modifications localement
3. Documenter les nouvelles fonctionnalités
4. Exporter la configuration avant de commit

## Support

Pour toute question ou problème :
- Consulter `CLAUDE.md` pour les détails techniques
- Vérifier la documentation Drupal : https://www.drupal.org/docs
- Ouvrir une issue sur le repository